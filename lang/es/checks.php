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
        'video_pause' => [
            'title' => 'Vídeos de fondo sin botón de pausa',
            'found' => 'Un vídeo de fondo se reproduce solo y no tiene botón de pausa.',
            'why' => 'El movimiento que dura más de cinco segundos debe poder detenerse (WCAG 2.2.2): distrae, y algunos visitantes no pueden leer el texto que tiene encima.',
            'fix' => 'La vista del paquete siempre tiene el botón: una sobrescritura de webx-widgets::components.video en el tema perdió .webx-video__pause. Vuelva a ponerlo o elimine la sobrescritura.',
        ],
        'counter_number' => [
            'title' => 'Contadores sin su número',
            'found' => 'El marcado de un contador no contiene el número hasta el que cuenta.',
            'why' => 'Los buscadores, los lectores de pantalla y una página sin JavaScript leen el marcado: reciben un cero o nada en lugar del número.',
            'fix' => 'La vista del paquete imprime el número final y el script cuenta hasta él: una sobrescritura de webx-widgets::components.counter en el tema imprime otra cosa. Imprima el número o elimine la sobrescritura.',
        ],
        'compare_range' => [
            'title' => 'Antes y después sin deslizador',
            'found' => 'Un divisor de antes y después no tiene campo range: solo el ratón y el dedo pueden moverlo.',
            'why' => 'El teclado no llega al divisor y un lector de pantalla no puede nombrarlo: parte de la imagen queda oculta para esos visitantes.',
            'fix' => 'La vista del paquete convierte el divisor en un <input type="range">: una sobrescritura de webx-widgets::components.compare en el tema lo perdió. Vuelva a ponerlo o quite la sobrescritura.',
        ],
        'toc_target' => [
            'title' => 'Índice que no lleva a ninguna parte',
            'found' => 'Un enlace del índice lleva a una sección que la página no tiene.',
            'why' => 'El visitante pulsa una sección y no pasa nada: la página no se mueve y el índice parece roto.',
            'fix' => 'El servidor arma el índice con los títulos de la página y les da sus id. Un índice escrito a mano, o una sobrescritura de la lista en el tema, apunta a un id que ya no existe: use <x-webx-toc> o corrija el enlace.',
        ],
        'load_more_link' => [
            'title' => '«Mostrar más» sin enlace a la página siguiente',
            'found' => 'Una lista con «Mostrar más» tiene una página siguiente pero ningún enlace a ella: solo lleva allí su botón.',
            'why' => 'Los buscadores no pulsan botones, y una página sin JavaScript tampoco: lo que viene después de la primera página no se encuentra.',
            'fix' => 'El paquete imprime los enlaces de las páginas bajo la lista y el botón solo los tapa: una sobrescritura de webx-widgets::components.load-more en el tema, o un slot links vacío, los perdió. Vuelva a ponerlos o quite la sobrescritura.',
        ],
        'zoom_link' => [
            'title' => 'Zoom sin enlace',
            'found' => 'Una imagen que se amplía bajo el ratón no es un enlace ni contiene uno: el teclado no puede abrirla.',
            'why' => 'El teclado y los lectores de pantalla no tienen paso del ratón: el enlace a la imagen, abierto en el visor, es su única forma de verla de cerca.',
            'fix' => 'La vista del paquete convierte la imagen en un enlace al original: una sobrescritura de webx-widgets::components.zoom en el tema lo perdió, o data-webx-zoom está en una imagen sin enlace. Ponga el atributo en el enlace del visor de la imagen o quite la sobrescritura.',
        ],
        'form_script' => [
            'title' => 'Un formulario sin su script',
            'found' => 'Un formulario que se envía en el sitio está en una página que no carga form.js: recarga la página para responder y solo lo comprueba el servidor.',
            'why' => 'Sin el script, el visitante espera una página entera, pierde el punto hasta el que se había desplazado y se entera de un error solo después de enviar.',
            'fix' => 'La plantilla imprime los scripts con @webxTheme, o en un sitio sin tema con @webxWidgets antes de </body>; después php artisan webx:theme:sync. Un sitio que empaqueta los scripts por su cuenta puede ignorarlo.',
        ],
    ],
];
