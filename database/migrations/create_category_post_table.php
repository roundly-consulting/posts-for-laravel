<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\Posts\Support\PostsConfig;

return new class extends Migration
{
    public function up(): void
    {
        // Both sides of this pivot reference posts' own tables, so both track the inbound
        // primary key rather than the outbound author key.
        $primaryKeyType = KeyType::fromConfig('posts.primary_key_type');

        Schema::create(PostsConfig::categoryPostTable(), function (Blueprint $table) use ($primaryKeyType): void {
            $table->ownerKey('post_id', $primaryKeyType, nullable: false, index: true);
            $table->ownerKey('category_id', $primaryKeyType, nullable: false, index: true);
            $table->timestamps();

            $table->primary(['post_id', 'category_id']);
        });
    }
};
