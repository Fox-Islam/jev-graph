<?php

declare(strict_types=1);

namespace Phox\ComposableJevDemo;

use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * The page's server, behind PHP's built-in server or any web server that sends every path to
 * `public/index.php`. A run is paid with the key the page sends, or the server's own, so it is
 * refused unless it comes as JSON to an allowed host. Asking for JSON forces a CORS preflight
 * this server never approves, so another site cannot post here; checking the host stops DNS
 * rebinding.
 */
final readonly class Http
{
    /** Where a run may be addressed without COMPOSABLE_JEV_HOSTS; the README states it */
    public const string HOSTS = '127.0.0.1,localhost';

    /** Where answers are cached without COMPOSABLE_JEV_CACHE, below the project root; the README states it */
    public const string CACHE = '.jev-cache';

    /**
     * @param  list<string>  $hosts  hosts a run may be addressed to
     */
    public function __construct(
        private App $app,
        private string $page,
        private array $hosts,
    ) {}

    /**
     * The settings in the README's table, from the environment or a `.env` in `$root`.
     */
    public static function fromEnvironment(string $root): self
    {
        $env = Env::load($root);
        $hosts = array_filter(array_map('trim', explode(',', $env('COMPOSABLE_JEV_HOSTS') ?? self::HOSTS)));

        return new self(
            App::jev(
                ['typesafe' => $env('TYPESAFE_API_KEY'), 'openrouter' => $env('OPENROUTER_API_KEY')],
                $env('COMPOSABLE_JEV_CACHE') ?? $root . '/' . self::CACHE,
                ($env('COMPOSABLE_JEV_BATCH') ?? '1') !== '0',
            ),
            $root . '/resources/page.html',
            array_values($hosts),
        );
    }

    /**
     * Why a request is refused, or null.
     *
     * @param  array<string, mixed>  $server  as `$_SERVER`
     * @param  list<string>  $hosts
     * @return array{int, string}|null
     */
    public static function refusal(array $server, string $path, array $hosts): ?array
    {
        if ($path !== '/api/run') {
            return null;
        }
        $host = mb_strtolower(explode(':', (string) ($server['HTTP_HOST'] ?? ''))[0]);
        if (! str_starts_with((string) ($server['CONTENT_TYPE'] ?? ''), 'application/json') || ! in_array($host, $hosts, true)) {
            return [403, 'send JSON from the page this server serves'];
        }

        return null;
    }

    /**
     * The path below wherever `index.php` is served from. PHP's built-in server, running it as a
     * router, sets SCRIPT_NAME to the path asked for, so only an `index.php` there is a base.
     *
     * @param  array<string, mixed>  $server
     */
    public static function path(array $server): string
    {
        $path = (string) parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $script = str_replace('\\', '/', (string) ($server['SCRIPT_NAME'] ?? ''));
        $base = str_ends_with($script, '/index.php') ? rtrim(dirname($script), '/') : '';

        return $base !== '' && str_starts_with($path, $base) ? (mb_substr($path, mb_strlen($base)) ?: '/') : $path;
    }

    public function handle(): void
    {
        $path = self::path($_SERVER);
        $refusal = self::refusal($_SERVER, $path, $this->hosts);
        if ($refusal !== null) {
            self::json($refusal[0], ['error' => $refusal[1]]);

            return;
        }
        match ([$_SERVER['REQUEST_METHOD'] ?? 'GET', $path]) {
            ['GET', '/'], ['GET', '/index.html'] => self::send(200, 'text/html; charset=utf-8', (string) file_get_contents($this->page)),
            ['GET', '/api/catalog'] => self::json(200, $this->app->catalog()),
            ['POST', '/api/run'] => $this->run(),
            default => self::json(404, ['error' => 'not found']),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function json(int $status, array $data): void
    {
        self::send($status, 'application/json', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private static function send(int $status, string $type, string $body): void
    {
        http_response_code($status);
        header("Content-Type: {$type}");
        echo $body;
    }

    private function run(): void
    {
        try {
            $body = json_decode((string) file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            self::json(400, ['error' => 'the body is not JSON']);

            return;
        }
        $body = is_array($body) ? $body : [];
        if (($body['experiment'] ?? null) === 'build') {
            $this->stream($body);

            return;
        }
        try {
            self::json(200, $this->app->run($body, key: Key::fromHeaders($_SERVER)));
        } catch (InvalidArgumentException $e) {
            self::json(400, ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            self::json(502, ['error' => $e::class . ': ' . $e->getMessage()]);
        }
    }

    /**
     * A built graph's run, one JSON event a line, as it happens. nginx buffers a response
     * unless told not to, and a page that has gone is noticed at the next write.
     *
     * @param  array<string, mixed>  $body
     */
    private function stream(array $body): void
    {
        header('Content-Type: application/x-ndjson');
        header('Cache-Control: no-store');
        header('X-Accel-Buffering: no');
        ini_set('zlib.output_compression', '0');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        set_time_limit(0);
        ignore_user_abort(true);
        $emit = function (array $event): void {
            echo json_encode($event, JSON_THROW_ON_ERROR) . "\n";
            flush();
        };
        try {
            $result = $this->app->run($body, $emit, fn () => connection_aborted() === 1, Key::fromHeaders($_SERVER));
            $emit(['event' => 'done', 'calls' => $result['calls'], 'cached' => $result['cached'], 'fake' => $result['fake']]);
        } catch (Stopped) {
            return;
        } catch (InvalidArgumentException $e) {
            $emit(['event' => 'error', 'error' => $e->getMessage()]);
        } catch (Throwable $e) {
            $emit(['event' => 'error', 'error' => $e::class . ': ' . $e->getMessage()]);
        }
    }
}
