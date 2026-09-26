<?php

declare(strict_types=1);

namespace Phox\ComposableJevDemo;

use RuntimeException;

/**
 * The caller of a run has gone, so its remaining questions are not asked.
 */
final class Stopped extends RuntimeException {}
