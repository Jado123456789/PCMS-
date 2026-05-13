<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $to, string $message): bool
    {
        $phone = $this->normalizePhone($to);

        if ($phone === null) {
            Log::warning('SMS not sent because the phone number is invalid.', ['to' => $to]);
            return false;
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => env('MISTA_SMS_URL', 'https://api.mista.io/sms'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => [
                'to' => $phone,
                'from' => env('MISTA_SMS_FROM', 'E-Notifier'),
                'unicode' => '0',
                'sms' => $message,
                'action' => 'send-sms',
            ],
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . env('MISTA_SMS_API_KEY', '596|ytiiNvtEma7SUNEbVoJhHHzMqXrTjvOuQkY8JHvX'),
            ],
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($response === false || $error) {
            Log::error('SMS gateway request failed.', [
                'to' => $phone,
                'error' => $error,
            ]);
            return false;
        }

        if ($statusCode >= 400) {
            Log::error('SMS gateway returned an error response.', [
                'to' => $phone,
                'status_code' => $statusCode,
                'response' => $response,
            ]);
            return false;
        }

        Log::info('SMS sent successfully.', [
            'to' => $phone,
            'status_code' => $statusCode,
        ]);

        return true;
    }

    private function normalizePhone(string $phone): ?string
    {
        $normalized = preg_replace('/\D+/', '', $phone);

        if (! $normalized) {
            return null;
        }

        if (str_starts_with($normalized, '0')) {
            $normalized = '25' . $normalized;
        }

        return strlen($normalized) >= 10 ? $normalized : null;
    }
}
