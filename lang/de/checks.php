<?php

declare(strict_types=1);

return [
    'widgets' => [
        'before_consent' => [
            'title' => 'Drittanbieter laden vor der Einwilligung',
            'found' => 'Ein Videoplayer, eine Karte, ein Zähler oder ein Pixel wird beim Laden der Seite angefragt, bevor der Besucher auf das Cookie-Banner geantwortet hat.',
            'why' => 'In der EU darf ein Drittanbieter, der Cookies setzt oder die Adresse des Besuchers erhält, erst nach Einwilligung in seine Kategorie laden. Das Banner fragt, aber die Seite hat die Anfrage schon gesendet.',
            'fix' => 'Verwenden Sie den Video- oder Kartenblock, der von selbst wartet, oder umschließen Sie den Code mit <x-webx-consent category="…">. Im Inhalt eingefügt, lässt die Korrektur ihn warten: ein iframe erhält data-src, ein script type="text/plain", beide data-webx-consent.',
        ],
        'banner_off' => [
            'title' => 'Cookie-Banner aus, aber Drittanbieter auf der Website',
            'found' => 'Das Cookie-Banner ist ausgeschaltet, und die Website hat ein Video, eine Karte, einen Zähler oder anderes, das auf Einwilligung warten soll.',
            'why' => 'Ohne Banner lädt alles von Drittanbietern bei jedem Besucher ohne Nachfrage. Das ist nur einer Website erlaubt, die keine Einwilligung braucht — außerhalb der EU und ohne Besucher von dort.',
            'fix' => 'Schalten Sie das Banner unter Einstellungen › Cookie ein (die Korrektur tut es). Braucht die Website wirklich kein Banner, blenden Sie diesen Befund mit Begründung aus.',
        ],
        'lightbox_size' => [
            'title' => 'Lightbox-Links ohne Bildgröße',
            'found' => 'Ein Link, der ein Bild in der Lightbox öffnet, hat kein data-width und data-height.',
            'why' => 'Ohne Größe lädt die Lightbox das ganze Bild herunter, um es zu messen, bevor sie sich öffnet, und das Bild springt an seinen Platz.',
            'fix' => 'Verwenden Sie <x-webx-lightbox :image> mit einem Bild der Mediathek — es schreibt die Größe — oder setzen Sie data-width und data-height des vollen Bildes auf den Link.',
        ],
        'slider_pause' => [
            'title' => 'Bewegte Slider ohne Pause-Schaltfläche',
            'found' => 'Ein Slider bewegt sich von selbst — Autoplay oder Laufband — und hat keine Pause-Schaltfläche.',
            'why' => 'Inhalt, der sich länger als fünf Sekunden bewegt, muss sich anhalten lassen (WCAG 2.2.2): Er lenkt ab, und manche Besucher können ihn gar nicht lesen.',
            'fix' => 'Die Ansicht des Pakets hat die Schaltfläche immer: Eine Überschreibung von webx-widgets::components.slider im Theme hat .webx-slider__pause verloren. Fügen Sie sie wieder ein oder entfernen Sie die Überschreibung.',
        ],
        'contact_both' => [
            'title' => 'Schnellkontakt-Schaltfläche und untere Leiste auf einer Seite',
            'found' => 'Die Seite hat sowohl <x-webx-contact-button> als auch <x-webx-contact-bar>.',
            'why' => 'Beide bieten dieselben Anrufe und Chats doppelt an, und auf dem Telefon liegt die Schaltfläche über der Leiste.',
            'fix' => 'Behalten Sie eines der beiden im Layout des Themes.',
        ],
    ],
];
