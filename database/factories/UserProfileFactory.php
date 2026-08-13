<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfile>
 */
class UserProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nickname' => fake()->unique()->userName(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'bio' => fake()->sentence(),
            'phone' => fake()->numerify('6########'),
            'phone_visible' => false,
            'email_visible' => false,
            'first_name_visible' => false,
            'last_name_visible' => false,
        ];
    }
}
