<?php

namespace Framio\SocialShare\Api\Controller;

use Flarum\Http\RequestUtil;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Illuminate\Database\Capsule\Manager as DB;

class ListQueueController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Sadece Adminler görebilir
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        // Son eklenen en üstte olacak şekilde getir
        $queue = DB::table('framio_social_queue')
            ->orderBy('created_at', 'desc')
            ->limit(50) // Performans için son 50 kayıt
            ->get();

        // Payload (JSON) verisini array'e çevirip gönderelim
        $formatted = $queue->map(function ($item) {
            $item->payload = json_decode($item->payload);
            return $item;
        });

        return new JsonResponse($formatted);
    }
}