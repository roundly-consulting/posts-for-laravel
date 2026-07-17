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
        // Two independent axes, deliberately two config keys. `key_type` is OUTBOUND — the
        // key type of the host's author models, which the host owns. `primary_key_type` is
        // INBOUND — this table's own id, which other packages' morph columns point at.
        // A host with bigint users and uuid posts is legitimate; one key could not say it.
        $keyType = KeyType::fromConfig('posts.key_type');
        $primaryKeyType = KeyType::fromConfig('posts.primary_key_type');
        $morphName = (string) config('posts.author.morph-name', 'author');
        $authorNullable = (bool) config('posts.author.nullable', true);

        Schema::create((string) config('posts.tables.posts', 'posts'), function (Blueprint $table) use ($keyType, $primaryKeyType, $morphName, $authorNullable): void {
            match ($primaryKeyType) {
                KeyType::BigInt => $table->id(),
                KeyType::Uuid => $table->uuid('id')->primary(),
                KeyType::Ulid => $table->ulid('id')->primary(),
            };

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
