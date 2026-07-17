<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The host author tables, one per key type — the OUTBOUND axis posts' `author` morph points
 * at. All three exist on every run so a single suite can hold models of each shape; which one
 * a given leg actually uses is decided by `posts.key_type`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('uuid_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
        });

        Schema::create('ulid_users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
        });
    }
};
