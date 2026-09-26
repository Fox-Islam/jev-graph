<?php

declare(strict_types=1);

use Phox\ComposableJevDemo\Http;

$run = ['HTTP_HOST' => '127.0.0.1:8765', 'CONTENT_TYPE' => 'application/json'];
$hosts = ['127.0.0.1', 'localhost'];

it('lets a JSON run through to an allowed host', function () use ($run, $hosts): void {
    expect(Http::refusal($run, '/api/run', $hosts))->toBeNull()
        ->and(Http::refusal(['HTTP_HOST' => 'evil.test'], '/', $hosts))->toBeNull();
});

it('refuses a run another site could send', function (array $server) use ($hosts): void {
    expect(Http::refusal($server, '/api/run', $hosts))->toBe([403, 'send JSON from the page this server serves']);
})->with([
    'a form post' => [['HTTP_HOST' => '127.0.0.1', 'CONTENT_TYPE' => 'text/plain']],
    'a rebound host' => [['HTTP_HOST' => 'evil.test', 'CONTENT_TYPE' => 'application/json']],
]);

it('routes below the folder index.php is served from', function (array $server, string $path): void {
    expect(Http::path($server))->toBe($path);
})->with([
    'the built-in server' => [['REQUEST_URI' => '/api/catalog', 'SCRIPT_NAME' => '/api/catalog'], '/api/catalog'],
    'a site root' => [['REQUEST_URI' => '/api/run?x=1', 'SCRIPT_NAME' => '/index.php'], '/api/run'],
    'a folder' => [['REQUEST_URI' => '/jev/api/run', 'SCRIPT_NAME' => '/jev/index.php'], '/api/run'],
    'the folder itself' => [['REQUEST_URI' => '/jev/', 'SCRIPT_NAME' => '/jev/index.php'], '/'],
]);

it('states in the README the defaults it runs with', function (): void {
    $readme = (string) file_get_contents(dirname(__DIR__, 2) . '/README.md');

    expect($readme)->toContain('`' . Http::HOSTS . '` when unset')
        ->and($readme)->toContain('`' . Http::CACHE . '` when unset');
});
