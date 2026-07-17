<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create((string) config('posts.tables.tags', 'post_tags'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->jsonb('name')->nullable();
            $table->jsonb('slug')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
