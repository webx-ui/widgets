{{-- A day and a time (FORM_STEPS §6.4), in the zone of the site. Without the script: the browser's
     date (`min` — today in that zone as the page was printed) and a list of the day's times, the zone
     under them; a day of the week not offered and a time of today already gone are the server's to
     refuse. With it, datetime.js hides both and draws a calendar and the times of the chosen day,
     writing every choice back into them — the checks, the errors and what is sent stay theirs.
     `visitor-timezone` (§6.7): `<name>[timezone]` goes with the answer — the site's zone as printed,
     the browser's once the script has counted the grid in it. `lead-days`: `min` is that many days on.
     Classes: webx-datetime, __plain, __part, __label, __zone; the script's — see datetime.css. --}}
@php
    $moment = $moment();
    $zoneId = $fieldId.'-zone';
    $described = trim($zoneId.' '.$describedBy());
    // Shown only for some answers: not `required` to the browser, form.js puts it back on show.
    $asked = $required && ! $conditional();
    $later = $required && $conditional();
@endphp
<div class="webx-datetime" data-webx-datetime="{{ json_encode([
    'step' => $moment['step'],
    'weekdays' => $moment['weekdays'],
    'zone' => $moment['zone'],
    'times' => $moment['times'],
    'lead' => $moment['lead'],
    'visitor' => $moment['visitor'],
    'label' => $moment['label'],
    'panel' => $moment['panel'],
    'first' => $moment['first'],
    'words' => [
        'previous' => $words['previous_month'],
        'next' => $words['next_month'],
        'none' => $words['no_times'],
        'unavailable' => $words['unavailable'],
        'time' => $words['time'],
        'times' => $words['times'],
        'zone' => $words['visitor_zone'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
    <div class="webx-datetime__plain">
        <span class="webx-datetime__part">
            <label class="webx-datetime__label" for="{{ $fieldId }}">{{ $words['date'] }}</label>
            <input type="date" class="webx-field__control" id="{{ $fieldId }}" name="{{ $inputName }}[date]" value="{{ $moment['date'] }}" min="{{ $moment['min'] }}" aria-describedby="{{ $described }}" @required($asked) @if ($later) data-webx-required="true" @endif @disabled($disabled) @if ($invalid) aria-invalid="true" @endif>
        </span>
        <span class="webx-datetime__part">
            <label class="webx-datetime__label" for="{{ $fieldId }}-time">{{ $words['time'] }}</label>
            <span class="webx-field__select">
                <select class="webx-field__control webx-field__control--select" id="{{ $fieldId }}-time" name="{{ $inputName }}[time]" aria-describedby="{{ $described }}" @required($asked) @if ($later) data-webx-required="true" @endif @disabled($disabled) @if ($invalid) aria-invalid="true" @endif>
                    <option value="">{{ $words['choose'] }}</option>
                    @foreach ($moment['times'] as $time)
                        <option value="{{ $time['value'] }}" @selected($moment['time'] === $time['value'])>{{ $time['label'] }}</option>
                    @endforeach
                </select>
            </span>
        </span>
        @if ($moment['visitor'])
            <input type="hidden" name="{{ $inputName }}[timezone]" value="{{ $moment['zone'] }}" data-webx-timezone @disabled($disabled)>
        @endif
    </div>
    <p class="webx-datetime__zone" id="{{ $zoneId }}">{{ $moment['zoneLabel'] }}</p>
</div>
