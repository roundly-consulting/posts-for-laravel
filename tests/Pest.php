<?php

declare(strict_types=1);

use RoundlyConsulting\Posts\Tests\ConfiguredPostTestCase;
use RoundlyConsulting\Posts\Tests\TestCase;
use RoundlyConsulting\Posts\Tests\UlidAuthorTestCase;
use RoundlyConsulting\Posts\Tests\UlidPostTestCase;
use RoundlyConsulting\Posts\Tests\UuidAuthorTestCase;
use RoundlyConsulting\Posts\Tests\UuidPostTestCase;

uses(TestCase::class)->in(__DIR__.'/Unit', __DIR__.'/Feature');

// The OUTBOUND axis — the key type of the host's author models.
uses(UuidAuthorTestCase::class)->in(__DIR__.'/UuidAuthor');
uses(UlidAuthorTestCase::class)->in(__DIR__.'/UlidAuthor');
uses(ConfiguredPostTestCase::class)->in(__DIR__.'/Configured');

// The INBOUND axis — the key type of posts' own tables, which other packages' morph columns
// point at. Fixed at migrate time, so each non-default leg needs its own base case to reach
// the before-boot window. The bigint leg rides the default base case precisely because it
// must prove the *unconfigured* install is correct.
uses(TestCase::class)->in(__DIR__.'/PostKey/BigIntPostKeyTest.php');
uses(UuidPostTestCase::class)->in(__DIR__.'/PostKey/UuidPostKeyTest.php');
uses(UlidPostTestCase::class)->in(__DIR__.'/PostKey/UlidPostKeyTest.php');
