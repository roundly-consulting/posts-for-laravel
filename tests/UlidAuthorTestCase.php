<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Tests;

abstract class UlidAuthorTestCase extends TestCase
{
    protected function authorKeyType(): string
    {
        return 'ulid';
    }
}
