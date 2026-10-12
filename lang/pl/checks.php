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
        'video_pause' => [
            'title' => 'Wideo w tle bez przycisku pauzy',
            'found' => 'Wideo w tle odtwarza się samo i nie ma przycisku pauzy.',
            'why' => 'Ruch trwający dłużej niż pięć sekund musi dać się zatrzymać (WCAG 2.2.2): rozprasza, a niektórzy odwiedzający nie mogą przeczytać tekstu na nim.',
            'fix' => 'Widok pakietu zawsze ma przycisk: nadpisanie webx-widgets::components.video w motywie zgubiło .webx-video__pause. Przywróć go lub usuń nadpisanie.',
        ],
        'counter_number' => [
            'title' => 'Liczniki bez swojej liczby',
            'found' => 'Znaczniki licznika nie zawierają liczby, do której liczy.',
            'why' => 'Wyszukiwarki, czytniki ekranu i strona bez JavaScriptu czytają znaczniki: zamiast liczby dostają zero albo nic.',
            'fix' => 'Widok pakietu wypisuje liczbę końcową, a skrypt do niej odlicza: nadpisanie webx-widgets::components.counter w motywie wypisuje coś innego. Wypisz liczbę lub usuń nadpisanie.',
        ],
        'compare_range' => [
            'title' => 'Przed i po bez suwaka',
            'found' => 'Linia podziału przed i po nie ma pola range: przesunąć ją można tylko myszą i palcem.',
            'why' => 'Klawiatura nie dosięga linii podziału, a czytnik ekranu nie może jej nazwać: część obrazu pozostaje dla tych odwiedzających ukryta.',
            'fix' => 'Widok pakietu robi z linii podziału <input type="range">: nadpisanie webx-widgets::components.compare w motywie je zgubiło. Przywróć pole albo usuń nadpisanie.',
        ],
        'toc_target' => [
            'title' => 'Spis treści prowadzi donikąd',
            'found' => 'Link spisu treści prowadzi do sekcji, której na stronie nie ma.',
            'why' => 'Odwiedzający klika sekcję i nic się nie dzieje: strona się nie przesuwa, a spis wygląda na zepsuty.',
            'fix' => 'Serwer buduje spis z nagłówków strony i sam nadaje im id. Spis napisany ręcznie albo nadpisanie listy w motywie wskazuje na id, którego już nie ma: użyj <x-webx-toc> albo popraw link.',
        ],
        'load_more_link' => [
            'title' => '„Pokaż więcej” bez linku do następnej strony',
            'found' => 'Lista z „Pokaż więcej” ma następną stronę, ale nie ma do niej linku: prowadzi tam tylko przycisk.',
            'why' => 'Wyszukiwarki nie naciskają przycisków, strona bez JavaScriptu też nie: to, co jest za pierwszą stroną, nie zostaje znalezione.',
            'fix' => 'Pakiet drukuje linki do stron pod listą, a przycisk tylko je zasłania: nadpisanie webx-widgets::components.load-more w motywie lub pusty slot links je zgubiło. Przywróć je albo usuń nadpisanie.',
        ],
        'zoom_link' => [
            'title' => 'Powiększenie bez linku',
            'found' => 'Obraz powiększany pod myszą nie jest linkiem i nie zawiera linku: klawiatura nie może go otworzyć.',
            'why' => 'Klawiatura i czytnik ekranu nie mają najechania: link do obrazu, otwarty w przeglądarce zdjęć, to ich jedyny sposób, by obejrzeć go z bliska.',
            'fix' => 'Widok pakietu robi z obrazu link do oryginału: nadpisanie webx-widgets::components.zoom w motywie go zgubiło albo data-webx-zoom napisano na gołym obrazie. Umieść atrybut na linku przeglądarki zdjęć albo usuń nadpisanie.',
        ],
        'form_script' => [
            'title' => 'Formularz bez swojego skryptu',
            'found' => 'Formularz wysyłany na miejscu stoi na stronie bez form.js: przeładowuje stronę, aby odpowiedzieć, i sprawdza go tylko serwer.',
            'why' => 'Bez skryptu odwiedzający czeka na całą stronę, traci miejsce, do którego przewinął, i o błędzie dowiaduje się dopiero po wysłaniu.',
            'fix' => 'Skrypty drukuje layout: @webxTheme, a na stronie bez motywu @webxWidgets przed </body>; potem php artisan webx:theme:sync. Strona, która sama pakuje skrypty, może to zignorować.',
        ],
    ],
];
