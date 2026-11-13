<?php

declare(strict_types=1);

namespace WebxUi\Widgets;

/**
 * Who of a page's blocks stands on its first screen: the first to ask for a kind gets it, once a
 * request (a scoped instance). A theme's hero asks for `hero`, and only the first hero of the page
 * prints its pictures with `fetchpriority="high"` — the largest paint of the screen is fetched
 * before the scripts and the pictures under it; a second hero waits its turn as any picture does.
 *
 * A template asks only when it prints something: an empty block leaves the first screen to the
 * next one.
 */
final class FirstScreen
{
    /** @var array<string, true> */
    private array $taken = [];

    public static function claim(string $kind): bool
    {
        return app(self::class)->take($kind);
    }

    public function take(string $kind): bool
    {
        if (isset($this->taken[$kind])) {
            return false;
        }

        $this->taken[$kind] = true;

        return true;
    }
}
