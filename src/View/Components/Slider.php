<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use stdClass;
use WebxUi\Widgets\Facades\Widgets;
use WebxUi\Widgets\View\Sliders;

/**
 * `<x-webx-slider variant="cards" :per-view="['sm' => 1.2, 'md' => 2, 'lg' => 3]">` with
 * `<x-webx-slide>` inside (spec §7): Swiper, with logic and accessibility done and a frame of
 * styles on the site's tokens.
 *
 * A variant is a set of settings and the modifier `webx-slider--<variant>`; every setting is a
 * prop over it, and `options` passes anything else to Swiper as it is. Per view is by the width
 * of the slider's container, not the window's: the breakpoints become container queries beside
 * the slider, which size the strip without JavaScript and which the script reads back.
 */
final class Slider extends Component
{
    /** Container widths the named breakpoints of `per-view` start at, in pixels. */
    public const array BREAKPOINTS = ['sm' => 0, 'md' => 640, 'lg' => 960, 'xl' => 1280];

    public const array EFFECTS = ['slide', 'fade'];

    /**
     * What each variant is (§7): `cards` a strip of cards, `hero` a slide to the width that fades
     * and turns on its own, `gallery` a big picture with thumbnails, `logos` a running strip
     * with no arrows.
     *
     * @var array<string, array{per-view: float|array<string, float>, arrows: bool, pagination: bool, loop: bool, autoplay: int, continuous: bool, effect: string, thumbs: bool, speed: int}>
     */
    public const array VARIANTS = [
        'cards' => ['per-view' => ['sm' => 1.2, 'md' => 2, 'lg' => 3], 'arrows' => true, 'pagination' => true, 'loop' => false, 'autoplay' => 0, 'continuous' => false, 'effect' => 'slide', 'thumbs' => false, 'speed' => 400],
        'hero' => ['per-view' => 1, 'arrows' => true, 'pagination' => true, 'loop' => true, 'autoplay' => 6000, 'continuous' => false, 'effect' => 'fade', 'thumbs' => false, 'speed' => 600],
        'gallery' => ['per-view' => 1, 'arrows' => true, 'pagination' => false, 'loop' => false, 'autoplay' => 0, 'continuous' => false, 'effect' => 'slide', 'thumbs' => true, 'speed' => 400],
        // Continuous: no stop between slides, one slide per `speed` milliseconds.
        'logos' => ['per-view' => ['sm' => 2.5, 'md' => 4, 'lg' => 6], 'arrows' => false, 'pagination' => false, 'loop' => true, 'autoplay' => 0, 'continuous' => true, 'effect' => 'slide', 'thumbs' => false, 'speed' => 4000],
    ];

    /** @var list<array{0: int, 1: float}> Per view from each container width up, narrowest first. */
    public array $views;

    public bool $hasArrows;

    public bool $hasPagination;

    public bool $hasPause;

    /** @var array<string, mixed> What the script is told, `data-webx-slider`. */
    public array $config;

    /**
     * @param  float|int|string|array<int|string, float|int|string>|null  $perView  one number, or by breakpoint: a name of {@see BREAKPOINTS} or a width in pixels
     * @param  bool|int|string|null  $autoplay  milliseconds between slides; true — the variant's, or 5000; false or 0 — off
     * @param  array<string, mixed>  $options  Swiper's own settings, over everything
     */
    public function __construct(
        public string $variant = 'cards',
        float|int|string|array|null $perView = null,
        ?bool $arrows = null,
        ?bool $pagination = null,
        ?bool $loop = null,
        bool|int|string|null $autoplay = null,
        ?bool $continuous = null,
        ?string $effect = null,
        ?bool $thumbs = null,
        ?int $speed = null,
        array $options = [],
        public ?string $label = null,
        public ?string $id = null,
    ) {
        $preset = self::VARIANTS[$variant] ?? throw new InvalidArgumentException('A slider is one of: '.implode(', ', array_keys(self::VARIANTS)).", not \"{$variant}\".");

        // It is written into the slider's <style>, so it may be nothing but an id.
        if ($id !== null && preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $id) !== 1) {
            throw new InvalidArgumentException("<x-webx-slider id>: letters, digits, - and _, starting with a letter, not \"{$id}\".");
        }

