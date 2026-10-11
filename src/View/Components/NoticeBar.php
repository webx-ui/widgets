<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-notice-bar />` — an announcement bar above the header, with a close button that is
 * remembered (spec §14): "Open on Saturdays from November", "Free delivery until Sunday".
 *
 * What it says is the first banner of the place `place` (`notice` by default) of
 * `webx-ui/module-banners` when the site has the module and the place has one to show — its
 * title, text and buttons, an editor's to change in the panel. Otherwise it is the slot. Neither:
 * nothing at all, not even the file.
 *
 * Closed, it stays closed — in `localStorage`, by the version of what it says: a hash of the words
 * unless `version` names one. Change the words and the bar is back for everybody who closed the
 * old ones. The page does not flash it on the way: `Widgets::finish()` writes a few lines into the
 * head that hide a closed version before the body is painted.
 *
 * It stands above the header in the flow and does not stick: the sticky header measures itself,
 * not what is above it, so `--webx-header-height` stays what it was.
 */
final class NoticeBar extends Component
{
    /** The versions a visitor closed, in `localStorage`: the newest of them, so the list stays short. */
    public const string STORAGE = 'webx-notice-bar';

    public function __construct(
        public ?string $place = 'notice',
        public ?string $label = null,
        public ?string $version = null,
        public bool $closable = true,
    ) {
        if ($version !== null && preg_match('/^[a-z0-9-]{1,40}$/i', $version) !== 1) {
            throw new InvalidArgumentException("<x-webx-notice-bar version=\"{$version}\">: letters, digits and dashes, up to 40.");
        }
    }

    public function render(): Closure
    {
        // A closure: whether there is anything to say is known only with the slot.
        return function (array $data): View|string {
            $card = $this->card();
            $slot = $data['slot'] ?? '';
            $words = $card === null ? trim((string) $slot) : '';

            if ($card === null && $words === '') {
                // A string is a view's name or a template to compile: an empty one is nothing.
                return '';
            }

            Widgets::need('notice-bar');

            return view('webx-widgets::components.notice-bar', [
                'card' => $card,
                'revision' => $this->version ?? self::stamp($card ?? $words),
                'labelText' => $this->label ?? __('webx-widgets::widgets.notice_bar.label'),
            ]);
        };
    }

    /**
     * The version of what the bar says: the same words are the same version on every page and
     * every day, other words another one. Whitespace does not count — a template indented anew
     * does not bring a closed bar back.
     *
     * @param  array<string, mixed>|string  $content
     */
    public static function stamp(array|string $content): string
    {
        $text = is_array($content) ? (string) json_encode($content, JSON_UNESCAPED_UNICODE) : $content;

        return substr(hash('sha256', (string) preg_replace('/\s+/u', ' ', trim($text))), 0, 12);
    }

    /**
     * What `Widgets::finish()` writes into the head of a page with these bars: a few lines that
     * read the versions this visitor closed and hide those bars before the body is painted — the
     * stylesheet and the script come too late for that. The bar's own script removes them then.
     *
     * @param  list<string>  $versions
     */
    public static function head(array $versions): string
    {
        $versions = array_values(array_filter($versions, static fn (string $version): bool => preg_match('/^[a-z0-9-]{1,40}$/i', $version) === 1));

        if ($versions === []) {
            return '';
        }

        return '<script>try{var c=JSON.parse(localStorage.getItem('.json_encode(self::STORAGE).')||"[]"),h='
            .json_encode($versions, JSON_THROW_ON_ERROR)
            .'.filter(function(v){return c.indexOf(v)>-1});if(h.length){var s=document.createElement("style");'
            .'s.textContent=h.map(function(v){return\'[data-webx-notice-bar="\'+v+\'"]\'}).join(",")+"{display:none}";'
            .'document.head.appendChild(s)}}catch(e){}</script>';
    }

    /**
     * The first banner of the place a reader of this page may see — its words and buttons; null
     * without the module, without the place, or with nothing in it. A site whose banners cannot
     * be read right now gets the slot rather than a page that answers 500.
     *
     * @return array{title: string, text: string, buttons: list<array<string, mixed>>}|null
     */
    private function card(): ?array
    {
        if ($this->place === null || ! function_exists('banners')) {
            return null;
        }

        $card = rescue(fn (): mixed => banners($this->place)->first(), null);

        if (! is_array($card)) {
            return null;
        }

        $words = [
            'title' => trim((string) ($card['title'] ?? '')),
            'text' => trim((string) ($card['text'] ?? '')),
            'buttons' => array_values(array_filter((array) ($card['buttons'] ?? []), is_array(...))),
        ];

        return $words['title'] === '' && $words['text'] === '' && $words['buttons'] === [] ? null : $words;
    }
}
