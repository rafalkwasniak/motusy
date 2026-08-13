<?php

namespace Tests\Feature\Api\V1;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    use RefreshDatabase;

    private const VALID = [
        'device_id' => 'abc-123',
        'platform' => 'android',
        'app_version' => '1.0.0',
    ];

    public function test_registers_a_device(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/devices', self::VALID)
            ->assertStatus(200)
            ->assertJsonPath('data.device_id', 'abc-123')
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.has_push_token', false);

        $this->assertDatabaseHas('devices', ['user_id' => $user->id, 'device_id' => 'abc-123']);
    }

    public function test_repeated_registration_updates_instead_of_duplicating(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/devices', self::VALID)->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/devices', [...self::VALID, 'app_version' => '1.1.0'])
            ->assertStatus(200)
            ->assertJsonPath('data.app_version', '1.1.0');

        $this->assertSame(1, Device::where('user_id', $user->id)->count());
    }

    /**
     * FCM replaces these over time, so a later registration has to overwrite it.
     */
    public function test_push_token_can_be_added_and_replaced(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/devices', [...self::VALID, 'push_token' => 'fcm-pierwszy'])
            ->assertJsonPath('data.has_push_token', true);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/devices', [...self::VALID, 'push_token' => 'fcm-drugi']);

        $this->assertSame('fcm-drugi', Device::where('user_id', $user->id)->first()->push_token);
    }

    /**
     * Binding the device to the token it signed in with is what later lets a single
     * device be signed out, and push be addressed to a device rather than an account.
     */
    public function test_device_is_bound_to_the_access_token_used_to_register_it(): void
    {
        $user = User::factory()->create([
            'email' => 'rafal@example.com',
            'password' => 'tajne-haslo-123',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'rafal@example.com',
            'password' => 'tajne-haslo-123',
            'device_name' => 'telefon',
        ])->json('data.token');

        $this->withToken($token)->postJson('/api/v1/devices', self::VALID)->assertStatus(200);

        $device = Device::where('user_id', $user->id)->first();

        $this->assertNotNull($device->personal_access_token_id);
        $this->assertSame($user->tokens()->first()->id, $device->personal_access_token_id);
    }

    /**
     * The same phone may serve two accounts, so device_id is unique per user only.
     */
    public function test_same_device_id_can_belong_to_two_accounts(): void
    {
        foreach ([User::factory()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/devices', self::VALID)
                ->assertStatus(200);
        }

        $this->assertSame(2, Device::where('device_id', 'abc-123')->count());
    }

    public function test_rejects_an_unknown_platform(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/devices', [...self::VALID, 'platform' => 'symbian'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['platform']]);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/devices', self::VALID)->assertStatus(401);
    }
}
