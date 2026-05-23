<?php

namespace Framio\SocialShare\Api\Controller;

use Flarum\Http\RequestUtil;
use Framio\SocialShare\Services\MetaService;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SharePostController implements RequestHandlerInterface
{
    protected $metaService;

    // Servisi içeri alıyoruz (Dependency Injection)
    public function __construct(MetaService $metaService)
    {
        $this->metaService = $metaService;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // 1. Güvenlik: Sadece Adminler
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        // 2. Verileri Al
        $data = $request->getParsedBody();

        $message = $data['message'] ?? '';
        $images = $data['images'] ?? []; // YENİ: Artık dizi olarak alıyoruz
        $pageId = $data['pageId'] ?? null;
        $igId = $data['igId'] ?? null;
        $token = $data['token'] ?? null;
        $scheduleTime = $data['scheduleTime'] ?? null;

        // 3. Basit Doğrulama (Hata mesajlarına dikkat et, eskisiyle farklı)
        if (!$token) {
            return new JsonResponse(['success' => false, 'errors' => ['Erişim Jetonu (Token) eksik.']]);
        }

        if (empty($images)) {
            return new JsonResponse(['success' => false, 'errors' => ['En az bir görsel seçmelisiniz.']]);
        }

        // 4. Servisi Çalıştır
        try {
            $results = $this->metaService->publish($message, $images, $pageId, $igId, $token, $scheduleTime);
            return new JsonResponse(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            // Hatayı loga da yazalım
            resolve('log')->error('[Framio Social Share Error] ' . $e->getMessage());
            
            return new JsonResponse([
                'success' => false, 
                'errors' => [$e->getMessage()] 
            ]);
        }
    }
}