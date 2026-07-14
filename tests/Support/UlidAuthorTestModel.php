<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Tests\Support;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Posts\Concerns\HasPosts;

final class UlidAuthorTestModel extends Model
{
    use HasPosts;
    use HasUlids;

    public $table = 'ulid_users';

    public $timestamps = false;

    protected $guarded = [];
}
