<?php

namespace Tests\Feature\Api\V1;

use App\Models\Motorcycle;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadTest extends TestCase
{
    use RefreshDatabase;

    private function disk()
    {
        return Storage::disk(config('motusy.uploads.disk'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('motusy.uploads.disk'));
    }

    public function test_avatar_upload_stores_the_file_and_returns_an_absolute_url(): void
    {
        $user = User::factory()->has(UserProfile::factory(), 'profile')->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('ja.jpg', 400, 400),
            ]);

        $response->assertStatus(200);

        $path = $user->fresh()->profile->avatar;

        $this->disk()->assertExists($path);

        // The card must expose the disk URL, not the raw column value.
        $this->assertSame($this->disk()->url($path), $response->json('data.avatar'));
    }

    /**
     * The stored name must not come from the upload, because these files sit under
     * the webroot.
     */
    public function test_stored_name_is_generated_not_taken_from_the_upload(): void
    {
        $user = User::factory()->has(UserProfile::factory(), 'profile')->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('../../evil.jpg'),
            ])
            ->assertStatus(200);

        $stored = $user->fresh()->profile->avatar;

        $this->assertStringNotContainsString('evil', $stored);
        $this->assertStringNotContainsString('..', $stored);
        $this->assertStringStartsWith(config('motusy.uploads.avatar_directory').'/', $stored);
    }

    public function test_replacing_the_avatar_deletes_the_previous_file(): void
    {
        $user = User::factory()->has(UserProfile::factory(), 'profile')->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/avatar', ['avatar' => UploadedFile::fake()->image('pierwsze.jpg')])
            ->assertStatus(200);

        $first = $user->fresh()->profile->avatar;

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/avatar', ['avatar' => UploadedFile::fake()->image('drugie.jpg')])
            ->assertStatus(200);

        $second = $user->fresh()->profile->avatar;

        $this->assertNotSame($first, $second);
        $this->disk()->assertMissing($first);
        $this->disk()->assertExists($second);
    }

    public function test_deleting_the_avatar_clears_the_column_and_the_file(): void
    {
        $user = User::factory()->has(UserProfile::factory(), 'profile')->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/avatar', ['avatar' => UploadedFile::fake()->image('ja.jpg')])
            ->assertStatus(200);

        $path = $user->fresh()->profile->avatar;

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/profile/avatar')
            ->assertStatus(200)
            ->assertJsonPath('data.avatar', null);

        $this->disk()->assertMissing($path);
        $this->assertNull($user->fresh()->profile->avatar);
    }

    public function test_oversized_avatar_is_scaled_down_to_the_configured_limit(): void
    {
        $user = User::factory()->has(UserProfile::factory(), 'profile')->create();
        $max = config('motusy.uploads.avatar_max_dimension');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('duze.jpg', $max * 3, $max * 3),
            ])
            ->assertStatus(200);

        [$width, $height] = getimagesizefromstring(
            $this->disk()->get($user->fresh()->profile->avatar)
        );

        $this->assertSame($max, $width);
        $this->assertSame($max, $height);
    }

    /**
     * scale() maps to scaleDown, so a picture smaller than the limit must be left as
     * it is rather than blown up.
     */
    public function test_small_image_is_not_upscaled(): void
    {
        $user = User::factory()->has(UserProfile::factory(), 'profile')->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('male.jpg', 120, 90),
            ])
            ->assertStatus(200);

        [$width, $height] = getimagesizefromstring(
            $this->disk()->get($user->fresh()->profile->avatar)
        );

        $this->assertSame(120, $width);
        $this->assertSame(90, $height);
    }

    public function test_uploads_are_converted_to_the_configured_format(): void
    {
        $user = User::factory()->has(UserProfile::factory(), 'profile')->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/avatar', ['avatar' => UploadedFile::fake()->image('ja.png', 300, 300)])
            ->assertStatus(200);

        $path = $user->fresh()->profile->avatar;

        $this->assertStringEndsWith('.'.config('motusy.uploads.format'), $path);
        $this->assertStringContainsString(
            'image/'.config('motusy.uploads.format'),
            (string) $this->disk()->mimeType($path),
        );
    }

    public function test_motorcycle_photo_uses_its_own_larger_limit(): void
    {
        $user = User::factory()->has(Motorcycle::factory())->create();
        $max = config('motusy.uploads.motorcycle_photo_max_dimension');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/motorcycle/photo', [
                'photo' => UploadedFile::fake()->image('moto.jpg', $max * 2, $max * 2),
            ])
            ->assertStatus(200);

        [$width] = getimagesizefromstring($this->disk()->get($user->fresh()->motorcycle->photo));

        $this->assertSame($max, $width);
    }

    public function test_avatar_rejects_a_non_image(): void
    {
        $user = User::factory()->has(UserProfile::factory(), 'profile')->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => UploadedFile::fake()->create('zlosliwy.php', 10, 'application/x-php'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['avatar']]);
    }

    public function test_avatar_rejects_a_file_over_the_size_limit(): void
    {
        $user = User::factory()->has(UserProfile::factory(), 'profile')->create();
        $tooBig = config('motusy.uploads.max_kilobytes') + 128;

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('duze.jpg')->size($tooBig),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['avatar']]);
    }

    public function test_avatar_upload_without_a_profile_explains_what_is_missing(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/profile/avatar', ['avatar' => UploadedFile::fake()->image('ja.jpg')])
            ->assertStatus(409)
            ->assertJsonPath('code', 'PROFILE_REQUIRED');
    }

    public function test_motorcycle_photo_upload_and_removal(): void
    {
        $user = User::factory()->has(Motorcycle::factory())->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/motorcycle/photo', [
                'photo' => UploadedFile::fake()->image('moto.jpg'),
            ]);

        $response->assertStatus(200);

        $path = $user->fresh()->motorcycle->photo;
        $this->assertSame($this->disk()->url($path), $response->json('data.motorcycle.photo'));
        $this->disk()->assertExists($path);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/motorcycle/photo')
            ->assertStatus(200)
            ->assertJsonPath('data.motorcycle.photo', null);

        $this->disk()->assertMissing($path);
    }

    public function test_motorcycle_photo_upload_without_a_motorcycle_explains_what_is_missing(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/motorcycle/photo', ['photo' => UploadedFile::fake()->image('moto.jpg')])
            ->assertStatus(409)
            ->assertJsonPath('code', 'MOTORCYCLE_REQUIRED');
    }

    public function test_uploads_require_authentication(): void
    {
        $this->postJson('/api/v1/profile/avatar', ['avatar' => UploadedFile::fake()->image('ja.jpg')])
            ->assertStatus(401);

        $this->postJson('/api/v1/motorcycle/photo', ['photo' => UploadedFile::fake()->image('moto.jpg')])
            ->assertStatus(401);
    }

    /**
     * The avatar is public by design: people who meet on the road see it. What must
     * not leak are the fields the owner hid.
     */
    public function test_avatar_is_visible_to_others_while_hidden_fields_stay_hidden(): void
    {
        $owner = User::factory()->create();
        UserProfile::factory()->for($owner)->create(['phone' => '600100200', 'phone_visible' => false]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/profile/avatar', ['avatar' => UploadedFile::fake()->image('ja.jpg')])
            ->assertStatus(200);

        $card = $owner->fresh()->card(User::factory()->create());

        $this->assertSame($this->disk()->url($owner->fresh()->profile->avatar), $card['avatar']);
        $this->assertNull($card['phone']);
    }
}
