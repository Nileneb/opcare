<?php

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role' => 'employee',
            'invited_by' => User::factory(),
            'token' => Str::uuid(),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now(),
        ]);
    }
}
