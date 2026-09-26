<?php

declare(strict_types=1);

namespace Phox\ComposableJevDemo;

use Closure;
use Phox\ComposableJev\Gates;
use Phox\ComposableJev\Graph;
use Phox\ComposableJev\Node;
use Phox\ComposableJev\Oracle\Jev;
use Phox\ComposableJev\Oracle\Oracle;
use Phox\ComposableJev\Oracle\Simulator;
use Phox\ComposableJev\Presets;
use Phox\ComposableJev\Row;
use Phox\ComposableJev\Rules;
use Phox\TypeSafe\Client;
use Phox\TypeSafe\Enums\Provider;

/**
 * What the page asks for: the catalog, and runs. A built graph's run hands each node to `$emit`
 * as it is asked and answered, and each row as it finishes.
 */
final readonly class App
{
    /**
     * @param  Closure(bool, Key): Oracle  $oracles  the oracle for a run, given whether it is simulated and the key it is paid with
     * @param  array<string, bool>  $held  provider => whether the server holds a key of its own for it
     */
    public function __construct(private Closure $oracles, private array $held = []) {}

    /**
     * Jev, paid with the key the page sends, or else the server's own for that provider.
     *
     * @param  array<string, string|null>  $keys  provider => the server's key, if it has one
     */
    public static function jev(array $keys, ?string $cacheDir, bool $batch = true): self
    {
        return new self(function (bool $simulated, Key $key) use ($keys, $cacheDir, $batch): Oracle {
            if ($simulated) {
                return new Simulator;
            }
            $value = $key->value ?? $keys[$key->provider->value] ?? throw new BadRequest(
                'no API key for ' . self::label($key->provider) . ': put yours in beside the Jev button, or use Simulated mode',
            );

            return new Jev(Client::make($value)->provider($key->provider), $batch, $cacheDir);
        }, array_map(fn (?string $k) => $k !== null && $k !== '', $keys));
    }

    public static function label(Provider $provider): string
    {
        return $provider === Provider::OpenRouter ? 'OpenRouter' : 'TypeSafe';
    }

    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        return [
            'gates' => array_map(fn (Node $n) => $n->describe(), Gates::builtIn()),
            'templates' => Gates::templates(),
            'rules' => Rules::templates(),
            'graphs' => self::graphs(),
            'providers' => array_combine(
                array_map(fn (Provider $p) => $p->value, Provider::cases()),
                array_map(fn (Provider $p) => ['label' => self::label($p), 'server' => $this->held[$p->value] ?? false], Provider::cases()),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  (Closure(array<string, mixed>): void)|null  $emit
     * @param  (Closure(): bool)|null  $stopped
     * @return array<string, mixed>
     */
    public function run(array $body, ?Closure $emit = null, ?Closure $stopped = null, Key $key = new Key): array
    {
        $simulated = (bool) ($body['fake'] ?? false);
        $inner = ($this->oracles)($simulated, $key);
        $oracle = $stopped === null ? $inner : new Stoppable($inner, $stopped);
        $result = match ($body['experiment'] ?? null) {
            'build' => $this->build($body, $oracle, $emit ?? fn () => null),
            'truth', 'sweep' => $this->single($body, $oracle),
            default => throw new BadRequest('unknown experiment ' . json_encode($body['experiment'] ?? null)),
        };

        return $result + ['calls' => $inner->calls ?? 0, 'cached' => $inner->cached ?? 0, 'fake' => $simulated];
    }

    /**
     * The presets as the page edits them: each spec with the inputs the next tick starts with, and
     * whether its truth table is off.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function graphs(): array
    {
        $graphs = [];
        foreach (Presets::names() as $name) {
            $starts = Presets::starts($name);
            $graphs[$name] = Presets::get($name)->spec()
                + ($starts === [] ? [] : ['steps' => [['inputs' => $starts]]])
                + (Presets::truthTable($name) ? [] : ['truthTable' => false]);
        }

        return $graphs;
    }

    /**
     * @param  list<string>  $reads
     * @return list<array<string, float>>
     */
    private static function combinations(array $reads): array
    {
        $count = count($reads);

        return array_map(
            fn (int $i) => array_combine($reads, array_map(fn (int $j) => (float) ($i >> ($count - 1 - $j) & 1), array_keys($reads))),
            range(0, 2 ** $count - 1),
        );
    }

    /**
     * Input `a` from 0 to 1 in `$steps` points, with any other input held at `$b`.
     *
     * @param  list<string>  $reads
     * @return list<array<string, float>>
     */
    private static function sweep(array $reads, int $steps, float $b): array
    {
        return array_map(
            fn (int $i) => array_combine($reads, array_map(fn (string $r) => $r === 'a' ? round($i / ($steps - 1), 4) : $b, $reads)),
            range(0, $steps - 1),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function signals(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (! is_array($value)) {
            throw new BadRequest('previous: the signals a tick ended on');
        }

        return array_filter($value, fn ($v) => $v === null || is_int($v) || is_float($v) || is_string($v));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function number(array $body, string $key, float $low, float $high): float
    {
        $value = $body[$key] ?? null;
        if (! is_numeric($value) || $value < $low || $value > $high) {
            throw new BadRequest(is_infinite($high) ? "{$key}: a number of at least {$low}" : "{$key}: a number from {$low} to {$high}");
        }

        return (float) $value;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  Closure(array<string, mixed>): void  $emit
     * @return array{rows: list<array<string, mixed>>}
     */
    private function build(array $body, Oracle $oracle, Closure $emit): array
    {
        $graph = Graph::fromSpec(is_array($body['graph'] ?? null) ? $body['graph'] : []);
        $onRow = fn (int $i, Row $row) => $emit(['event' => 'row', 'row' => $i] + $row->toArray());
        $onFire = fn (int $i, string $kind, string $node, mixed $p) => $emit(['event' => $kind, 'row' => $i, 'node' => $node, 'p' => $p]);
        if (($body['mode'] ?? null) === 'tick') {
            $values = $graph->values(
                is_array($body['inputs'] ?? null) ? $body['inputs'] : [],
                (int) ($body['context'] ?? $body['text'] ?? 0),
                $graph->hasTime() ? self::number($body, 'time', 0, INF) : 0,
            );
            $row = $graph->tick($oracle, $values, self::signals($body['previous'] ?? null), self::signals($body['previousIdeal'] ?? null),
                fn (string $kind, string $node, mixed $p) => $onFire(0, $kind, $node, $p));
            $onRow(0, $row);
            $rows = [$row];
        } elseif (($body['graph']['truthTable'] ?? true) === false) {
            throw new BadRequest('this network has its truth table turned off');
        } else {
            $rows = $graph->truthTable($oracle, $onRow, $onFire);
        }

        return ['rows' => array_map(fn (Row $r) => $r->toArray(), $rows)];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function single(array $body, Oracle $oracle): array
    {
        $node = Build::single(is_array($body['node'] ?? null) ? $body['node'] : []);
        $states = $body['experiment'] === 'truth'
            ? self::combinations($node->reads)
            : self::sweep($node->reads, (int) self::number($body, 'steps', 2, INF), (float) self::number($body, 'b', 0, 1));
        $rows = array_map(function (array $state) use ($node, $oracle): array {
            $p = $oracle->fire($node, $state);

            return ['inputs' => $state, 'p' => $p, 'want' => $node->expected($state), 'signals' => ['out' => $p]];
        }, $states);

        return [
            'rows' => $rows,
            'question' => $node->describe(),
            'graph' => ['inputs' => $node->reads, 'output' => 'out', 'nodes' => [
                ['name' => 'out', 'gate' => $node->name, 'instructions' => $node->instructions, 'wiring' => array_combine($node->reads, $node->reads)],
            ]],
        ];
    }
}
