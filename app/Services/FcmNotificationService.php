<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmNotificationService
{
    /**
     * Never throws — callers (APIs, jobs, admin) must keep working if push fails.
     */
    public function send($data)
    {
        try {
            ini_set('max_execution_time', -1);

            $title = $data->title ?? '';
            $body = $data->body ?? '';
            $notification_type = $data->notification_type ?? '';
            $fcm_device_token = $data->fcm_device_token ?? '';
            $topic = $data->topic ?? '';

            if (! $topic && ! $fcm_device_token) {
                return null;
            }

            $serviceAccountPath = $this->resolveServiceAccountPath();
            if (! $serviceAccountPath) {
                return null;
            }

            $custom_sound = 'custom_tone.mp3';
            $token_or_topic = $topic ? 'topic' : 'token';

            $message = [
                'message' => [
                    $token_or_topic => $topic ?: $fcm_device_token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'id' => '1',
                        'status' => 'done',
                        'type' => $notification_type,
                    ],
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'high_importance_channel',
                            'sound' => $custom_sound,
                        ],
                    ],
                ],
            ];

            $access_token = $this->getAccessToken($serviceAccountPath);
            if (! $access_token) {
                return null;
            }

            $fcm_project_id = (string) config('services.fcm.project_id', '');
            if ($fcm_project_id === '') {
                Log::warning('[FCM] FCM_PROJECT_ID missing from config');

                return null;
            }

            $url = "https://fcm.googleapis.com/v1/projects/{$fcm_project_id}/messages:send";

            return Http::withHeaders([
                'Authorization' => 'Bearer '.$access_token,
                'Content-Type' => 'application/json',
            ])->post($url, $message);
        } catch (\Throwable $e) {
            Log::warning('[FCM] send failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function getAccessToken($serviceAccountPath)
    {
        try {
            if (! is_string($serviceAccountPath) || ! is_file($serviceAccountPath)) {
                Log::warning('[FCM] service account path is not a file', ['path' => $serviceAccountPath]);

                return null;
            }

            $jsonKey = json_decode((string) file_get_contents($serviceAccountPath), true);
            if (! is_array($jsonKey) || empty($jsonKey['client_email']) || empty($jsonKey['private_key'])) {
                Log::warning('[FCM] invalid service account JSON', ['path' => $serviceAccountPath]);

                return null;
            }

            $header = ['alg' => 'RS256', 'typ' => 'JWT'];
            $claimSet = [
                'iss' => $jsonKey['client_email'],
                'scope' => 'https://www.googleapis.com/auth/cloud-platform',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => time(),
                'exp' => time() + 3600,
            ];

            $jwtHeader = base64_encode(json_encode($header));
            $jwtClaimSet = base64_encode(json_encode($claimSet));

            $signingInput = $jwtHeader.'.'.$jwtClaimSet;
            $signature = '';
            openssl_sign($signingInput, $signature, $jsonKey['private_key'], 'SHA256');
            $jwtSignature = base64_encode($signature);

            $jwt = $jwtHeader.'.'.$jwtClaimSet.'.'.$jwtSignature;

            $postData = [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

            $response = curl_exec($ch);
            curl_close($ch);

            $responseData = json_decode((string) $response, true);

            return $responseData['access_token'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('[FCM] getAccessToken failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function resolveServiceAccountPath(): ?string
    {
        $relative = trim((string) config('services.fcm.service_account_path', ''));
        if ($relative === '') {
            $relative = trim((string) env('FCM_SERVICE_ACCOUNT_PATH', ''));
        }

        if ($relative === '') {
            Log::warning('[FCM] FCM_SERVICE_ACCOUNT_PATH is empty');

            return null;
        }

        $full = str_starts_with($relative, DIRECTORY_SEPARATOR)
            ? $relative
            : base_path($relative);

        if (! is_file($full)) {
            Log::warning('[FCM] service account file missing or not a file', [
                'path' => $full,
                'is_dir' => is_dir($full),
            ]);

            return null;
        }

        return $full;
    }
}
