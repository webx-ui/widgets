<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Contacts\ContactsSource;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-contact-button form="callback" />` — a round button in a corner of the window that
 * opens into the ways to reach the site (spec §12.4): every chat — a number's messengers, then
 * the channels of their own — the main number, the first e-mail, and a form in a dialog.
 *
 * It is a dropdown (§6.3) opening upwards, so the keyboard, Esc and one-open-at-a-time are the
 * dropdown's. While the cookie banner is on the screen the button stands above it rather than
 * under it. `:items` picks and orders what the list holds; nothing to list — nothing printed.
 */
final class ContactButton extends Component
{
    public const array CORNERS = ['bottom-end', 'bottom-start'];

    public const array ITEMS = ['chats', 'phone', 'email', 'form'];

    /** @var list<array{kind: string, href: string, text: string, icon: string, external: bool}> */
    public array $links = [];

    /**
     * @param  list<string>  $items
     */
    public function __construct(
        public string $corner = 'bottom-end',
        public ?string $form = null,
        public array $items = self::ITEMS,
        public ?string $label = null,
        public ?string $formLabel = null,
    ) {
        if (! in_array($corner, self::CORNERS, true)) {
            throw new InvalidArgumentException("<x-webx-contact-button corner=\"{$corner}\">: bottom-end or bottom-start.");
        }

        foreach ($items as $item) {
            if (! in_array($item, self::ITEMS, true)) {
                throw new InvalidArgumentException("<x-webx-contact-button :items>: \"{$item}\" is none of ".implode(', ', self::ITEMS).'.');
            }
        }

        foreach ($items as $item) {
            array_push($this->links, ...$this->linksOf($item));
        }
    }

    public function shouldRender(): bool
    {
        return $this->links !== [];
    }

    public function render(): View
    {
        Widgets::need('contacts');

        if ($this->form !== null) {
            Widgets::form($this->form);
        }

        return view('webx-widgets::components.contact-button', [
            'labelText' => $this->label ?? __('webx-widgets::widgets.contact.open'),
        ]);
    }

    /**
     * @return list<array{kind: string, href: string, text: string, icon: string, external: bool}>
     */
    private function linksOf(string $item): array
    {
        $contacts = ContactsSource::get();

        if ($item === 'chats') {
            return array_map(static fn ($chat): array => [
                'kind' => $chat->kind,
                'href' => $chat->url,
                'text' => ContactsSource::brand($chat->kind).($chat->label === null ? '' : ' · '.$chat->label),
                'icon' => ContactsSource::icon($chat->kind),
                'external' => true,
            ], $contacts?->chats() ?? []);
        }

        if ($item === 'phone') {
            $phone = $contacts?->primaryPhone();

            return $phone === null ? [] : [['kind' => 'phone', 'href' => $phone->href, 'text' => $phone->number, 'icon' => 'phone', 'external' => false]];
        }

        if ($item === 'email') {
            $email = $contacts?->emails()[0] ?? null;

            return $email === null ? [] : [['kind' => 'email', 'href' => $email->href(), 'text' => $email->address, 'icon' => 'mail', 'external' => false]];
        }

        return $this->form === null ? [] : [[
            'kind' => 'form',
            'href' => '#webx-form-'.$this->form,
            'text' => $this->formLabel ?? (string) __('webx-widgets::widgets.contact.form'),
            'icon' => 'form',
            'external' => false,
        ]];
    }
}
