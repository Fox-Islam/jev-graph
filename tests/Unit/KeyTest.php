<?php

declare(strict_types=1);

use Phox\ComposableJev\Presets;
use Phox\ComposableJevDemo\App;
use Phox\ComposableJevDemo\Key;
use Phox\TypeSafe\Enums\Provider;

it('reads the key and the provider the page sends', function (): void {
    $key = Key::fromHeaders(['HTTP_X_JEV_KEY' => ' sk-mine ', 'HTTP_X_JEV_PROVIDER' => 'OpenRouter']);

    expect([$key->provider, $key->value])->toBe([Provider::OpenRouter, 'sk-mine'])
        ->and(Key::fromHeaders([]))->toEqual(new Key(Provider::TypeSafe, null))
        ->and(fn () => Key::fromHeaders(['HTTP_X_JEV_PROVIDER' => 'elsewhere']))->toThrow(InvalidArgumentException::class, 'typesafe or openrouter');
});

it('keeps the key out of a dump', function (): void {
    ob_start();
    var_dump(new Key(Provider::TypeSafe, 'sk-secret'));

    expect(ob_get_clean())->not->toContain('sk-secret')->toContain('[redacted]');
});

it('asks for a key where neither the page nor the server has one', function (): void {
    $app = App::jev(['typesafe' => null, 'openrouter' => null], null);
    $body = ['experiment' => 'build', 'mode' => 'tick', 'graph' => Presets::get('ticket triage')->spec()];

    expect(fn () => $app->run($body, key: new Key(Provider::OpenRouter)))->toThrow(InvalidArgumentException::class, 'no API key for OpenRouter')
        ->and($app->run(['graph' => Presets::get('xor')->spec(), 'fake' => true] + $body)['fake'])->toBeTrue();
});

it('says which providers the server holds a key for', function (): void {
    $providers = App::jev(['typesafe' => 'ts-server', 'openrouter' => null], null)->catalog()['providers'];

    expect($providers)->toBe([
        'typesafe' => ['label' => 'TypeSafe', 'server' => true],
        'openrouter' => ['label' => 'OpenRouter', 'server' => false],
    ])->and(json_encode($providers))->not->toContain('ts-server');
});
