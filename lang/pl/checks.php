<?php

declare(strict_types=1);

return [
    'widgets' => [
        'before_consent' => [
            'title' => 'Usługi zewnętrzne ładują się przed zgodą',
            'found' => 'Odtwarzacz wideo, mapa, licznik lub piksel są pobierane przy ładowaniu strony — zanim odwiedzający odpowie na baner cookie.',
            'why' => 'W UE usługa zewnętrzna, która ustawia cookie lub otrzymuje adres odwiedzającego, może się załadować dopiero po zgodzie na jej kategorię. Baner pyta, ale strona już wysłała żądanie.',
            'fix' => 'Użyj bloku wideo lub mapy, który czeka sam, albo otocz kod <x-webx-consent category="…">. Wklejony w treść — przycisk poprawki każe mu czekać: iframe dostaje data-src, script type="text/plain", oba data-webx-consent.',
        ],
        'banner_off' => [
            'title' => 'Baner cookie wyłączony, a usługi zewnętrzne są',
            'found' => 'Baner cookie jest wyłączony, a na stronie jest wideo, mapa, licznik lub coś innego oznaczonego, by czekać na zgodę.',
            'why' => 'Z wyłączonym banerem wszystko zewnętrzne ładuje się u każdego odwiedzającego bez pytania. Wolno tak tylko stronie, która nie potrzebuje zgody — poza UE i bez odwiedzających stamtąd.',
            'fix' => 'Włącz baner w Ustawieniach › Cookie (robi to przycisk poprawki). Jeśli strona naprawdę go nie potrzebuje, ukryj to znalezisko z powodem.',
        ],
        'lightbox_size' => [
            'title' => 'Linki lightboxa bez wymiarów obrazu',
            'found' => 'Link, który otwiera obraz w lightboxie, nie ma data-width i data-height.',
            'why' => 'Bez wymiarów lightbox pobiera cały obraz, żeby go zmierzyć, zanim się otworzy, a obraz przeskakuje na miejsce.',
            'fix' => 'Użyj <x-webx-lightbox :image> z obrazem z biblioteki — wpisuje wymiary — albo dopisz do linku data-width i data-height pełnego obrazu.',
        ],
        'slider_pause' => [
            'title' => 'Ruchome slidery bez przycisku pauzy',
            'found' => 'Slider porusza się sam — autoodtwarzanie lub przewijana taśma — i nie ma przycisku pauzy.',
            'why' => 'Treść, która porusza się dłużej niż pięć sekund, musi dać się zatrzymać (WCAG 2.2.2): rozprasza, a niektórzy odwiedzający w ogóle nie mogą jej przeczytać.',
            'fix' => 'Widok pakietu zawsze ma przycisk: nadpisanie webx-widgets::components.slider w motywie zgubiło .webx-slider__pause. Przywróć go albo usuń nadpisanie.',
        ],
        'contact_both' => [
            'title' => 'Przycisk szybkiego kontaktu i dolny pasek na jednej stronie',
            'found' => 'Strona ma zarówno <x-webx-contact-button>, jak i <x-webx-contact-bar>.',
            'why' => 'Dwa razy proponują te same połączenia i czaty, a na telefonie przycisk zasłania pasek.',
            'fix' => 'Zostaw w układzie motywu jedno z nich.',
        ],
    ],
];
