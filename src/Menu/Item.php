<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Menu;

use Illuminate\Support\Arr;

/**
 * One item of a navigation the header and the mobile menu draw (spec §6.1, §6.2).
 *
 * The widgets are a library: they take `menu('header')` from `module-menu` as it comes, but
 * cannot require that module, and a theme without it hands them plain arrays — the published
 * pages, say. So both shapes are read here, by what they carry rather than by their class:
 *
 *     a MenuLink        label, url, children, attrs(), isCurrent(), isActive(), variant
 *     an array          ['label' => …, 'url' => … or 'attrs' => [...], 'children' => [...],
 *                        'current' => bool, 'variant' => …]
 *
 * An array without `current` is current when its address is the page being rendered.
 */
final readonly class Item
{
    /**
     * @param  array<string, string>  $attrs  `href`, `target`, `rel` — whatever the link prints.
     * @param  list<Item>  $children
     */
    public function __construct(
        public string $label,
        public array $attrs,
        public array $children,
        public bool $current,
        public bool $active,
        public string $variant = 'link',
    ) {}

    /**
     * @param  iterable<mixed>|null  $items
     * @return list<Item>
     */
    public static function list(?iterable $items): array
    {
        $list = [];

        foreach ($items ?? [] as $item) {
            if (($read = self::from($item)) !== null) {
                $list[] = $read;
            }
        }

        return $list;
    }

    public static function from(mixed $item): ?self
    {
        if ($item instanceof self) {
            return $item;
        }

        if (is_object($item) && method_exists($item, 'attrs') && property_exists($item, 'label')) {
            $children = self::list(is_iterable($item->children ?? null) ? $item->children : []);

            return new self(
                label: (string) $item->label,
                attrs: self::strings($item->attrs()),
                children: $children,
                current: method_exists($item, 'isCurrent') && (bool) $item->isCurrent(),
                active: method_exists($item, 'isActive') ? (bool) $item->isActive() : self::anyActive($children),
                variant: is_string($item->variant ?? null) ? $item->variant : 'link',
            );
        }

        if (! is_array($item) || ! is_string($item['label'] ?? null)) {
            return null;
        }

        $attrs = self::strings(is_array($item['attrs'] ?? null) ? $item['attrs'] : ['href' => $item['url'] ?? null]);
        $children = self::list(is_iterable($item['children'] ?? null) ? $item['children'] : []);
        $current = array_key_exists('current', $item) ? (bool) $item['current'] : self::here($attrs['href'] ?? null);

        return new self(
            label: $item['label'],
            attrs: $attrs,
            children: $children,
            current: $current,
            active: $current || (bool) ($item['active'] ?? false) || self::anyActive($children),
            variant: is_string($item['variant'] ?? null) ? $item['variant'] : 'link',
        );
    }

    public function href(): ?string
    {
        return $this->attrs['href'] ?? null;
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /**
     * @param  list<Item>  $items
     */
    private static function anyActive(array $items): bool
    {
        foreach ($items as $item) {
            if ($item->active) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private static function strings(mixed $attrs): array
    {
        $strings = [];

        foreach (is_array($attrs) ? $attrs : [] as $name => $value) {
            if (is_string($name) && (is_string($value) || is_int($value)) && (string) $value !== '') {
                $strings[$name] = (string) $value;
            }
        }

        return $strings;
    }

    /** The address is the page being rendered: path against path, the host left out. */
    private static function here(?string $href): bool
    {
        if ($href === null || ! app()->bound('request')) {
            return false;
        }

        $path = parse_url($href, PHP_URL_PATH);
        $host = parse_url($href, PHP_URL_HOST);
        $request = request();

        if (is_string($host) && $host !== $request->getHost()) {
            return false;
        }

        return '/'.trim(is_string($path) ? $path : '', '/') === '/'.trim($request->path(), '/');
    }

    /**
     * The item's attributes as HTML, `aria-current` included where it is the page.
     *
     * @param  array<string, string>  $extra
     */
    public function attributes(array $extra = []): string
    {
        $attrs = Arr::except($this->attrs, ['class']);

        if ($this->current) {
            $attrs['aria-current'] = 'page';
        }

        $html = [];

        foreach ([...$attrs, ...$extra] as $name => $value) {
            $html[] = e($name).'="'.e($value).'"';
        }

        return implode(' ', $html);
    }
}
