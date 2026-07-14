<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Tests\Support;

use Illuminate\Database\Eloquent\Model;

/** A real Eloquent model that is NOT a Post — the resolver must not hand it back. */
final class NotAPost extends Model
{
    protected $guarded = [];
}
