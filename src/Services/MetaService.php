<?php

namespace Framio\SocialShare\Services;

use GuzzleHttp\Client;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;

class MetaService
{
    protected $client;
    protected $baseUrl = 'https://graph.facebook.com/v19.0'; 

    public function __construct()
    {
        $this->client = new Client();
    }

    public function publish($message, $images, $pageId, $igId, $userToken, $scheduleTime = null, $userId = 1, $isCronJob = false)
    {
        if ($scheduleTime && !$isCronJob) {
            return $this->saveToQueue($userId, $message, $images, $pageId, $igId, $userToken, $scheduleTime);
        }

        $this->log("--- ANLIK PAYLAŞIM BAŞLADI ---");
        
        $errors = [];
        $successData = [];

        $pageToken = $this->getPageAccessToken($pageId, $userToken);
        $finalToken = $pageToken ?: $userToken;

        // --- FACEBOOK ---
        if ($pageId) {
            try {
                $fbResult = $this->postToFacebook($pageId, $message, $images, $finalToken);
                $successData['facebook'] = $fbResult;
                $this->log("FB Başarılı ID: " . ($fbResult['id'] ?? 'Yok'));
            } catch (\Exception $e) {
                $this->log("FB Hatası: " . $e->getMessage());
            }
        }

        // --- INSTAGRAM ---
        if ($igId) {
            try {
                $igResult = $this->postToInstagram($igId, $message, $images, $finalToken);
                $successData['instagram'] = $igResult;
                $this->log("IG Başarılı ID: " . ($igResult['id'] ?? 'Yok'));
            } catch (\Exception $e) {
                $err = "IG Hatası: " . $e->getMessage();
                $errors[] = $err;
                $this->log($err);
            }
        }

        if (empty($successData) && !empty($errors)) {
            throw new \Exception(implode(', ', $errors));
        }

        return $successData;
    }

    protected function saveToQueue($userId, $message, $images, $pageId, $igId, $userToken, $scheduleTime)
    {
        try {
            $date = new \DateTime($scheduleTime, new \DateTimeZone('Europe/Istanbul'));
            $formattedTime = $date->format('Y-m-d H:i:s');

            $payload = json_encode([
                'message' => $message,
                'images' => $images,
                'pageId' => $pageId,
                'igId' => $igId,
                'userToken' => $userToken
            ]);

            DB::table('framio_social_queue')->insert([
                'user_id' => $userId,
                'payload' => $payload,
                'status' => 'pending',
                'scheduled_at' => $formattedTime
            ]);

            $this->log("Gönderi yerel veritabanına zamanlandı: $formattedTime");
            
            return ['status' => 'queued', 'scheduled_at' => $formattedTime, 'message' => 'Sistem kuyruğuna alındı.'];

        } catch (\Exception $e) {
            $this->log("Veritabanı Kayıt Hatası: " . $e->getMessage());
            throw new \Exception("Zamanlama veritabanına kaydedilemedi: " . $e->getMessage());
        }
    }

    protected function log($msg) {
        $time = Carbon::now('Europe/Istanbul')->format('Y-m-d H:i:s');
        resolve('log')->info("[Framio Queue System] " . $msg);
    }

    protected function getPageAccessToken($pageId, $userToken)
    {
        try {
            if (!$pageId) return null;
            $response = $this->client->get("{$this->baseUrl}/{$pageId}", [
                'query' => ['fields' => 'access_token', 'access_token' => $userToken]
            ]);
            $data = json_decode($response->getBody(), true);
            return $data['access_token'] ?? null;
        } catch (\Exception $e) { return null; }
    }

    protected function postToFacebook($pageId, $message, $images, $token)
    {
        if (count($images) === 1) {
            $params = ['url' => $images[0], 'message' => $message, 'access_token' => $token, 'published' => true];
            $response = $this->client->post("{$this->baseUrl}/{$pageId}/photos", ['form_params' => $params]);
            return json_decode($response->getBody(), true);
        }
        $mediaIds = [];
        foreach ($images as $imgUrl) {
            $params = ['url' => $imgUrl, 'published' => false, 'access_token' => $token];
            $res = $this->client->post("{$this->baseUrl}/{$pageId}/photos", ['form_params' => $params]);
            $data = json_decode($res->getBody(), true);
            if (isset($data['id'])) $mediaIds[] = ['media_fbid' => $data['id']];
        }
        $feedParams = ['message' => $message, 'attached_media' => $mediaIds, 'access_token' => $token, 'published' => true];
        $response = $this->client->post("{$this->baseUrl}/{$pageId}/feed", ['json' => $feedParams]);
        return json_decode($response->getBody(), true);
    }

    // --- INSTAGRAM AKILLI BEKLEME (RETRY) SİSTEMİ EKLENDİ ---
    protected function postToInstagram($igId, $message, $images, $token)
    {
        $creationId = null;
        if (count($images) === 1) {
            $containerParams = ['image_url' => $images[0], 'caption' => $message, 'access_token' => $token];
            $res = $this->client->post("{$this->baseUrl}/{$igId}/media", ['form_params' => $containerParams]);
            $containerData = json_decode($res->getBody(), true);
            if (!isset($containerData['id'])) throw new \Exception("IG Container Hatası.");
            $creationId = $containerData['id'];
        } else {
            $childIds = [];
            foreach ($images as $imgUrl) {
                $itemParams = ['image_url' => $imgUrl, 'is_carousel_item' => true, 'access_token' => $token];
                $res = $this->client->post("{$this->baseUrl}/{$igId}/media", ['form_params' => $itemParams]);
                $data = json_decode($res->getBody(), true);
                if (isset($data['id'])) $childIds[] = $data['id'];
            }
            $carouselParams = ['media_type' => 'CAROUSEL', 'children' => implode(',', $childIds), 'caption' => $message, 'access_token' => $token];
            $res = $this->client->post("{$this->baseUrl}/{$igId}/media", ['form_params' => $carouselParams]);
            $data = json_decode($res->getBody(), true);
            $creationId = $data['id'] ?? null;
        }

        if (!$creationId) {
            throw new \Exception("Instagram Media ID oluşturulamadı.");
        }

        // AKILLI DÖNGÜ: Instagram resimleri sunucularında işlemeyi anında bitirmez.
        // Eski koddaki düz 'sleep(5)' yerine, Instagram hazır olana kadar soran yapı kurduk.
        $maxRetries = 6; // Toplam 30 saniye tolerans
        $attempt = 0;
        $lastError = "";

        while ($attempt < $maxRetries) {
            $attempt++;
            sleep(5); // Her denemede 5 saniye bekle

            try {
                $publishParams = ['creation_id' => $creationId, 'access_token' => $token];
                $res = $this->client->post("{$this->baseUrl}/{$igId}/media_publish", ['form_params' => $publishParams]);
                return json_decode($res->getBody(), true); // Başarılıysa döngüden çık
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                // Hata aldıysa (Media not ready) döngü devam eder, bir 5 saniye daha bekler.
            }
        }

        throw new \Exception("Instagram Yayınlama Hatası (İşlem Zaman Aşımı): " . $lastError);
    }
    
    // --- GÖNDERİ GÜNCELLEME (Facebook Only) ---
    public function updateFacebookPost($postId, $message, $token)
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/{$postId}", [
                'form_params' => [
                    'message' => $message,
                    'access_token' => $token
                ]
            ]);
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $this->log("Facebook Güncelleme Hatası: " . $e->getMessage());
            throw new \Exception("Facebook güncellenemedi: " . $e->getMessage());
        }
    }
}