<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create((string) config('posts.tables.tag_post', 'post_tag'), function (Blueprint $table): void {
            $table->uuid('post_id')->index();
            $table->uuid('tag_id')->index();
            $table->timestamps();

            $table->primary(['post_id', 'tag_id']);
        });
    }
};
