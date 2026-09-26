<?php

declare(strict_types=1);

namespace Phox\ComposableJevDemo;

use Closure;
use Phox\ComposableJev\Answer;
use Phox\ComposableJev\Node;
use Phox\ComposableJev\Oracle\Oracle;

/**
 * An oracle that stops answering once `$stopped` returns true, so a run the caller has left ends at
 * its next question instead of running on at the caller's cost.
 */
final readonly class Stoppable implements Oracle
{
    /**
     * @param  Closure(): bool  $stopped
     */
    public function __construct(private Oracle $inner, private Closure $stopped) {}

    public function fire(Node $node, array $state): float|Answer
    {
        $this->check();

        return $this->inner->fire($node, $state);
    }

    public function fireMany(array $items): array
    {
        $this->check();

        return $this->inner->fireMany($items);
    }

    public function batching(): bool
    {
        return $this->inner->batching();
    }

    private function check(): void
    {
        if (($this->stopped)()) {
            throw new Stopped('the run was stopped');
        }
    }
}
