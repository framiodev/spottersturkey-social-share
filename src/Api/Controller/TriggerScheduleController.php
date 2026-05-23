<?php

namespace Framio\SocialShare\Api\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Illuminate\Database\Capsule\Manager as DB;
use Framio\SocialShare\Services\MetaService;
use Carbon\Carbon;

class TriggerScheduleController implements RequestHandlerInterface
{
    protected $metaService;

    public function __construct(MetaService $metaService)
    {
        $this->metaService = $metaService;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // 1. ZAMAN AŞIMINI ÖNLE: Instagram (özellikle çoklu) resimlerini işlemek vakit alır.
        // Sunucunun işlemi yarıda kesmemesi için limiti 5 dakikaya çıkarıyoruz.
        set_time_limit(300);

        // Basit Güvenlik Anahtarı (URL'den gönderilecek)
        $queryParams = $request->getQueryParams();
        if (!isset($queryParams['key']) || $queryParams['key'] !== 'GizliSifreFramio123') {
            return new JsonResponse(['error' => 'Yetkisiz Giris'], 401);
        }

        // Şu anki Türkiye saati
        $now = Carbon::now('Europe/Istanbul')->toDateTimeString();

        // Vakti gelmiş (scheduled_at <= NOW) ve bekleyen (pending) gönderileri bul
        // TABLO ADI: Sadece 'framio_social_queue' (flhg_ öneki otomatik eklenir)
        $posts = DB::table('framio_social_queue')
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', $now)
            ->get();

        if ($posts->isEmpty()) {
            return new JsonResponse(['status' => 'Bekleyen gonderi yok', 'server_time' => $now]);
        }

        $results = [];

        foreach ($posts as $post) {
            $payload = json_decode($post->payload, true);
            
            $message = $payload['message'] ?? '';
            $images = $payload['images'] ?? [];
            $pageId = $payload['pageId'] ?? null;
            $igId = $payload['igId'] ?? null;
            $userToken = $payload['userToken'] ?? null;

            try {
                // MetaService'i çağır ama bu sefer "isCronJob = true" de.
                // Böylece tekrar veritabanına kaydetmez, direkt Meta'ya gönderir.
                $response = $this->metaService->publish(
                    $message,
                    $images,
                    $pageId,
                    $igId,
                    $userToken,
                    null, // Zamanlama yokmuş gibi davran (Anlık at)
                    $post->user_id,
                    true // CRON MODU AKTİF
                );

                // Veritabanını güncelle: BAŞARILI
                DB::table('framio_social_queue')
                    ->where('id', $post->id)
                    ->update([
                        'status' => 'success', 
                        'response_log' => json_encode($response), // Detaylı log kaydı ekledik
                        'updated_at' => Carbon::now('Europe/Istanbul')->toDateTimeString()
                    ]);
                
                $results[] = "ID {$post->id}: Basarili";

            } catch (\Exception $e) {
                // Veritabanını güncelle: HATALI
                DB::table('framio_social_queue')
                    ->where('id', $post->id)
                    ->update([
                        'status' => 'failed', 
                        'response_log' => json_encode(['error' => $e->getMessage()]), // Hatayı sisteme yazdırıyoruz
                        'updated_at' => Carbon::now('Europe/Istanbul')->toDateTimeString()
                    ]);
                
                $results[] = "ID {$post->id}: Hata - " . $e->getMessage();
            }
        }

        return new JsonResponse([
            'status' => 'calisti',
            'server_time' => $now,
            'processed' => count($posts),
            'log' => $results
        ]);
    }
}