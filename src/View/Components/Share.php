<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Contacts\ContactsSource;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-share />` — "share this page" as plain links (spec §14): each network's own address
 * for sharing, opened in a new tab, an e-mail, and "Copy link". No script of a network's — their
 * buttons set cookies before anyone has agreed to anything — and nothing to ask consent for.
 *
 * On a phone, where the system has a share sheet of its own (`navigator.share`), the script
 * shows one "Share" button that opens it instead: it knows the apps the visitor actually has.
 *
 * `url` — the page's address without its query by default; `title` — the text a network puts
 * with the link, none by default; `networks` — which, in which order.
 */
final class Share extends Component
{
    /** The address each network shares through: `{url}` and `{text}` filled in, encoded. */
    public const array NETWORKS = [
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u={url}',
        'x' => 'https://x.com/intent/post?url={url}&text={text}',
        'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}',
        'telegram' => 'https://t.me/share/url?url={url}&text={text}',
        'whatsapp' => 'https://wa.me/?text={both}',
        'viber' => 'viber://forward?text={both}',
        'reddit' => 'https://www.reddit.com/submit?url={url}&title={text}',
        'pinterest' => 'https://www.pinterest.com/pin/create/button/?url={url}&description={text}',
        'threads' => 'https://www.threads.net/intent/post?text={both}',
        'bluesky' => 'https://bsky.app/intent/compose?text={both}',
        'vk' => 'https://vk.com/share.php?url={url}&title={text}',
        'email' => 'mailto:?subject={text}&body={url}',
        'copy' => '',
    ];

    public const array DEFAULT = ['facebook', 'x', 'linkedin', 'telegram', 'whatsapp', 'email', 'copy'];

    /** @var list<array{network: string, href: string, label: string, icon: string}> */
    public array $links = [];

    public string $address;

    /**
     * @param  list<string>  $networks
     */
    public function __construct(
        ?string $url = null,
        public ?string $title = null,
        public array $networks = self::DEFAULT,
        public ?string $label = null,
    ) {
        $this->address = $url ?? url()->current();

        if (preg_match('~^https?://~i', $this->address) !== 1) {
            throw new InvalidArgumentException("<x-webx-share url=\"{$this->address}\">: an absolute http(s) address — a network cannot open a relative one.");
        }

        foreach ($networks as $network) {
            if (! array_key_exists($network, self::NETWORKS)) {
                throw new InvalidArgumentException("<x-webx-share :networks>: \"{$network}\" is none of ".implode(', ', array_keys(self::NETWORKS)).'.');
            }
        }

        $text = trim((string) $title);

        foreach (array_values(array_unique($networks)) as $network) {
            $this->links[] = [
                'network' => $network,
                'href' => strtr(self::NETWORKS[$network], [
                    '{url}' => rawurlencode($this->address),
                    '{text}' => rawurlencode($text),
                    '{both}' => rawurlencode(trim($text.' '.$this->address)),
                ]),
                'label' => match ($network) {
                    'email' => __('webx-widgets::widgets.share.email'),
                    'copy' => __('webx-widgets::widgets.share.copy'),
                    default => __('webx-widgets::widgets.share.on', ['network' => ContactsSource::brand($network)]),
                },
                'icon' => match ($network) {
                    'email' => 'mail',
                    'copy' => 'link',
                    default => ContactsSource::icon($network),
                },
            ];
        }
    }

    public function shouldRender(): bool
    {
        return $this->links !== [];
    }

    public function render(): View
    {
        Widgets::need('share');

        return view('webx-widgets::components.share', [
            'labelText' => $this->label ?? __('webx-widgets::widgets.share.label'),
        ]);
    }
}
