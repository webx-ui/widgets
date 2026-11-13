<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void need(string $widget)
 * @method static mixed later(\Closure $render)
 * @method static void hold(bool $when = true)
 * @method static void release()
 * @method static list<string> claimed()
 * @method static list<string> claimedLater()
 * @method static void form(string $slug, bool $sheet = false)
 * @method static list<string> forms()
 * @method static string finish(string $html)
 * @method static int sequence(string $name)
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
