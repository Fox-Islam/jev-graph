<?php

declare(strict_types=1);

namespace Phox\ComposableJevDemo;

use Closure;

/**
 * Settings from the environment, falling back to a `.env` file.
 */
final class Env
{
    /**
     * @return Closure(string): ?string a setting, or null where it is unset or empty
     */
    public static function load(string $root): Closure
    {
        $file = [];
        if (is_file($root . '/.env')) {
            foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                [$key, $value] = array_pad(explode('=', trim($line), 2), 2, null);
                if ($value !== null && $key !== '' && ! str_starts_with($key, '#')) {
                    $file[$key] = trim(trim($value), '"\'');
                }
            }
        }

        return function (string $name) use ($file): ?string {
            $value = getenv($name);

            return is_string($value) && $value !== '' ? $value : ($file[$name] ?? null ?: null);
        };
    }
}
