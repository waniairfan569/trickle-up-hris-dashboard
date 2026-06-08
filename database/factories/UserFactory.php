<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
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

    public function asAdmin(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = \App\Models\Role::firstOrCreate(['name' => 'super_admin'], ['display_name' => 'Super Admin']);
            $user->roles()->attach($role);
        });
    }

    public function asManager(int $managerId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'manager_id' => $managerId,
        ])->afterCreating(function (User $user) {
            $role = \App\Models\Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
            $user->roles()->attach($role);
        });
    }

    public function asEmployee(int $managerId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'manager_id' => $managerId,
        ])->afterCreating(function (User $user) {
            $role = \App\Models\Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
            $user->roles()->attach($role);
        });
    }

    public function asRestricted(): static
    {
        return $this->afterCreating(function (User $user) {
            // Explicitly no roles or just restricted
        });
    }
}
