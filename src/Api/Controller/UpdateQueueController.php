<?php

namespace Framio\SocialShare\Api\Controller;

use Flarum\Http\RequestUtil;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Illuminate\Database\Capsule\Manager as DB;
use Framio\SocialShare\Services\MetaService;

class UpdateQueueController implements RequestHandlerInterface
{
    protected $metaService;

    public function __construct(MetaService $metaService)
    {
        $this->metaService = $metaService;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $body = $request->getParsedBody();
        $id = $body['id'] ?? null;
        $action = $body['action'] ?? null;
        $newMessage = $body['message'] ?? null;

        if (!$id) return new JsonResponse(['error' => 'ID gerekli'], 400);

        // DÜZELTME: Tablo adından "flhg_" öneki kaldırıldı. Flarum bunu kendisi ekleyecek.
        $record = DB::table('framio_social_queue')->find($id);
        if (!$record) return new JsonResponse(['error' => 'Kayıt bulunamadı'], 404);

        if ($action === 'delete') {
            DB::table('framio_social_queue')->delete($id);
            return new JsonResponse(['status' => 'deleted']);
        }

        if ($action === 'update' && $newMessage) {
            $payload = json_decode($record->payload, true);
            $syncMessage = "İçerik kuyrukta güncellendi.";

            // --- CANLI FACEBOOK SENKRONİZASYONU ---
            if ($record->status === 'success' && !empty($record->response_log)) {
                $log = json_decode($record->response_log, true);
                
                $fbPostId = $log['facebook']['id'] ?? null;
                $token = $payload['userToken'] ?? null;

                if ($fbPostId && $token) {
                    try {
                        $this->metaService->updateFacebookPost($fbPostId, $newMessage, $token);
                        $syncMessage = "İçerik hem burada hem de Facebook üzerinde anında güncellendi!";
                    } catch (\Exception $e) {
                        $syncMessage = "Yerel veri güncellendi ancak Facebook API reddetti: " . $e->getMessage();
                    }
                }
            }

            // --- YEREL VERİTABANINI GÜNCELLE ---
            $payload['message'] = $newMessage;
            
            DB::table('framio_social_queue')
                ->where('id', $id)
                ->update([
                    'payload' => json_encode($payload),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            return new JsonResponse(['status' => 'updated', 'message' => $syncMessage]);
        }

        return new JsonResponse(['error' => 'Geçersiz işlem'], 400);
    }
}