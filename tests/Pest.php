<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Tests\ConfiguredPostTestCase;
use RoundlyConsulting\Posts\Tests\TestCase;
use RoundlyConsulting\Posts\Tests\UlidAuthorTestCase;
use RoundlyConsulting\Posts\Tests\UuidAuthorTestCase;

uses(TestCase::class)->in(__DIR__.'/Unit', __DIR__.'/Feature');
uses(UuidAuthorTestCase::class)->in(__DIR__.'/UuidAuthor');
uses(UlidAuthorTestCase::class)->in(__DIR__.'/UlidAuthor');
uses(ConfiguredPostTestCase::class)->in(__DIR__.'/Configured');
