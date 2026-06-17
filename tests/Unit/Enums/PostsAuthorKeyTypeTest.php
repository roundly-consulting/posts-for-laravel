<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Posts\Enums\PostsAuthorKeyType;
use RoundlyConsulting\Posts\Exceptions\InvalidAuthorKeyTypeException;

it('resolves the key type from config', function (): void {
    config()->set('posts.author.key-type', 'uuid');

    expect(PostsAuthorKeyType::fromConfig())->toBe(PostsAuthorKeyType::Uuid);
});

it('defaults to bigint when config is missing', function (): void {
    config()->set('posts.author.key-type', null);

    expect(PostsAuthorKeyType::fromConfig())->toBe(PostsAuthorKeyType::Bigint);
});

it('throws when the configured key type is invalid', function (): void {
    config()->set('posts.author.key-type', 'guid');

    PostsAuthorKeyType::fromConfig();
})->throws(InvalidAuthorKeyTypeException::class);

it('throws when the configured key type is not a string', function (): void {
    config()->set('posts.author.key-type', ['nope']);

    PostsAuthorKeyType::fromConfig();
})->throws(InvalidAuthorKeyTypeException::class);

it('builds a bigint column', function (): void {
    Schema::create('author_key_bigint', function (Blueprint $table): void {
        $table->increments('ref');
        PostsAuthorKeyType::Bigint->columnDefinition($table, 'author_id', true);
    });

    expect(Schema::getColumnType('author_key_bigint', 'author_id'))->toBe('integer');
});

it('builds a uuid column', function (): void {
    Schema::create('author_key_uuid', function (Blueprint $table): void {
        $table->increments('ref');
        PostsAuthorKeyType::Uuid->columnDefinition($table, 'author_id', false);
    });

    expect(Schema::hasColumn('author_key_uuid', 'author_id'))->toBeTrue();
});
