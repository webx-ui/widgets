<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool has(string $category)
 * @method static bool enabled()
 * @method static bool answered()
 * @method static array{v: string, d: string, c: list<string>}|null answer()
 * @method static string version()
 * @method static bool gpc()
 * @method static string|null policy()
 *
 * @see \WebxUi\Widgets\Consent
 */
final class Consent extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \WebxUi\Widgets\Consent::class;
    }
}
