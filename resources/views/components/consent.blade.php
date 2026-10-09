{{--
    Code that waits for consent to its category (spec §9.3): as it is once agreed to, inert in a
    <template> until then — the consent script puts it on the page when the visitor agrees.
--}}
@if ($granted)
{{ $slot }}
@else
<template data-webx-consent="{{ $category }}">{{ $slot }}</template>
@endif
