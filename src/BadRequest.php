<?php

declare(strict_types=1);

namespace Phox\ComposableJevDemo;

use InvalidArgumentException;

/**
 * A request the server cannot run, with what to change.
 */
final class BadRequest extends InvalidArgumentException {}
