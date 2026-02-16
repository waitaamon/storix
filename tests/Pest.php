<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Storix\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
