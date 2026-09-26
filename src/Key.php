<?php

declare(strict_types=1);

namespace Phox\ComposableJevDemo;

use Phox\TypeSafe\Enums\Provider;
use SensitiveParameter;

/**
 * The API key a run is paid with, and who Jev is reached through. It lives for one request: it
 * comes in a header, goes to the SDK's client, and is never logged, cached or sent back.
 */
final readonly class Key
{
    public function __construct(
        public Provider $provider = Provider::TypeSafe,
        #[SensitiveParameter]
        public ?string $value = null,
    ) {}

    public function __debugInfo(): array
    {
        return ['provider' => $this->provider->value, 'value' => $this->value === null ? null : '[redacted]'];
    }

    /**
     * From `X-Jev-Key` and `X-Jev-Provider`, which the page sends where you have typed a key.
     *
     * @param  array<string, mixed>  $server  as `$_SERVER`
     */
    public static function fromHeaders(array $server): self
    {
        $value = trim((string) ($server['HTTP_X_JEV_KEY'] ?? ''));
        $provider = Provider::tryFrom(mb_strtolower(trim((string) ($server['HTTP_X_JEV_PROVIDER'] ?? 'typesafe'))))
            ?? throw new BadRequest('provider: typesafe or openrouter');

        return new self($provider, $value === '' ? null : $value);
    }
}
