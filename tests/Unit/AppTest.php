<?php

declare(strict_types=1);

use Phox\ComposableJev\Oracle\Simulator;
use Phox\ComposableJev\Presets;
use Phox\ComposableJevDemo\App;
use Tests\Fakes\FakeTypeSafe;

function app(?FakeTypeSafe $fake = null): App
{
    return new App(fn (bool $simulated) => $simulated || $fake === null ? new Simulator : $fake->jev());
}

it('offers every preset, with the 8-bit adder truth table off', function (): void {
    $graphs = app()->catalog()['graphs'];

    expect(array_keys($graphs))->toBe(['xor', 'half-adder', '4-bit adder', '8-bit adder', 'sr latch', 'ticket triage', 'delay timer', '555 oscillator', 'decay'])
        ->and($graphs['8-bit adder']['truthTable'])->toBeFalse()
        ->and($graphs['4-bit adder'])->not->toHaveKey('truthTable')
        ->and($graphs['4-bit adder']['steps'][0]['inputs']['a0'])->toBe(1);
});

it('runs each experiment in the simulator, right wherever there is a rule', function (array $body): void {
    $result = app()->run($body + ['fake' => true]);

    expect($result['rows'])->not->toBeEmpty();
    foreach ($result['rows'] as $row) {
        expect($row['want'] === null || ($row['p'] > 0.5) === $row['want'])->toBeTrue();
    }
})->with([
    'a gate' => [['experiment' => 'truth', 'node' => ['gate' => 'nand']]],
    'a weighted sum' => [['experiment' => 'truth', 'node' => ['weights' => [0.6, 0.6], 'bias' => 1]]],
    'a sweep' => [['experiment' => 'sweep', 'node' => ['gate' => 'and'], 'steps' => 5, 'b' => 1]],
    'a truth table' => [['experiment' => 'build', 'graph' => Presets::get('half-adder')->spec()]],
    'a tick' => [['experiment' => 'build', 'mode' => 'tick', 'graph' => Presets::adder(4)->spec(), 'inputs' => ['a0' => 1]]],
]);

it('refuses what it cannot run', function (array $body, string $error): void {
    expect(fn () => app()->run($body + ['fake' => true]))->toThrow(InvalidArgumentException::class, $error);
})->with([
    [['experiment' => 'nope'], 'unknown experiment'],
    [['experiment' => 'sweep', 'node' => ['gate' => 'and'], 'steps' => 1, 'b' => 1], 'steps: a number of at least 2'],
    [['experiment' => 'truth', 'node' => ['instructions' => 'Is a big?']], 'no rule, so only Jev'],
    [['experiment' => 'build', 'graph' => app()->catalog()['graphs']['8-bit adder']], 'truth table turned off'],
    [['experiment' => 'build', 'graph' => ['inputs' => ['a'], 'layers' => [[['name' => 'x', 'preset' => 'and', 'reads' => ['a', 'zz']]]]]], 'not an input or a node'],
    [['experiment' => 'build', 'graph' => ['inputs' => ['a'], 'texts' => ['t'], 'layers' => [[['name' => 'x', 'preset' => 'buffer', 'reads' => ['text']]]]]], 'only a question can read the context'],
    [['experiment' => 'build', 'graph' => ['inputs' => ['a'], 'layers' => [[['name' => 'x', 'preset' => 'custom', 'reads' => ['a'], 'instructions' => ' ']]]]], 'write a question'],
    [['experiment' => 'build', 'graph' => ['inputs' => [], 'layers' => [[['name' => 'x', 'preset' => 'buffer', 'reads' => ['a']]]]]], 'a context, or time'],
]);

it('emits each node as it is asked and answered, a level at a time', function (): void {
    $events = [];
    app()->run(['experiment' => 'build', 'fake' => true, 'mode' => 'tick', 'graph' => Presets::get('xor')->spec(), 'inputs' => ['a' => 1]],
        function (array $e) use (&$events): void {
            $events[] = [$e['event'], $e['node'] ?? null];
        });

    expect(array_count_values(array_map(fn ($e) => implode(':', $e), $events))['fired:xor'])->toBe(1)
        ->and(array_search(['fired', 'or'], $events, true))->toBeLessThan(array_search(['firing', 'xor'], $events, true))
        ->and(end($events))->toBe(['row', null]);
});

it('carries a latch through the signals the page sends back', function (): void {
    $spec = Presets::get('sr latch')->spec();
    $tick = fn (array $inputs, ?array $previous) => app()->run(['experiment' => 'build', 'fake' => true, 'mode' => 'tick',
        'graph' => $spec, 'inputs' => $inputs, 'previous' => $previous])['rows'][0];
    $first = $tick(['s' => 1], null);
    $second = $tick(['s' => 1], $first['inputs'] + $first['signals']);
    $held = $tick([], $second['inputs'] + $second['signals']);

    expect($held['signals']['q'])->toBeGreaterThan(0.5)->and($held['fires'])->toBe(2);
});

it('asks Jev in batches and counts what it spent', function (): void {
    $fake = FakeTypeSafe::byRule(Presets::adder(4));
    $result = app($fake)->run(['experiment' => 'build', 'mode' => 'tick', 'graph' => Presets::adder(4)->spec(),
        'inputs' => Presets::sums(4, [[7, 5]])[0]]);

    expect($result['calls'])->toBe(4)->and(count($fake->requests))->toBe(4)->and($result['fake'])->toBeFalse();
});
