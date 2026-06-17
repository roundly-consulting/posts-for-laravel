<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Posts\Models\Post;

/** @extends Factory<Post> */
final class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'visible' => true,
            'content' => $this->faker->paragraph(),
            'author_id' => null,
        ];
    }

    public function hidden(): self
    {
        return $this->state(['visible' => false]);
    }
}
