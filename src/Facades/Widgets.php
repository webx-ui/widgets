<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void need(string $widget)
 * @method static list<string> claimed()
 * @method static void form(string $slug)
 * @method static list<string> forms()
 * @method static string finish(string $html)
 *
 * @see \WebxUi\Widgets\Widgets
 */
final class Widgets extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \WebxUi\Widgets\Widgets::class;
    }
}
