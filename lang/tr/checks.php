<?php

declare(strict_types=1);

return [
    'widgets' => [
        'before_consent' => [
            'title' => 'Onaydan önce yüklenen üçüncü taraflar',
            'found' => 'Bir video oynatıcı, harita, sayaç veya piksel, ziyaretçi çerez bannerına yanıt vermeden önce sayfa yüklenirken istenir.',
            'why' => 'AB’de çerez bırakan veya ziyaretçinin adresini alan bir üçüncü taraf yalnızca kategorisine onay verildikten sonra yüklenebilir. Banner sorar, ama sayfa isteği çoktan göndermiştir.',
            'fix' => 'Kendiliğinden bekleyen video veya harita bloğunu kullanın ya da kodu <x-webx-consent category="…"> ile sarın. İçeriğe yapıştırılmışsa düzeltme düğmesi onu bekletir: iframe data-src, script type="text/plain", ikisi de data-webx-consent alır.',
        ],
        'banner_off' => [
            'title' => 'Çerez bannerı kapalı ama sitede üçüncü taraflar var',
            'found' => 'Çerez bannerı kapalı ve sitede bir video, harita, sayaç ya da onay beklemesi işaretlenmiş başka bir şey var.',
            'why' => 'Banner kapalıyken üçüncü taraflara ait her şey her ziyaretçide sormadan yüklenir. Buna yalnızca onaya ihtiyacı olmayan bir site izinlidir: AB dışında ve oradan ziyaretçisi olmayan.',
            'fix' => 'Bannerı Ayarlar › Cookie bölümünde açın (düzeltme düğmesi bunu yapar). Site gerçekten bannera ihtiyaç duymuyorsa bu bulguyu gerekçesiyle gizleyin.',
        ],
        'lightbox_size' => [
            'title' => 'Görsel boyutu olmayan lightbox bağlantıları',
            'found' => 'Görseli lightboxta açan bir bağlantının data-width ve data-height değeri yok.',
            'why' => 'Boyut olmadan lightbox açılmadan önce görseli ölçmek için tamamını indirir ve görsel yerine sıçrar.',
            'fix' => 'Kütüphaneden bir görselle <x-webx-lightbox :image> kullanın — boyutu yazar — ya da bağlantıya tam görselin data-width ve data-height değerlerini ekleyin.',
        ],
        'slider_pause' => [
            'title' => 'Duraklat düğmesi olmayan hareketli sliderlar',
            'found' => 'Bir slider kendiliğinden hareket ediyor — otomatik oynatma veya akan şerit — ve duraklat düğmesi yok.',
            'why' => 'Beş saniyeden uzun hareket eden içerik durdurulabilmelidir (WCAG 2.2.2): dikkat dağıtır ve bazı ziyaretçiler onu hiç okuyamaz.',
            'fix' => 'Paketin görünümünde düğme her zaman vardır: temadaki webx-widgets::components.slider geçersiz kılması .webx-slider__pause öğesini kaybetmiş. Geri ekleyin ya da geçersiz kılmayı kaldırın.',
        ],
        'contact_both' => [
            'title' => 'Aynı sayfada hızlı iletişim düğmesi ve alt çubuk',
            'found' => 'Sayfada hem <x-webx-contact-button> hem de <x-webx-contact-bar> var.',
            'why' => 'Aynı aramaları ve sohbetleri iki kez sunarlar, telefonda düğme çubuğun üstüne biner.',
            'fix' => 'Temanın düzeninde ikisinden birini bırakın.',
        ],
    ],
];
