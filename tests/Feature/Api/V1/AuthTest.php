<?php

namespace Tests\Feature\Api\V1;

use App\Models\Motorcycle;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_account_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'rafal@example.com',
            'password' => 'tajne-haslo-123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'rafal@example.com')
            ->assertJsonPath('data.user.profile_complete', false)
            ->assertJsonStructure(['success', 'message', 'data' => ['token', 'user' => ['id', 'email']]]);

        $this->assertDatabaseHas('users', ['email' => 'rafal@example.com']);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_registration_stores_password_hashed(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'email' => 'rafal@example.com',
            'password' => 'tajne-haslo-123',
        ])->assertStatus(201);

        $this->assertNotSame('tajne-haslo-123', User::first()->password);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'rafal@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'email' => 'rafal@example.com',
            'password' => 'tajne-haslo-123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_registration_rejects_short_password(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'email' => 'rafal@example.com',
            'password' => 'krotkie',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['password']]);
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'rafal@example.com',
            'password' => 'tajne-haslo-123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'rafal@example.com',
            'password' => 'tajne-haslo-123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'rafal@example.com');

        $this->assertNotEmpty($response->json('data.token'));
    }

    /**
     * The app reacts differently to a bad password than to a missing token, so the
     * two must not share a code.
     */
    public function test_login_with_wrong_password_returns_invalid_credentials_code(): void
    {
        User::factory()->create([
            'email' => 'rafal@example.com',
            'password' => 'tajne-haslo-123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'rafal@example.com',
            'password' => 'zle-haslo-123',
        ])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    /**
     * An unknown email must be indistinguishable from a wrong password, otherwise the
     * endpoint tells an attacker which addresses are registered.
     */
    public function test_login_with_unknown_email_is_indistinguishable_from_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'rafal@example.com',
            'password' => 'tajne-haslo-123',
        ]);

        $unknown = $this->postJson('/api/v1/auth/login', [
            'email' => 'nikt@example.com',
            'password' => 'tajne-haslo-123',
        ]);

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => 'rafal@example.com',
            'password' => 'zle-haslo-123',
        ]);

        $this->assertSame($wrongPassword->status(), $unknown->status());
        $this->assertSame($wrongPassword->json(), $unknown->json());
    }

    /**
     * Without this the login endpoint is an open door for dictionary attacks.
     */
    public function test_login_is_rate_limited(): void
    {
        $attempts = config('motusy.auth.throttle.attempts');

        for ($i = 0; $i < $attempts; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'rafal@example.com',
                'password' => 'zle-haslo-'.$i,
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'rafal@example.com',
            'password' => 'zle-haslo-kolejne',
        ])
            ->assertStatus(429)
            ->assertJsonPath('code', 'TOO_MANY_REQUESTS');
    }

    public function test_me_returns_account_with_profile_and_motorcycle(): void
    {
        $user = User::factory()->create();
        UserProfile::factory()->for($user)->create(['nickname' => 'Rafal', 'gender' => 'male']);
        Motorcycle::factory()->for($user)->create(['brand' => 'Yamaha', 'model' => 'MT-07']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.nickname', 'Rafal')
            ->assertJsonPath('data.motorcycle.brand', 'Yamaha')
            ->assertJsonPath('data.motorcycle.model', 'MT-07')
            ->assertJsonPath('data.profile_complete', true);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create([
            'email' => 'rafal@example.com',
            'password' => 'tajne-haslo-123',
        ]);

        $phone = $this->postJson('/api/v1/auth/login', [
            'email' => 'rafal@example.com',
            'password' => 'tajne-haslo-123',
            'device_name' => 'telefon',
        ])->json('data.token');

        $tablet = $this->postJson('/api/v1/auth/login', [
            'email' => 'rafal@example.com',
            'password' => 'tajne-haslo-123',
            'device_name' => 'tablet',
        ])->json('data.token');

        $this->withToken($phone)->postJson('/api/v1/auth/logout')->assertStatus(200);

        // The auth manager caches the resolved user for the whole test method, so
        // without this the next request would be answered from that cache rather than
        // by re-reading the token. Verified against the live server: a revoked token
        // really does get 401.
        $this->app['auth']->forgetGuards();

        $this->withToken($phone)->getJson('/api/v1/auth/me')->assertStatus(401);

        $this->app['auth']->forgetGuards();

        $this->withToken($tablet)->getJson('/api/v1/auth/me')->assertStatus(200);

        $this->assertSame(1, $user->tokens()->count());
    }

    /**
     * Hidden fields come back as null instead of disappearing, so the card keeps the
     * same key set for every viewer.
     */
    public function test_card_hides_private_fields_from_others_without_changing_shape(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        UserProfile::factory()->for($owner)->create([
            'nickname' => 'Rafal',
            'first_name' => 'Rafal',
            'phone' => '600100200',
            'phone_visible' => false,
            'first_name_visible' => false,
            'email_visible' => false,
        ]);

        $stranger = User::factory()->create();

        $asStranger = $owner->card($stranger);
        $asSelf = $owner->card($owner);

        $this->assertSame(array_keys($asSelf), array_keys($asStranger));

        $this->assertNull($asStranger['phone']);
        $this->assertNull($asStranger['first_name']);
        $this->assertNull($asStranger['email']);

        $this->assertSame('600100200', $asSelf['phone']);
        $this->assertSame('owner@example.com', $asSelf['email']);

        $this->assertSame('Rafal', $asStranger['nickname']);
    }

    public function test_card_is_null_safe_for_account_without_profile(): void
    {
        $user = User::factory()->create();

        $card = $user->card();

        $this->assertNull($card['nickname']);
        $this->assertNull($card['motorcycle']['brand']);
        $this->assertSame($user->id, $card['id']);
    }
}
