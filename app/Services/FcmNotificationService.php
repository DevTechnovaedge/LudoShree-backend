<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class FcmNotificationService
{

    function send($data)
    {
        ini_set('max_execution_time', -1); // 60 seconds

        $title                              =   $data->title;
        $body                               =   $data->body;
        $notification_type                  =   $data->notification_type;
        $fcm_device_token                   =   $data->fcm_device_token ?? '';
        $topic = $data->topic ?? '';

        // Path to the service account JSON file

        // Path to the service account JSON file
        $serviceAccountPath = base_path(env('FCM_SERVICE_ACCOUNT_PATH'));

        if (!file_exists($serviceAccountPath)) {
            throw new Exception("Service account file not found: " . $serviceAccountPath);
        }

        // The URL to send FCM requests

          // Prepare the notification payload
      // Prepare the notification payload for v1 API
      
                $custom_sound         = "custom_tone.mp3";
                $notification_type == 'instant';
            // if($notification_type == 'instant' || $notification_type == 'schedule' || $notification_type == 'daily'):
            //     $custom_sound         = "custom_tone";
            // endif;
            
            $token_or_topic = $topic ? 'topic' : 'token';

            $message = [
                'message' => [
                    $token_or_topic => $topic ? $topic : $fcm_device_token,
                    // 'registration_ids' => $fcm_device_token,
                    'notification' =>[
                                            'title' => $title,
                                            'body' => $body,
                                        ],
                    'data' => [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'id' => '1',
                        'status' => 'done',
                        'type' => $notification_type
                    ],
                    'android' => [
                        "priority" => "high",
                      'notification' => [
                          "channel_id" => "high_importance_channel",
                          'sound' => $custom_sound,  // Custom sound for Android
                          // 'image' => $image,
                      ],
                  ],
                ],
            ];
            

            // Get the OAuth 2.0 token using the JSON file
            $access_token = $this->getAccessToken($serviceAccountPath);

            $fcm_project_id             =   env('FCM_PROJECT_ID');
            
            $url = "https://fcm.googleapis.com/v1/projects/$fcm_project_id/messages:send";

            // Sending the payload to FCM
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ])->post($url, $message);


        return $response;
    }

    function getAccessToken($serviceAccountPath)
    {
        // Load the service account JSON file
        $jsonKey = json_decode(file_get_contents($serviceAccountPath), true);

        // Prepare the JWT header and claim set
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claimSet = [
            'iss' => $jsonKey['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        // Encode the header and claim set in Base64
        $jwtHeader = base64_encode(json_encode($header));
        $jwtClaimSet = base64_encode(json_encode($claimSet));

        // Create the signature
        $signingInput = $jwtHeader . '.' . $jwtClaimSet;
        $signature = '';
        openssl_sign($signingInput, $signature, $jsonKey['private_key'], 'SHA256');
        $jwtSignature = base64_encode($signature);

        // Generate the JWT token
        $jwt = $jwtHeader . '.' . $jwtClaimSet . '.' . $jwtSignature;

        // Prepare the POST data
        $postData = [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ];

        // Send the request to get the access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

        $response = curl_exec($ch);
        curl_close($ch);

        // Decode and return the access token
        $responseData = json_decode($response, true);
        return $responseData['access_token'];
    }
}
