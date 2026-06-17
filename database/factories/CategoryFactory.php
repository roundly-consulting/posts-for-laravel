<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Posts\Models\Category;

/** @extends Factory<Category> */
final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => $this->faker->unique()->words(2, true)],
            'position' => 0,
        ];
    }

    public function childOf(Category $parent): self
    {
        return $this->state(['parent_id' => $parent->getKey()]);
    }
}
