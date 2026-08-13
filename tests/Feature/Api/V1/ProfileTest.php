<?php

namespace Tests\Feature\Api\V1;

use App\Models\Motorcycle;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_PROFILE = [
        'nickname' => 'Rafal',
        'gender' => 'male',
    ];

    private const VALID_MOTORCYCLE = [
        'brand' => 'Yamaha',
        'model' => 'MT-07',
        'production_year' => 2021,
        'color' => 'czarny',
    ];

    public function test_profile_is_created_on_first_save(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile', self::VALID_PROFILE)
            ->assertStatus(200)
            ->assertJsonPath('data.nickname', 'Rafal')
            ->assertJsonPath('data.gender', 'male');

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'nickname' => 'Rafal',
        ]);
    }

    public function test_second_save_updates_instead_of_creating_another_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile', self::VALID_PROFILE)
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile', [...self::VALID_PROFILE, 'nickname' => 'Rafau'])
            ->assertStatus(200)
            ->assertJsonPath('data.nickname', 'Rafau');

        $this->assertSame(1, UserProfile::where('user_id', $user->id)->count());
    }

    public function test_motorcycle_is_created_then_updated_in_place(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/motorcycle', self::VALID_MOTORCYCLE)
            ->assertStatus(200)
            ->assertJsonPath('data.motorcycle.brand', 'Yamaha');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/motorcycle', [...self::VALID_MOTORCYCLE, 'model' => 'MT-09'])
            ->assertStatus(200)
            ->assertJsonPath('data.motorcycle.model', 'MT-09');

        $this->assertSame(1, Motorcycle::where('user_id', $user->id)->count());
    }

    public function test_profile_complete_flips_only_after_both_profile_and_motorcycle_exist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile', self::VALID_PROFILE)
            ->assertJsonPath('data.profile_complete', false);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/motorcycle', self::VALID_MOTORCYCLE)
            ->assertJsonPath('data.profile_complete', true);
    }

    public function test_visibility_flags_are_stored_and_applied_to_the_card(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/profile', [
                ...self::VALID_PROFILE,
                'phone' => '600100200',
                'first_name' => 'Rafal',
                'phone_visible' => true,
                'first_name_visible' => false,
            ])
            ->assertStatus(200);

        $card = $owner->fresh()->card($stranger);

        $this->assertSame('600100200', $card['phone']);
        $this->assertNull($card['first_name']);
    }

    public function test_profile_rejects_unknown_gender(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/profile', [...self::VALID_PROFILE, 'gender' => 'motocykl'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['gender']]);
    }

    public function test_motorcycle_rejects_year_from_the_future(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/motorcycle', [...self::VALID_MOTORCYCLE, 'production_year' => (int) date('Y') + 5])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['production_year']]);
    }

    public function test_motorcycle_rejects_absurdly_old_year(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/motorcycle', [...self::VALID_MOTORCYCLE, 'production_year' => 1500])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['production_year']]);
    }

    public function test_profile_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/profile', self::VALID_PROFILE)
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/motorcycle', self::VALID_MOTORCYCLE)
            ->assertStatus(401);
    }

    /**
     * There is no route parameter to target somebody else, but the guarantee is worth
     * locking down: saving always writes to the caller's own row.
     */
    public function test_saving_never_touches_another_users_profile(): void
    {
        $owner = User::factory()->create();
        UserProfile::factory()->for($owner)->create(['nickname' => 'Wlasciciel']);

        $other = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->postJson('/api/v1/profile', [...self::VALID_PROFILE, 'nickname' => 'Obcy'])
            ->assertStatus(200);

        $this->assertSame('Wlasciciel', $owner->fresh()->profile->nickname);
        $this->assertSame('Obcy', $other->fresh()->profile->nickname);
    }
}
