<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

return new class extends Migration
{
    public function up(): void
    {
        $keyType = KeyType::fromConfig('posts.key_type');
        $morphName = (string) config('posts.author.morph-name', 'author');
        $authorNullable = (bool) config('posts.author.nullable', true);

        Schema::create((string) config('posts.tables.posts', 'posts'), function (Blueprint $table) use ($keyType, $morphName, $authorNullable): void {
            $table->uuid('id')->primary();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->jsonb('title')->nullable();
            $table->jsonb('slug')->nullable();
            $table->jsonb('perex')->nullable();
            $table->jsonb('content')->nullable();
            $table->jsonb('meta_title')->nullable();
            $table->jsonb('meta_description')->nullable();
            $table->jsonb('seo')->nullable();
            $table->morphKey($morphName, $keyType, $authorNullable);
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
