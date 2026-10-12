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
        'video_pause' => [
            'title' => 'Hintergrundvideos ohne Pause-Schaltfläche',
            'found' => 'Ein Hintergrundvideo läuft von selbst und hat keine Pause-Schaltfläche.',
            'why' => 'Bewegung, die länger als fünf Sekunden dauert, muss sich anhalten lassen (WCAG 2.2.2): Sie lenkt ab, und manche Besucher können den Text darüber nicht lesen.',
            'fix' => 'Die Ansicht des Pakets hat die Schaltfläche immer: Eine Überschreibung von webx-widgets::components.video im Theme hat .webx-video__pause verloren. Ergänzen Sie sie wieder oder entfernen Sie die Überschreibung.',
        ],
        'counter_number' => [
            'title' => 'Zähler ohne ihre Zahl',
            'found' => 'Das Markup eines Zählers enthält nicht die Zahl, bis zu der er zählt.',
            'why' => 'Suchmaschinen, Screenreader und eine Seite ohne JavaScript lesen das Markup: Sie bekommen eine Null oder nichts statt der Zahl.',
            'fix' => 'Die Ansicht des Pakets gibt die Endzahl aus, und das Skript zählt bis dahin: Eine Überschreibung von webx-widgets::components.counter im Theme gibt etwas anderes aus. Geben Sie die Zahl aus oder entfernen Sie die Überschreibung.',
        ],
        'compare_range' => [
            'title' => 'Vorher-nachher ohne Schieberegler',
            'found' => 'Eine Vorher-nachher-Trennlinie hat kein Range-Feld: Nur Maus und Finger können sie bewegen.',
            'why' => 'Mit der Tastatur ist die Trennlinie nicht erreichbar, und ein Screenreader kann sie nicht benennen: Ein Teil des Bildes bleibt diesen Besuchern verborgen.',
            'fix' => 'Die Ansicht des Pakets macht die Trennlinie zu einem <input type="range">: Eine Überschreibung von webx-widgets::components.compare im Theme hat es verloren. Fügen Sie es wieder ein oder entfernen Sie die Überschreibung.',
        ],
        'toc_target' => [
            'title' => 'Inhaltsverzeichnis führt ins Leere',
            'found' => 'Ein Link des Inhaltsverzeichnisses führt zu einem Abschnitt, den die Seite nicht hat.',
            'why' => 'Ein Besucher klickt auf einen Abschnitt und nichts passiert: Die Seite bewegt sich nicht, und das Verzeichnis wirkt kaputt.',
            'fix' => 'Der Server baut das Verzeichnis aus den Überschriften der Seite und vergibt ihre IDs. Ein von Hand geschriebenes Verzeichnis oder eine Überschreibung der Liste im Theme verweist auf eine verschwundene ID: Nutzen Sie <x-webx-toc> oder korrigieren Sie den Link.',
        ],
        'load_more_link' => [
            'title' => '„Mehr anzeigen“ ohne Link zur nächsten Seite',
            'found' => 'Eine Liste mit „Mehr anzeigen“ hat eine nächste Seite, aber keinen Link dorthin: Nur der Button führt hin.',
            'why' => 'Suchmaschinen drücken keine Buttons, und eine Seite ohne JavaScript kann es auch nicht: Was nach der ersten Seite kommt, wird nicht gefunden.',
            'fix' => 'Das Paket druckt die Links zu den Seiten unter der Liste, der Button verdeckt sie nur: Eine Überschreibung von webx-widgets::components.load-more im Theme oder ein leerer links-Slot hat sie verloren. Setzen Sie sie zurück oder entfernen Sie die Überschreibung.',
        ],
        'zoom_link' => [
            'title' => 'Zoom ohne Link',
            'found' => 'Ein Bild, das unter der Maus vergrößert wird, ist kein Link und enthält keinen: Mit der Tastatur lässt es sich nicht öffnen.',
            'why' => 'Tastatur und Screenreader kennen kein Überfahren: Der Link zum Bild, in der Bildansicht geöffnet, ist ihr einziger Weg, es näher zu sehen.',
            'fix' => 'Die Ansicht des Pakets macht das Bild zu einem Link auf das Original: Eine Überschreibung von webx-widgets::components.zoom im Theme hat ihn verloren, oder data-webx-zoom steht auf einem nackten Bild. Setzen Sie das Attribut auf den Lightbox-Link des Bildes oder entfernen Sie die Überschreibung.',
        ],
        'form_script' => [
            'title' => 'Ein Formular ohne sein Skript',
            'found' => 'Ein Formular, das an Ort und Stelle sendet, steht auf einer Seite ohne form.js: Es lädt die Seite neu, um zu antworten, und nur der Server prüft es.',
            'why' => 'Ohne das Skript wartet der Besucher auf eine ganze Seite, verliert die Stelle, bis zu der er gescrollt hat, und erfährt von einem Fehler erst nach dem Senden.',
            'fix' => 'Das Layout gibt die Skripte mit @webxTheme aus, auf einer Website ohne Theme mit @webxWidgets vor </body>; danach php artisan webx:theme:sync. Eine Website, die die Skripte selbst bündelt, kann dies ignorieren.',
        ],
    ],
];
