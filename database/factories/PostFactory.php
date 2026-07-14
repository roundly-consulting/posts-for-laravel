<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Posts\Enums\PostStatus;
use RoundlyConsulting\Posts\Models\Post;
use RoundlyConsulting\Posts\Support\PostModel;

/** @extends Factory<Post> */
final class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * Build the model the host configured, not the packaged one — otherwise a host
     * that points `posts.model` at its own subclass still gets a packaged `Post`
     * out of the factory the package ships.
     *
     * @return class-string<Post>
     */
    public function modelName(): string
    {
        return PostModel::class();
    }

    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(4);

        return [
            'status' => PostStatus::Draft,
            'published_at' => null,
            'title' => ['en' => $title],
            'perex' => ['en' => $this->faker->sentence()],
            'content' => ['en' => $this->faker->paragraph()],
        ];
    }

    public function draft(): self
    {
        return $this->state(['status' => PostStatus::Draft, 'published_at' => null]);
    }

    public function published(): self
    {
        return $this->state(['status' => PostStatus::Published, 'published_at' => now()]);
    }

    public function scheduled(): self
    {
        return $this->state(['status' => PostStatus::Scheduled, 'published_at' => now()->addDay()]);
    }

    public function archived(): self
    {
        return $this->state(['status' => PostStatus::Archived]);
    }

    public function forAuthor(Model $author): self
    {
        return $this->state([
            'author_type' => $author->getMorphClass(),
            'author_id' => $author->getKey(),
        ]);
    }

    /** @param  array<string, string>  $titles */
    public function withTitles(array $titles): self
    {
        return $this->state(['title' => $titles]);
    }
}
