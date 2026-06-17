<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Posts\Enums\PostsAuthorKeyType;

return new class extends Migration
{
    public function up(): void
    {
        $keyType = PostsAuthorKeyType::fromConfig();
        $authorNullable = (bool) config('posts.author.nullable', true);

        Schema::create((string) config('posts.tables.posts', 'posts'), function (Blueprint $table) use ($keyType, $authorNullable): void {
            $table->uuid('id')->primary();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->json('title')->nullable();
            $table->json('slug')->nullable();
            $table->json('perex')->nullable();
            $table->json('content')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->json('seo')->nullable();
            $table->string('author_type')->nullable();
            $keyType->columnDefinition($table, 'author_id', $authorNullable);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['author_type', 'author_id']);
        });
    }
};
