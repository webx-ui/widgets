<?php

declare(strict_types=1);

return [
    'widgets' => [
        'before_consent' => [
            'title' => 'Terceiros que carregam antes do consentimento',
            'found' => 'Um leitor de vídeo, um mapa, um contador ou um pixel é pedido ao carregar a página, antes de o visitante responder ao banner de cookies.',
            'why' => 'Na UE, um terceiro que define cookies ou recebe o endereço do visitante só pode carregar após o consentimento para a sua categoria. O banner pergunta, mas a página já enviou o pedido.',
            'fix' => 'Use o bloco de vídeo ou de mapa, que espera por si, ou envolva o código em <x-webx-consent category="…">. Colado no conteúdo, a correção fá-lo esperar: um iframe recebe data-src, um script type="text/plain", ambos data-webx-consent.',
        ],
        'banner_off' => [
            'title' => 'Banner de cookies desligado com terceiros no site',
            'found' => 'O banner de cookies está desligado e o site tem um vídeo, um mapa, um contador ou outra coisa marcada para esperar o consentimento.',
            'why' => 'Sem banner, tudo o que é de terceiros carrega para cada visitante sem perguntar. Só é permitido a um site que não precisa de consentimento — fora da UE e sem visitantes de lá.',
            'fix' => 'Ligue o banner em Definições › Cookie (a correção faz isso). Se o site realmente não precisa dele, oculte esta constatação com o motivo.',
        ],
        'lightbox_size' => [
            'title' => 'Links de lightbox sem tamanho da imagem',
            'found' => 'Um link que abre uma imagem no lightbox não tem data-width nem data-height.',
            'why' => 'Sem tamanho, o lightbox descarrega a imagem inteira para a medir antes de abrir, e a imagem salta para o lugar.',
            'fix' => 'Use <x-webx-lightbox :image> com uma imagem da biblioteca — escreve o tamanho — ou coloque no link data-width e data-height da imagem completa.',
        ],
        'slider_pause' => [
            'title' => 'Sliders em movimento sem botão de pausa',
            'found' => 'Um slider move-se sozinho — reprodução automática ou faixa contínua — e não tem botão de pausa.',
            'why' => 'Conteúdo que se move por mais de cinco segundos tem de poder ser parado (WCAG 2.2.2): distrai, e alguns visitantes não o conseguem ler de todo.',
            'fix' => 'A vista do pacote tem sempre o botão: uma substituição de webx-widgets::components.slider no tema perdeu .webx-slider__pause. Reponha-o ou remova a substituição.',
        ],
        'contact_both' => [
            'title' => 'Botão de contacto rápido e barra inferior na mesma página',
            'found' => 'A página tem <x-webx-contact-button> e <x-webx-contact-bar> ao mesmo tempo.',
            'why' => 'Oferecem duas vezes as mesmas chamadas e conversas, e no telemóvel o botão tapa a barra.',
            'fix' => 'Mantenha apenas um dos dois no layout do tema.',
        ],
    ],
];
