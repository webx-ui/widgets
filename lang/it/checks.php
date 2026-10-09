<?php

declare(strict_types=1);

return [
    'widgets' => [
        'before_consent' => [
            'title' => 'Terze parti caricate prima del consenso',
            'found' => 'Un lettore video, una mappa, un contatore o un pixel viene richiesto al caricamento della pagina, prima che il visitatore risponda al banner dei cookie.',
            'why' => 'Nell’UE una terza parte che imposta cookie o riceve l’indirizzo del visitatore può caricarsi solo dopo il consenso alla sua categoria. Il banner chiede, ma la pagina ha già inviato la richiesta.',
            'fix' => 'Usa il blocco video o mappa, che aspetta da sé, o racchiudi il codice in <x-webx-consent category="…">. Se incollato nel contenuto, la correzione lo fa aspettare: un iframe riceve data-src, uno script type="text/plain", entrambi data-webx-consent.',
        ],
        'banner_off' => [
            'title' => 'Banner dei cookie spento con terze parti sul sito',
            'found' => 'Il banner dei cookie è spento e il sito ha un video, una mappa, un contatore o altro segnato per attendere il consenso.',
            'why' => 'Con il banner spento tutto ciò che è di terze parti si carica per ogni visitatore senza chiedere. È permesso solo a un sito che non ha bisogno di consenso: fuori dall’UE e senza visitatori da lì.',
            'fix' => 'Accendi il banner in Impostazioni › Cookie (lo fa la correzione). Se il sito davvero non ne ha bisogno, nascondi questo risultato con il motivo.',
        ],
        'lightbox_size' => [
            'title' => 'Link della lightbox senza dimensioni dell’immagine',
            'found' => 'Un link che apre un’immagine nella lightbox non ha data-width e data-height.',
            'why' => 'Senza dimensioni la lightbox scarica tutta l’immagine per misurarla prima di aprirsi, e l’immagine salta al suo posto.',
            'fix' => 'Usa <x-webx-lightbox :image> con un’immagine della libreria — scrive le dimensioni — o metti sul link data-width e data-height dell’immagine intera.',
        ],
        'slider_pause' => [
            'title' => 'Slider in movimento senza pulsante di pausa',
            'found' => 'Uno slider si muove da solo — autoplay o nastro continuo — e non ha un pulsante di pausa.',
            'why' => 'Un contenuto che si muove per più di cinque secondi deve potersi fermare (WCAG 2.2.2): distrae e alcuni visitatori non riescono a leggerlo affatto.',
            'fix' => 'La vista del pacchetto ha sempre il pulsante: una sovrascrittura di webx-widgets::components.slider nel tema ha perso .webx-slider__pause. Rimettilo o togli la sovrascrittura.',
        ],
        'contact_both' => [
            'title' => 'Pulsante di contatto rapido e barra in basso sulla stessa pagina',
            'found' => 'La pagina ha sia <x-webx-contact-button> sia <x-webx-contact-bar>.',
            'why' => 'Offrono due volte le stesse chiamate e chat, e sul telefono il pulsante copre la barra.',
            'fix' => 'Tieni uno dei due nel layout del tema.',
        ],
    ],
];
