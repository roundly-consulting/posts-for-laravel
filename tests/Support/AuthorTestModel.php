<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Posts\Concerns\HasPosts;

final class AuthorTestModel extends Model
{
    use HasPosts;

    public $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}