        $effect ??= $preset['effect'];

        if (! in_array($effect, self::EFFECTS, true)) {
            throw new InvalidArgumentException('<x-webx-slider effect>: one of '.implode(', ', self::EFFECTS).", not \"{$effect}\".");
        }

        // A fade shows one slide at a time, whatever the width.
        $this->views = $effect === 'fade' ? [[0, 1.0]] : self::views($perView ?? $preset['per-view']);
        $continuous ??= $preset['continuous'];
        $delay = self::delay($autoplay, $preset['autoplay']);

        $this->hasArrows = $arrows ?? $preset['arrows'];
        $this->hasPagination = $pagination ?? $preset['pagination'];
        // Anything that moves on its own can be stopped (WCAG 2.2.2).
        $this->hasPause = $delay > 0 || $continuous;

        $this->config = [
            'variant' => $variant,
            'effect' => $effect,
            'loop' => $loop ?? $preset['loop'],
            'autoplay' => $delay,
            'continuous' => $continuous,
            'thumbs' => $thumbs ?? $preset['thumbs'],
            'speed' => $speed ?? $preset['speed'],
            'options' => $options === [] ? new stdClass : $options,
        ];
    }

    public function render(): View
    {
        Widgets::need('slider');

        // The widest view decides how many slides are on the screen when the page opens.
        app(Sliders::class)->open((int) ceil(max(array_column($this->views, 1))));

        return view('webx-widgets::components.slider', [
            'words' => self::scriptWords(),
        ]);
    }

    /**
     * The script's words, Swiper's placeholders in place of the dictionary's.
     *
     * @return array<string, string>
     */
    private static function scriptWords(): array
    {
        $words = [];

        foreach (['slide', 'prev', 'next', 'first', 'last', 'go_to', 'position', 'pause', 'play', 'thumbs', 'thumb'] as $key) {
            $words[$key] = (string) __("webx-widgets::widgets.slider.{$key}");
        }

        return $words;
    }

    /**
     * @param  float|int|string|array<int|string, float|int|string>  $perView
     * @return list<array{0: int, 1: float}>
     */
    private static function views(float|int|string|array $perView): array
    {
        $views = [];

        foreach (is_array($perView) ? $perView : ['sm' => $perView] as $from => $count) {
            $width = is_int($from) ? $from : (self::BREAKPOINTS[$from] ?? (ctype_digit($from) ? (int) $from : null));

            if ($width === null) {
                throw new InvalidArgumentException('<x-webx-slider :per-view>: a breakpoint is one of '.implode(', ', array_keys(self::BREAKPOINTS))." or a width in pixels, not \"{$from}\".");
            }

            if (! is_numeric($count) || (float) $count <= 0) {
                throw new InvalidArgumentException("<x-webx-slider :per-view>: slides per view is a number above 0, not \"{$count}\".");
            }

            $views[$width] = (float) $count;
        }

        ksort($views);

        // The narrowest breakpoint given holds from no width at all.
        $narrowest = array_key_first($views);

        if ($narrowest !== 0) {
            $count = $views[$narrowest];
            unset($views[$narrowest]);
            $views = [0 => $count] + $views;
        }

        return array_map(null, array_keys($views), array_values($views));
    }

    private static function delay(bool|int|string|null $autoplay, int $preset): int
    {
        return match (true) {
            $autoplay === null => $preset,
            $autoplay === true, $autoplay === 'true', $autoplay === '' => $preset > 0 ? $preset : 5000,
            $autoplay === false, $autoplay === 'false' => 0,
            is_int($autoplay), ctype_digit($autoplay) => (int) $autoplay,
            default => throw new InvalidArgumentException("<x-webx-slider autoplay>: milliseconds, true or false, not \"{$autoplay}\"."),
        };
    }
}
