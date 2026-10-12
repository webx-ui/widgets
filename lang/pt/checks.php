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
        'video_pause' => [
            'title' => 'Vídeos de fundo sem botão de pausa',
            'found' => 'Um vídeo de fundo reproduz-se sozinho e não tem botão de pausa.',
            'why' => 'Um movimento que dura mais de cinco segundos tem de poder ser parado (WCAG 2.2.2): distrai, e alguns visitantes não conseguem ler o texto por cima.',
            'fix' => 'A vista do pacote tem sempre o botão: uma substituição de webx-widgets::components.video no tema perdeu .webx-video__pause. Reponha-o ou remova a substituição.',
        ],
        'counter_number' => [
            'title' => 'Contadores sem o seu número',
            'found' => 'A marcação de um contador não contém o número até ao qual conta.',
            'why' => 'Motores de busca, leitores de ecrã e uma página sem JavaScript leem a marcação: recebem um zero ou nada em vez do número.',
            'fix' => 'A vista do pacote imprime o número final e o script conta até ele: uma substituição de webx-widgets::components.counter no tema imprime outra coisa. Imprima o número ou remova a substituição.',
        ],
        'compare_range' => [
            'title' => 'Antes e depois sem controle deslizante',
            'found' => 'Uma divisória de antes e depois não tem campo range: só o rato e o dedo a podem mover.',
            'why' => 'O teclado não chega à divisória e um leitor de ecrã não a consegue nomear: parte da imagem fica escondida para esses visitantes.',
            'fix' => 'A vista do pacote faz da divisória um <input type="range">: uma substituição de webx-widgets::components.compare no tema perdeu-o. Reponha-o ou remova a substituição.',
        ],
        'toc_target' => [
            'title' => 'Índice que não leva a lado nenhum',
            'found' => 'Uma ligação do índice leva a uma secção que a página não tem.',
            'why' => 'O visitante clica numa secção e nada acontece: a página não se move e o índice parece avariado.',
            'fix' => 'O servidor monta o índice com os títulos da página e dá-lhes os seus id. Um índice escrito à mão, ou uma substituição da lista no tema, aponta para um id que desapareceu: use <x-webx-toc> ou corrija a ligação.',
        ],
        'load_more_link' => [
            'title' => '«Mostrar mais» sem ligação para a página seguinte',
            'found' => 'Uma lista com «Mostrar mais» tem uma página seguinte, mas nenhuma ligação para ela: só o botão leva até lá.',
            'why' => 'Os motores de busca não carregam em botões, e uma página sem JavaScript também não: o que vem depois da primeira página não é encontrado.',
            'fix' => 'O pacote imprime as ligações das páginas por baixo da lista e o botão apenas as cobre: uma substituição de webx-widgets::components.load-more no tema, ou um slot links vazio, perdeu-as. Reponha-as ou retire a substituição.',
        ],
        'zoom_link' => [
            'title' => 'Zoom sem ligação',
            'found' => 'Uma imagem ampliada sob o rato não é uma ligação nem contém uma: o teclado não a consegue abrir.',
            'why' => 'O teclado e os leitores de ecrã não têm passagem do rato: a ligação para a imagem, aberta no visualizador, é a sua única forma de a ver de perto.',
            'fix' => 'A vista do pacote faz da imagem uma ligação para o original: uma substituição de webx-widgets::components.zoom no tema perdeu-a, ou data-webx-zoom foi escrito numa imagem sem ligação. Ponha o atributo na ligação do visualizador ou retire a substituição.',
        ],
        'form_script' => [
            'title' => 'Um formulário sem o seu script',
            'found' => 'Um formulário que envia no local está numa página que não carrega form.js: recarrega a página para responder e só o servidor o verifica.',
            'why' => 'Sem o script, o visitante espera por uma página inteira, perde o ponto até onde rolou e só sabe de um erro depois de enviar.',
            'fix' => 'O layout imprime os scripts com @webxTheme, ou num site sem tema com @webxWidgets antes de </body>; depois php artisan webx:theme:sync. Um site que empacota os scripts por conta própria pode ignorar isto.',
        ],
    ],
];
