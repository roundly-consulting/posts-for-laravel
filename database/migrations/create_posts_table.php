<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->boolean('visible')->default(true);
            $table->longText('content')->nullable();
            $table->unsignedBigInteger('author_id')->nullable()->index();
            $table->timestamps();
        });
    }
};
