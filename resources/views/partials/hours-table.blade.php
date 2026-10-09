<table class="webx-hours__table">
    <caption class="webx-hours__caption">{{ $labelText }}</caption>
    <tbody>
        @foreach ($rows as $row)
            <tr @class(['webx-hours__day', 'is-today' => $row['today']]) data-weekdays="{{ implode(',', $row['weekdays']) }}">
                <th class="webx-hours__days" scope="row">{{ $row['days'] }}</th>
                <td class="webx-hours__times">{{ $row['hours'] }}</td>
            </tr>
        @endforeach
    </tbody>
    @if ($special !== [])
        <tbody class="webx-hours__special">
            <tr>
                <th class="webx-hours__heading" colspan="2" scope="colgroup">{{ $specialText }}</th>
            </tr>
            @foreach ($special as $date)
                <tr @class(['webx-hours__day', 'is-today' => $date['today']]) data-date="{{ $date['iso'] }}">
                    <th class="webx-hours__days" scope="row">
                        {{ $date['date'] }}
                        @if ($date['label'] !== null)
                            <span class="webx-hours__occasion">{{ $date['label'] }}</span>
                        @endif
                    </th>
                    <td class="webx-hours__times">{{ $date['hours'] }}</td>
                </tr>
            @endforeach
        </tbody>
    @endif
</table>
