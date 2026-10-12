<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Organization> */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'plan' => 'professional',
            'is_active' => true,
        ];
    }

    public function suspended(string $reason = 'Unpaid invoice'): static
    {
        return $this->state(fn () => ['suspended_at' => now(), 'suspended_reason' => $reason]);
    }
}
