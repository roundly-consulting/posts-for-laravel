<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Tests\TestCase;
use RoundlyConsulting\Posts\Tests\UuidAuthorTestCase;

uses(TestCase::class)->in(__DIR__.'/Unit', __DIR__.'/Feature');
uses(UuidAuthorTestCase::class)->in(__DIR__.'/UuidAuthor');
