<?php

declare(strict_types=1);

use Phox\ComposableJevDemo\Http;

require __DIR__ . '/../vendor/autoload.php';

Http::fromEnvironment(dirname(__DIR__))->handle();
