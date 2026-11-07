<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsVerificationSender
{
    public function send(string $phoneNumber, string $code): void
    {
        $endpoint = (string) config('services.sms.endpoint');
        $token = (string) config('services.sms.token');

        if ($endpoint === '' || $token === '') {
            throw new RuntimeException('SMS delivery is not configured.');
        }

        Http::acceptJson()
            ->withToken($token)
            ->timeout(10)
            ->post($endpoint, [
                'to' => $phoneNumber,
                'message' => "رمز التحقق في أساتذة هو {$code}. تنتهي صلاحيته خلال 10 دقائق.",
                'sender' => config('services.sms.sender'),
            ])
            ->throw();
    }
}
