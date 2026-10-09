<?php

declare(strict_types=1);

return [
    'widgets' => [
        'before_consent' => [
            'title' => 'Des tiers se chargent avant le consentement',
            'found' => 'Un lecteur vidéo, une carte, un compteur ou un pixel est demandé au chargement de la page, avant que le visiteur ait répondu au bandeau cookies.',
            'why' => 'Dans l’UE, un tiers qui dépose des cookies ou reçoit l’adresse du visiteur ne peut se charger qu’après le consentement à sa catégorie. Le bandeau demande, mais la page a déjà envoyé la requête.',
            'fix' => 'Utilisez le bloc vidéo ou carte, qui attend de lui-même, ou entourez le code de <x-webx-consent category="…">. Collé dans le contenu, la correction le fait attendre : un iframe reçoit data-src, un script type="text/plain", les deux data-webx-consent.',
        ],
        'banner_off' => [
            'title' => 'Bandeau cookies désactivé avec des tiers sur le site',
            'found' => 'Le bandeau cookies est désactivé, et le site a une vidéo, une carte, un compteur ou autre chose marqué pour attendre le consentement.',
            'why' => 'Bandeau désactivé, tout ce qui vient de tiers se charge chez chaque visiteur sans rien demander. Ce n’est permis qu’à un site qui n’a pas besoin de consentement — hors de l’UE et sans visiteurs de l’UE.',
            'fix' => 'Activez le bandeau dans Réglages › Cookie (la correction le fait). Si le site n’en a vraiment pas besoin, masquez ce constat avec la raison.',
        ],
        'lightbox_size' => [
            'title' => 'Liens de lightbox sans taille d’image',
            'found' => 'Un lien qui ouvre une image dans la lightbox n’a ni data-width ni data-height.',
            'why' => 'Sans taille, la lightbox télécharge toute l’image pour la mesurer avant de s’ouvrir, et l’image saute à sa place.',
            'fix' => 'Utilisez <x-webx-lightbox :image> avec une image de la médiathèque — il écrit la taille — ou mettez data-width et data-height de l’image complète sur le lien.',
        ],
        'slider_pause' => [
            'title' => 'Sliders animés sans bouton pause',
            'found' => 'Un slider bouge tout seul — défilement automatique ou bandeau continu — et n’a pas de bouton pause.',
            'why' => 'Un contenu qui bouge plus de cinq secondes doit pouvoir être arrêté (WCAG 2.2.2) : il distrait, et certains visiteurs ne peuvent pas le lire du tout.',
            'fix' => 'La vue du paquet a toujours le bouton : une surcharge de webx-widgets::components.slider dans le thème a perdu .webx-slider__pause. Remettez-le ou supprimez la surcharge.',
        ],
        'contact_both' => [
            'title' => 'Bouton de contact rapide et barre du bas sur une page',
            'found' => 'La page a à la fois <x-webx-contact-button> et <x-webx-contact-bar>.',
            'why' => 'Ils proposent deux fois les mêmes appels et messageries, et sur un téléphone le bouton recouvre la barre.',
            'fix' => 'Gardez l’un des deux dans la mise en page du thème.',
        ],
    ],
];
