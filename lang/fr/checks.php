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
        'video_pause' => [
            'title' => 'Vidéos d’arrière-plan sans bouton pause',
            'found' => 'Une vidéo d’arrière-plan se lit d’elle-même et n’a pas de bouton pause.',
            'why' => 'Un mouvement qui dure plus de cinq secondes doit pouvoir être arrêté (WCAG 2.2.2) : il distrait, et certains visiteurs ne peuvent pas lire le texte par-dessus.',
            'fix' => 'La vue du paquet a toujours le bouton : une surcharge de webx-widgets::components.video dans le thème a perdu .webx-video__pause. Remettez-le ou supprimez la surcharge.',
        ],
        'counter_number' => [
            'title' => 'Compteurs sans leur nombre',
            'found' => 'Le balisage d’un compteur ne contient pas le nombre jusqu’auquel il compte.',
            'why' => 'Les moteurs de recherche, les lecteurs d’écran et une page sans JavaScript lisent le balisage : ils obtiennent un zéro ou rien au lieu du nombre.',
            'fix' => 'La vue du paquet affiche le nombre final et le script compte jusqu’à lui : une surcharge de webx-widgets::components.counter dans le thème affiche autre chose. Affichez le nombre ou supprimez la surcharge.',
        ],
        'compare_range' => [
            'title' => 'Avant/après sans curseur',
            'found' => 'Une séparation avant/après n’a pas de champ range : seuls la souris et le doigt peuvent la déplacer.',
            'why' => 'Le clavier ne peut pas atteindre la séparation et un lecteur d’écran ne peut pas la nommer : une partie de l’image reste cachée à ces visiteurs.',
            'fix' => 'La vue du paquet fait de la séparation un <input type="range"> : une surcharge de webx-widgets::components.compare dans le thème l’a perdu. Remettez-le ou supprimez la surcharge.',
        ],
        'toc_target' => [
            'title' => 'Table des matières qui ne mène nulle part',
            'found' => 'Un lien de la table des matières mène à une section que la page n’a pas.',
            'why' => 'Le visiteur clique sur une section et rien ne se passe : la page ne bouge pas et la table semble cassée.',
            'fix' => 'Le serveur construit la table à partir des titres de la page et leur donne leurs id. Une table écrite à la main, ou une surcharge de la liste dans le thème, vise un id disparu : utilisez <x-webx-toc> ou corrigez le lien.',
        ],
        'load_more_link' => [
            'title' => '« Afficher plus » sans lien vers la page suivante',
            'found' => 'Une liste « Afficher plus » a une page suivante mais aucun lien vers elle : seul son bouton y mène.',
            'why' => 'Les moteurs de recherche n’appuient pas sur les boutons, et une page sans JavaScript non plus : ce qui suit la première page n’est pas trouvé.',
            'fix' => 'Le paquet imprime les liens des pages sous la liste et le bouton ne fait que les couvrir : une surcharge de webx-widgets::components.load-more dans le thème, ou un slot links vide, les a perdus. Remettez-les, ou retirez la surcharge.',
        ],
        'zoom_link' => [
            'title' => 'Zoom sans lien',
            'found' => 'Une image agrandie sous la souris n’est pas un lien et n’en contient pas : le clavier ne peut pas l’ouvrir.',
            'why' => 'Le clavier et les lecteurs d’écran n’ont pas de survol : le lien vers l’image, ouvert dans la visionneuse, est leur seul moyen de la voir de plus près.',
            'fix' => 'La vue du paquet fait de l’image un lien vers l’original : une surcharge de webx-widgets::components.zoom dans le thème l’a perdu, ou data-webx-zoom est écrit sur une image nue. Placez l’attribut sur le lien de visionneuse de l’image, ou retirez la surcharge.',
        ],
    ],
];
