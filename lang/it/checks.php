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
        'video_pause' => [
            'title' => 'Video di sfondo senza pulsante di pausa',
            'found' => 'Un video di sfondo si riproduce da solo e non ha un pulsante di pausa.',
            'why' => 'Un movimento che dura più di cinque secondi deve poter essere fermato (WCAG 2.2.2): distrae, e alcuni visitatori non riescono a leggere il testo sopra.',
            'fix' => 'La vista del pacchetto ha sempre il pulsante: una sovrascrittura di webx-widgets::components.video nel tema ha perso .webx-video__pause. Rimettetelo o rimuovete la sovrascrittura.',
        ],
        'counter_number' => [
            'title' => 'Contatori senza il loro numero',
            'found' => 'Il markup di un contatore non contiene il numero fino a cui conta.',
            'why' => 'Motori di ricerca, lettori di schermo e una pagina senza JavaScript leggono il markup: ottengono uno zero o niente al posto del numero.',
            'fix' => 'La vista del pacchetto stampa il numero finale e lo script conta fino a esso: una sovrascrittura di webx-widgets::components.counter nel tema stampa altro. Stampate il numero o rimuovete la sovrascrittura.',
        ],
        'compare_range' => [
            'title' => 'Prima e dopo senza cursore',
            'found' => 'Un divisore prima/dopo non ha un campo range: solo il mouse e il dito possono spostarlo.',
            'why' => 'La tastiera non raggiunge il divisore e uno screen reader non può nominarlo: una parte dell’immagine resta nascosta a quei visitatori.',
            'fix' => 'La vista del pacchetto rende il divisore un <input type="range">: una sovrascrittura di webx-widgets::components.compare nel tema l’ha perso. Rimettetelo o togliete la sovrascrittura.',
        ],
        'toc_target' => [
            'title' => 'Indice che non porta da nessuna parte',
            'found' => 'Un link dell’indice porta a una sezione che la pagina non ha.',
            'why' => 'Il visitatore clicca una sezione e non succede nulla: la pagina non si muove e l’indice sembra rotto.',
            'fix' => 'Il server costruisce l’indice dai titoli della pagina e assegna loro gli id. Un indice scritto a mano, o una sovrascrittura dell’elenco nel tema, punta a un id sparito: usate <x-webx-toc> o correggete il link.',
        ],
        'load_more_link' => [
            'title' => '«Mostra altro» senza link alla pagina successiva',
            'found' => 'Un elenco con «Mostra altro» ha una pagina successiva ma nessun link verso di essa: ci porta solo il pulsante.',
            'why' => 'I motori di ricerca non premono pulsanti, e nemmeno una pagina senza JavaScript: ciò che segue la prima pagina non viene trovato.',
            'fix' => 'Il pacchetto stampa i link delle pagine sotto l’elenco e il pulsante li copre soltanto: una sovrascrittura di webx-widgets::components.load-more nel tema, o uno slot links vuoto, li ha persi. Rimetteteli o togliete la sovrascrittura.',
        ],
        'zoom_link' => [
            'title' => 'Zoom senza link',
            'found' => 'Un’immagine ingrandita sotto il mouse non è un link e non ne contiene: la tastiera non può aprirla.',
            'why' => 'Tastiera e screen reader non hanno il passaggio del mouse: il link all’immagine, aperto nel visualizzatore, è il loro unico modo per vederla da vicino.',
            'fix' => 'La vista del pacchetto rende l’immagine un link all’originale: una sovrascrittura di webx-widgets::components.zoom nel tema l’ha perso, oppure data-webx-zoom è scritto su un’immagine senza link. Mettete l’attributo sul link del visualizzatore o togliete la sovrascrittura.',
        ],
    ],
];
