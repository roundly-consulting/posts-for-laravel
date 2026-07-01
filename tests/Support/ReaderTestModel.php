<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Tests\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * A minimal host actor used as an explicit liker / reporter / viewer in the
 * likes + reports integration tests. Stored in the `users` table.
 */
final class ReaderTestModel extends Model
{
    public $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}
