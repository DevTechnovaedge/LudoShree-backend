<?php
// app/Services/SmsService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $apiKey;

    public const OTP_PROVIDER_FAST2SMS = 'fast2sms';

    public const OTP_PROVIDER_VB_HTTP = 'vb_http';

    public function __construct()
    {
        $this->apiKey = env('FAST2SMS_API_KEY');
    }

    /**
     * @param  string|int  $to  10-digit mobile without country code
     * @param  string|int  $message  OTP digits (same as existing Fast2SMS flow)
     */
    public function sendOTP($to, $message)
    {
        $setting = site_setting();
        $provider = $setting->sms_otp_provider ?? self::OTP_PROVIDER_FAST2SMS;

        if ($provider === self::OTP_PROVIDER_VB_HTTP) {
            return $this->sendOtpViaVbHttp((string) $to, (string) $message, $setting);
        }

        return $this->sendOtpViaFast2sms((string) $to, (string) $message);
    }

    protected function sendOtpViaFast2sms(string $to, string $otp)
    {
        $response = Http::get('https://www.fast2sms.com/dev/bulkV2', [
            'authorization' => $this->apiKey,
            'route' => 'dlt',
            'sender_id' => 'MRPVTL',
            'message' => 181410,
            'variables_values' => $otp,
            'numbers' => $to,
            'flash' => 0,
            'language' => 'english',
        ]);

        return $response->json();
    }

    /**
     * VB HTTP GET — only two edits to the saved URL string:
     * 1) `{var}` → OTP digits (inside message=… e.g. …IS%20{var}%20…).
     * 2) The `number=` query value → mobile from the OTP request (digits only, last 10 if longer).
     * Nothing else is substituted (no `{number}`, `{otp}`, `{mobile_number}` braces).
     */
    protected function sendOtpViaVbHttp(string $to, string $otp, $setting): array
    {
        $template = trim((string) ($setting->sms_vb_api_url_template ?? ''));
        if ($template === '') {
            $template = $this->defaultVbUrlTemplate();
        }

        $mobile = preg_replace('/\D/', '', $to);
        if (strlen($mobile) > 10) {
            $mobile = substr($mobile, -10);
        }

        $otpStr = (string) $otp;

        $url = preg_replace('/\{\s*var\s*\}/', $otpStr, $template);

        if (preg_match('/(^|[\?&])number=[^&]*/', $url)) {
            $url = preg_replace('/(^|[\?&])number=[^&]*/', '${1}number='.$mobile, $url, 1);
        } else {
            Log::warning('VB HTTP SMS template missing number= query parameter');
        }

        if (str_contains($url, '{')) {
            Log::warning('VB HTTP SMS URL still contains unreplaced placeholders', [
                'url_excerpt' => substr(preg_replace('/\d/', '0', $url), 0, 160),
            ]);
        }

        $verifySsl = filter_var(env('SMS_VB_HTTP_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $pending = Http::timeout(25)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; LudoShreeSMS/1.0)',
                    'Accept' => '*/*',
                ]);

            if (! $verifySsl) {
                $pending = $pending->withoutVerifying();
            }

            $response = $pending->get($url);

            $raw = $response->body();
            $decoded = $response->json();

            if (! $response->successful()) {
                Log::warning('VB HTTP SMS non-success HTTP status', [
                    'status' => $response->status(),
                    'body_excerpt' => substr($raw, 0, 500),
                ]);
            }

            return is_array($decoded) ? $decoded : ['raw' => $raw, 'http_status' => $response->status()];
        } catch (\Throwable $e) {
            Log::error('VB HTTP SMS exception', [
                'message' => $e->getMessage(),
                'mobile_last4' => substr($mobile, -4),
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Default VB URL — OTP only in `{var}`; recipient always applied via number=… .
     */
    protected function defaultVbUrlTemplate(): string
    {
        return 'https://78.46.58.54/vb/apikey.php?apikey=88QFT9W4swAftCL8&%20senderid=JLPORT&templateid=1407169296535224939&number=0000000000&message=Your%20OTP%20IS%20{var}%20.%20Please%20enter%20this%20to%20verify%20your%20mobile.%20Thank%C2%A0You%C2%A0ORBIT';
    }
}
