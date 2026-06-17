<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Tests\Support;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Posts\Concerns\HasPosts;

final class UuidAuthorTestModel extends Model
{
    use HasPosts;
    use HasUuids;

    public $table = 'uuid_users';

    public $timestamps = false;

    protected $guarded = [];
}
