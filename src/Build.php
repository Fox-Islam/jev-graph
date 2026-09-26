<?php

declare(strict_types=1);

namespace Phox\ComposableJevDemo;

use Phox\ComposableJev\Gates;
use Phox\ComposableJev\Node;

final class Build
{
    /**
     * The Gates tab's one node over `a`, or `a` and `b`: a gate, a weighted sum, or a question.
     *
     * @param  array<string, mixed>  $spec
     */
    public static function single(array $spec): Node
    {
        if (isset($spec['gate'])) {
            return Gates::builtIn()[$spec['gate']] ?? throw new BadRequest('pick one of ' . implode(', ', array_keys(Gates::templates())));
        }
        if (isset($spec['weights'])) {
            $weights = is_array($spec['weights']) ? array_map('floatval', array_values($spec['weights'])) : [];
            if ($weights === [] || count($weights) > 4) {
                throw new BadRequest('weights: one to four numbers');
            }

            return Gates::weighted($weights, (float) ($spec['bias'] ?? 0));
        }
        $inputs = array_values((array) ($spec['inputs'] ?? ['a']));
        if (! in_array($inputs, [['a'], ['a', 'b']], true)) {
            throw new BadRequest('inputs: a, or a and b');
        }
        if (! isset($spec['instructions'])) {
            throw new BadRequest('node: give a gate, weights, or a question');
        }

        return Gates::custom('custom', (string) $spec['instructions'], $inputs, (string) ($spec['yes'] ?? ''), (string) ($spec['no'] ?? ''));
    }
}
