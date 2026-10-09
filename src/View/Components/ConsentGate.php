<?php

declare(strict_types=1);

namespace WebxUi\Widgets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use WebxUi\Widgets\Consent;

/**
 * `<x-webx-consent category="statistics">…</x-webx-consent>` — any code that must wait for an
 * answer: a counter pasted from the settings, a chat, an embed (spec §9.3).
 *
 * With the category agreed to — the server reads the cookie — the code is printed as it is.
 * Without, it is printed inside a `<template>`, which the browser neither runs nor loads, and the
 * consent script puts it on the page, scripts re-created so they run, once the visitor agrees.
 */
final class ConsentGate extends Component
{
    public function __construct(
        public string $category,
    ) {
        Consent::check($category);
    }

    public function render(): View
    {
        return view('webx-widgets::components.consent', [
            'granted' => app(Consent::class)->has($this->category),
        ]);
    }
}
