<?php

namespace Framio\SocialShare;

use Flarum\Extend;

// --- GÜVENLİ DOSYA YÜKLEME (HATA KORUMALI) ---

// 1. MetaService Kontrolü
$serviceFile = __DIR__ . '/src/Services/MetaService.php';
if (file_exists($serviceFile)) {
    require_once $serviceFile;
}

// 2. Controller Kontrolleri
$controllers = [
    '/src/Api/Controller/SharePostController.php',
    '/src/Api/Controller/TriggerScheduleController.php',
    '/src/Api/Controller/ListQueueController.php',
    '/src/Api/Controller/UpdateQueueController.php'
];

foreach ($controllers as $file) {
    if (file_exists(__DIR__ . $file)) {
        require_once __DIR__ . $file;
    }
}

// Class Importları
use Framio\SocialShare\Api\Controller\SharePostController;
use Framio\SocialShare\Api\Controller\TriggerScheduleController;
use Framio\SocialShare\Api\Controller\ListQueueController;
use Framio\SocialShare\Api\Controller\UpdateQueueController;

$extenders = [
    // Forum (Ön Yüz)
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    // Admin Paneli - (DÜZELTİLDİ: Olmayan CSS dosyası kaldırıldı)
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    // API Rotaları
    (new Extend\Routes('api'))
        // 1. Paylaşım (Forum Modalı)
        ->post('/framio/social-share', 'framio.social-share.post', SharePostController::class)
        
        // 2. Cron Tetikleyici (Sunucu)
        ->get('/framio/social-share/trigger-cron', 'framio.social-share.trigger-cron', TriggerScheduleController::class)

        // 3. Admin Kuyruk Listesi (Yeni)
        ->get('/framio/social-share/queue', 'framio.social-share.list', ListQueueController::class)

        // 4. Admin Kuyruk Güncelleme/Silme (Yeni)
        ->post('/framio/social-share/queue/update', 'framio.social-share.update', UpdateQueueController::class),

    // Ayarlar
    (new Extend\Settings())
        ->serializeToForum('framio-social-share.page_id', 'framio-social-share.page_id')
        ->serializeToForum('framio-social-share.ig_id', 'framio-social-share.ig_id')
        ->serializeToForum('framio-social-share.token', 'framio-social-share.token')
        ->serializeToForum('framio-social-share.post_template', 'framio-social-share.post_template'),
];

// Locale klasörü yoksa siteyi çökertme, sadece atla.
if (is_dir(__DIR__ . '/locale')) {
    $extenders[] = (new Extend\Locales(__DIR__.'/locale'));
}

return $extenders;