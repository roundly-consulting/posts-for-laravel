<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\Sluggable\DataTransferObjects\SlugIndexSpec;
use RoundlyConsulting\Sluggable\Schema\SlugIndexes;

return new class extends Migration
{
    public function up(): void
    {
        $primaryKeyType = KeyType::fromConfig('posts.primary_key_type');

        Schema::create((string) config('posts.tables.tags', 'post_tags'), function (Blueprint $table) use ($primaryKeyType): void {
            match ($primaryKeyType) {
                KeyType::BigInt => $table->id(),
                KeyType::Uuid => $table->uuid('id')->primary(),
                KeyType::Ulid => $table->ulid('id')->primary(),
            };

            $table->jsonb('name')->nullable();
            $table->jsonb('slug')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // One unique index per supported locale (`slug->en`, `slug->sk`, …), trashed rows
        // included — the same shape the model's slug definition probes. Locales are read
        // from sluggable's SlugLocales now; add later ones with `php artisan sluggable:indexes`.
        if ((bool) config('posts.slugs.unique', true)) {
            SlugIndexes::ensure(SlugIndexSpec::localeMap((string) config('posts.tables.tags', 'post_tags'), 'slug'));
        }
    }
};
