{{--
    Opening hours (spec §12.3). `data-webx-hours` holds the hours and every word the status
    could say, already written in the page's language: the script picks the sentence for now —
    the page may have lain in a cache for hours — and writes nothing of its own. Without
    JavaScript the status is the server's, as of the response.

    Classes: webx-hours, --status, --table, __status, is-open, is-closed, __table, __day,
    is-today, __days, __times, __special, __occasion.
--}}
<div {{ $attributes->class(['webx-hours', 'webx-hours--'.$layout])->merge(['data-webx-hours' => json_encode($script, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]) }}>
    @if ($layout === 'table')
        <p @class(['webx-hours__status', 'is-open' => $status['open'], 'is-closed' => ! $status['open']]) data-webx-hours-status>{{ $status['text'] }}</p>
        @include('webx-widgets::partials.hours-table')
    @else
        <x-webx-dropdown class="webx-hours__dropdown" :placement="$placement">
            <x-slot:trigger class="webx-hours__toggle">
                <x-webx-icon name="clock" />
                <span @class(['webx-hours__status', 'is-open' => $status['open'], 'is-closed' => ! $status['open']]) data-webx-hours-status>{{ $status['text'] }}</span>
            </x-slot:trigger>
            @include('webx-widgets::partials.hours-table')
        </x-webx-dropdown>
    @endif
</div>
