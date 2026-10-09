<?php

declare(strict_types=1);

return [
    'widgets' => [
        'before_consent' => [
            'title' => 'Terceros que se cargan antes del consentimiento',
            'found' => 'Un reproductor de vídeo, un mapa, un contador o un píxel se solicita al cargar la página, antes de que el visitante responda al banner de cookies.',
            'why' => 'En la UE, un tercero que instala cookies o recibe la dirección del visitante solo puede cargarse tras el consentimiento a su categoría. El banner pregunta, pero la página ya envió la solicitud.',
            'fix' => 'Use el bloque de vídeo o de mapa, que espera por sí mismo, o envuelva el código en <x-webx-consent category="…">. Pegado en el contenido, la corrección lo hace esperar: un iframe recibe data-src, un script type="text/plain", ambos data-webx-consent.',
        ],
        'banner_off' => [
            'title' => 'Banner de cookies desactivado con terceros en el sitio',
            'found' => 'El banner de cookies está desactivado y el sitio tiene un vídeo, un mapa, un contador u otra cosa marcada para esperar el consentimiento.',
            'why' => 'Sin banner, todo lo de terceros se carga para cada visitante sin preguntar. Solo se permite a un sitio que no necesita consentimiento: fuera de la UE y sin visitantes de allí.',
            'fix' => 'Active el banner en Ajustes › Cookie (la corrección lo hace). Si el sitio de verdad no lo necesita, oculte este hallazgo con el motivo.',
        ],
        'lightbox_size' => [
            'title' => 'Enlaces de lightbox sin tamaño de imagen',
            'found' => 'Un enlace que abre una imagen en el lightbox no tiene data-width ni data-height.',
            'why' => 'Sin tamaño, el lightbox descarga toda la imagen para medirla antes de abrirse, y la imagen salta a su sitio.',
            'fix' => 'Use <x-webx-lightbox :image> con una imagen de la biblioteca —escribe el tamaño— o ponga data-width y data-height de la imagen completa en el enlace.',
        ],
        'slider_pause' => [
            'title' => 'Sliders en movimiento sin botón de pausa',
            'found' => 'Un slider se mueve solo —reproducción automática o cinta continua— y no tiene botón de pausa.',
            'why' => 'El contenido que se mueve más de cinco segundos debe poder detenerse (WCAG 2.2.2): distrae y algunos visitantes no pueden leerlo en absoluto.',
            'fix' => 'La vista del paquete siempre tiene el botón: una sustitución de webx-widgets::components.slider en el tema perdió .webx-slider__pause. Devuélvalo o quite la sustitución.',
        ],
        'contact_both' => [
            'title' => 'Botón de contacto rápido y barra inferior en una página',
            'found' => 'La página tiene <x-webx-contact-button> y <x-webx-contact-bar> a la vez.',
            'why' => 'Ofrecen dos veces las mismas llamadas y chats, y en el teléfono el botón tapa la barra.',
            'fix' => 'Deje solo uno de los dos en la plantilla del tema.',
        ],
    ],
];
