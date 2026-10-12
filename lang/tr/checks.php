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
        'video_pause' => [
            'title' => 'Duraklatma düğmesi olmayan arka plan videoları',
            'found' => 'Bir arka plan videosu kendiliğinden oynuyor ve duraklatma düğmesi yok.',
            'why' => 'Beş saniyeden uzun süren hareketin durdurulabilmesi gerekir (WCAG 2.2.2): dikkat dağıtır ve bazı ziyaretçiler üzerindeki metni okuyamaz.',
            'fix' => 'Paketin görünümünde düğme her zaman vardır: temadaki webx-widgets::components.video geçersiz kılması .webx-video__pause öğesini kaybetmiş. Geri ekleyin ya da geçersiz kılmayı kaldırın.',
        ],
        'counter_number' => [
            'title' => 'Sayısı olmayan sayaçlar',
            'found' => 'Bir sayacın işaretlemesinde saydığı sayı yok.',
            'why' => 'Arama motorları, ekran okuyucular ve JavaScript’siz bir sayfa işaretlemeyi okur: sayı yerine sıfır ya da hiçbir şey alır.',
            'fix' => 'Paketin görünümü son sayıyı yazar ve betik ona kadar sayar: temadaki webx-widgets::components.counter geçersiz kılması başka bir şey yazıyor. Sayıyı yazın ya da geçersiz kılmayı kaldırın.',
        ],
        'compare_range' => [
            'title' => 'Kaydırıcısız önce ve sonra',
            'found' => 'Bir önce-sonra ayırıcısının range alanı yok: onu yalnızca fare ve parmak hareket ettirebilir.',
            'why' => 'Klavye ayırıcıya ulaşamaz, ekran okuyucu da onu adlandıramaz: resmin bir kısmı bu ziyaretçilerden gizli kalır.',
            'fix' => 'Paketin görünümü ayırıcıyı bir <input type="range"> yapar: temadaki webx-widgets::components.compare geçersiz kılması onu kaybetmiş. Geri ekleyin ya da geçersiz kılmayı kaldırın.',
        ],
        'toc_target' => [
            'title' => 'Hiçbir yere gitmeyen içindekiler',
            'found' => 'İçindekiler bağlantısı, sayfada olmayan bir bölüme gidiyor.',
            'why' => 'Ziyaretçi bir bölüme tıklar ve hiçbir şey olmaz: sayfa kımıldamaz, liste bozuk görünür.',
            'fix' => 'Sunucu listeyi sayfanın başlıklarından kurar ve onlara id verir. Elle yazılmış bir liste ya da temadaki liste geçersiz kılması artık olmayan bir id’yi gösteriyor: <x-webx-toc> kullanın ya da bağlantıyı düzeltin.',
        ],
        'load_more_link' => [
            'title' => 'Sonraki sayfaya bağlantısı olmayan “Daha fazla göster”',
            'found' => '“Daha fazla göster” listesinin sonraki bir sayfası var ama ona bağlantı yok: oraya yalnızca düğmesi götürüyor.',
            'why' => 'Arama motorları düğmelere basmaz, JavaScript olmayan bir sayfa da basamaz: ilk sayfadan sonrakiler bulunmaz.',
            'fix' => 'Paket sayfa bağlantılarını listenin altına basar, düğme yalnızca onları örter: temadaki webx-widgets::components.load-more geçersiz kılması ya da boş bir links yuvası onları kaybetti. Geri koyun ya da geçersiz kılmayı kaldırın.',
        ],
        'zoom_link' => [
            'title' => 'Bağlantısız yakınlaştırma',
            'found' => 'Fareyle büyütülen bir görsel bağlantı değil ve bağlantı içermiyor: klavye onu açamaz.',
            'why' => 'Klavye ve ekran okuyucuların üzerine gelme hareketi yoktur: görsele giden ve görüntüleyicide açılan bağlantı, onu yakından görmenin tek yoludur.',
            'fix' => 'Paketin görünümü görseli orijinale bağlantı yapar: temadaki webx-widgets::components.zoom geçersiz kılması bunu kaybetti ya da data-webx-zoom çıplak bir görsele yazıldı. Özniteliği görselin görüntüleyici bağlantısına koyun veya geçersiz kılmayı kaldırın.',
        ],
        'form_script' => [
            'title' => 'Betiği olmayan bir form',
            'found' => 'Yerinde gönderilen bir form, form.js yüklemeyen bir sayfada duruyor: yanıt vermek için sayfayı yeniden yükler ve onu yalnızca sunucu denetler.',
            'why' => 'Betik olmadan ziyaretçi koca bir sayfayı bekler, kaydırdığı yeri kaybeder ve bir hatayı ancak gönderdikten sonra öğrenir.',
            'fix' => 'Betikleri düzen yazdırır: @webxTheme ile, temasız bir sitede ise </body> öncesine @webxWidgets ile; ardından php artisan webx:theme:sync. Betikleri kendisi paketleyen bir site bunu yok sayabilir.',
        ],
    ],
];
