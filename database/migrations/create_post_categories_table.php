<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\Posts\Support\PostsConfig;
use RoundlyConsulting\Posts\Support\PostSlugs;

return new class extends Migration
{
    public function up(): void
    {
        $primaryKeyType = KeyType::fromConfig('posts.primary_key_type');

        Schema::create(PostsConfig::categoriesTable(), function (Blueprint $table) use ($primaryKeyType): void {
            match ($primaryKeyType) {
                KeyType::BigInt => $table->id(),
                KeyType::Uuid => $table->uuid('id')->primary(),
                KeyType::Ulid => $table->ulid('id')->primary(),
            };

            // Self-referential: must track the primary key above or the tree breaks.
            $table->ownerKey('parent_id', $primaryKeyType, nullable: true, index: true);

            $table->jsonb('name')->nullable();
            $table->jsonb('slug')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // One unique index per supported locale (`slug->en`, `slug->sk`, …), trashed rows
        // included — the same shape the model's slug definition probes. Locales are read
        // from sluggable's SlugLocales now; add later ones with `php artisan sluggable:indexes`.
        PostSlugs::ensureIndexes(PostsConfig::categoriesTable());
    }
};
