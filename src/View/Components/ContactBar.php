<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use WebxUi\Widgets\Contacts\ContactsSource;
use WebxUi\Widgets\Facades\Widgets;

/**
 * `<x-webx-contact-bar form="callback" />` — a bar along the bottom of a phone's screen (spec
 * §12.4): "Call" the main number, "Write" to the first chat (or the first e-mail), "Request" — a
 * form in a dialog.
 *
 * Shown while the page is narrower than `breakpoint`, above the home indicator
 * (`safe-area-inset-bottom`), and it takes its own height at the end of the page, so the footer
 * is never under it. All CSS: it needs no script.
 */
final class ContactBar extends Component
{
    /** @var list<array{kind: string, href: string, text: string, icon: string, external: bool}> */
    public array $links = [];

    public function __construct(
        public ?string $form = null,
        public int $breakpoint = 768,
        public ?string $callLabel = null,
        public ?string $writeLabel = null,
        public ?string $formLabel = null,
    ) {
        if ($breakpoint < 1) {
            throw new InvalidArgumentException('<x-webx-contact-bar :breakpoint>: a width in pixels.');
        }

        $contacts = ContactsSource::get();
        $phone = $contacts?->primaryPhone();
        $chat = $contacts?->chats()[0] ?? null;
        $email = $contacts?->emails()[0] ?? null;

        if ($phone !== null) {
            $this->links[] = ['kind' => 'call', 'href' => $phone->href, 'text' => $callLabel ?? (string) __('webx-widgets::widgets.contact.call'), 'icon' => 'phone', 'external' => false];
        }

        if ($chat !== null || $email !== null) {
            $this->links[] = [
                'kind' => 'write',
                'href' => $chat !== null ? $chat->url : $email->href(),
                'text' => $writeLabel ?? (string) __('webx-widgets::widgets.contact.write'),
                'icon' => $chat !== null ? ContactsSource::icon($chat->kind) : 'mail',
                'external' => $chat !== null,
            ];
        }

        if ($form !== null) {
            $this->links[] = ['kind' => 'form', 'href' => '#webx-form-'.$form, 'text' => $formLabel ?? (string) __('webx-widgets::widgets.contact.request'), 'icon' => 'form', 'external' => false];
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

        return view('webx-widgets::components.contact-bar');
    }
}
