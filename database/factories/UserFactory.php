<?php

namespace Database\Factories;

<<<<<<< HEAD
=======
use App\Models\User;
>>>>>>> 08ed6007b014c43be8e6804161c672aea09a4410
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
<<<<<<< HEAD
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
=======
 * @extends Factory<User>
>>>>>>> 08ed6007b014c43be8e6804161c672aea09a4410
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
