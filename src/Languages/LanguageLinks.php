<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Languages;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\URL;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Models\Locale;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Resolution;
use WebxUi\Routing\SiteUrl;
use WebxUi\Routing\UrlNormaliser;

/**
 * The page being served in each of the site's languages (spec §13), for the switcher.
 *
 * The addresses come from where `hreflang` takes them (module-seo's `Alternates`, ROUTING §8):
 * a page the registry found is its entity's canonical row in each language; an address outside
 * the registry — a module's own route, the blog feed — is the same path under each language's
 * prefix. Where the entity has no row, or is not shown in that language, the link leads to that
 * language's home page and says so (`fallback`): a visitor who chose Polish should land on a
 * Polish page, not on a 404. Unlike `hreflang`, a page closed to the index still switches: the
 * reader is not a search engine.
 *
 * Nothing on a site with one language, without `webx-ui/routing` and `webx-ui/localization`
 * (the package requires neither), or where the language is not in the path: with `header` every
 * language has the same address, and a link cannot choose one.
 */
final class LanguageLinks
{
    /** @return list<LanguageLink> In the order the site lists its languages, the current one marked. */
    public static function current(): array
    {
        if (! class_exists(Locales::class) || ! class_exists(SiteUrl::class) || ! app()->bound(Locales::class)) {
            return [];
        }

        $request = app()->bound('request') ? app('request') : null;

        return $request instanceof Request ? app(self::class)->for($request) : [];
    }

    public function __construct(
        private readonly Locales $locales,
        private readonly SiteUrl $site,
        private readonly Config $config,
    ) {}

    /** @return list<LanguageLink> */
    public function for(Request $request): array
    {
        $codes = $this->locales->codes();

        if (count($codes) < 2 || (string) $this->config->get('webx-localization.strategy', 'prefix') !== 'prefix') {
            return [];
        }

        $current = $this->locales->current();
        $paths = $this->paths($request, $codes);
        $links = [];

        foreach ($codes as $code) {
            $path = $paths[$code] ?? null;

            $links[] = new LanguageLink(
                code: $code,
                name: $this->name($code),
                url: $code === $current && $path === null ? $request->url() : URL::to($path ?? $this->site->prefix($code)),
                current: $code === $current,
                fallback: $code !== $current && $path === null,
            );
        }

        return $links;
    }

    /**
     * Language → path of this page in it; a language missing has no translation of it.
     *
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function paths(Request $request, array $codes): array
    {
        $entity = Resolution::of($request)?->entity;

        if ($entity instanceof Model) {
            return $this->entityPaths($entity);
        }

        // The registry's own route answered and found nothing: a 404 has no other language.
        $route = $request->route();

        if (! $route instanceof LaravelRoute || $route->isFallback) {
            return [];
        }

        return $this->routePaths($request->path(), $codes);
    }

    /** @return array<string, string> */
    private function entityPaths(Model $entity): array
    {
        $paths = [];

        foreach (Route::query()->forEntity($entity)->canonical()->get() as $row) {
            if (! $this->locales->has($row->locale)) {
                continue;
            }

            if ($entity instanceof Visible && ! $entity->isVisible($row->locale)) {
                continue;
            }

            $paths[$row->locale] = UrlNormaliser::join($this->site->prefix($row->locale), $row->path);
        }

        return $paths;
    }

    /**
     * The path with its language prefix taken off and every language's put back — what
     * `hreflang` does for a route the registry does not hold.
     *
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function routePaths(string $path, array $codes): array
    {
        $segments = explode('/', trim($path, '/'), 2);
        $lower = array_map(static fn (string $code): string => mb_strtolower($code, 'UTF-8'), $codes);
        $rest = in_array(UrlNormaliser::key($segments[0]), $lower, true) ? ($segments[1] ?? '') : trim($path, '/');
        $paths = [];

        foreach ($codes as $code) {
            $paths[$code] = UrlNormaliser::join($this->site->prefix($code), $rest);
        }

        return $paths;
    }

    /** What the language calls itself: "Deutsch", "Polski" — the name a reader of it looks for. */
    private function name(string $code): string
    {
        $locale = $this->locales->find($code) ?? Locale::fromCode($code);

        return $locale->native_name !== '' ? $locale->native_name : ($locale->name !== '' ? $locale->name : strtoupper($code));
    }
}
