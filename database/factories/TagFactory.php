<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Posts\Models\Tag;

/** @extends Factory<Tag> */
final class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => $this->faker->unique()->word()],
        ];
    }
}
