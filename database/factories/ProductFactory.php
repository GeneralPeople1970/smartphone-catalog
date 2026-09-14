<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand' => 'OPPO',
            'name' => fake()->unique()->bothify('Phone ???? ####'),
            'price' => '3999',
            'soc_name' => 'Test SoC',
            'battery_capacity' => 5000,
            'image_url' => '/assets/logo.png',
            'status' => 'published',
            'specs' => [],
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }
}
