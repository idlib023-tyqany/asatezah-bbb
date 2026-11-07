<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerificationChallenge;
use App\Notifications\VerificationCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class VerificationChallengeService
{
    public function __construct(private readonly SmsVerificationSender $smsSender) {}

    /**
     * @param  array<int, string>  $channels
     */
    public function issue(User $user, string $purpose, array $channels): VerificationChallenge
    {
        $emailCode = in_array('email', $channels, true) ? $this->newCode() : null;
        $phoneCode = in_array('phone', $channels, true) ? $this->newCode() : null;

        $challenge = DB::transaction(function () use ($user, $purpose, $channels, $emailCode, $phoneCode): VerificationChallenge {
            VerificationChallenge::query()
                ->where('expires_at', '<=', now())
                ->delete();
            VerificationChallenge::query()
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->delete();

            return VerificationChallenge::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'purpose' => $purpose,
                'email' => in_array('email', $channels, true) ? $user->email : null,
                'phone_number' => in_array('phone', $channels, true) ? $user->phone_number : null,
                'email_code_hash' => $emailCode === null ? null : Hash::make($emailCode),
                'phone_code_hash' => $phoneCode === null ? null : Hash::make($phoneCode),
                'channels' => $channels,
                'expires_at' => now()->addMinutes(10),
            ]);
        });

        try {
            $this->deliver($challenge, $emailCode, $phoneCode);
        } catch (\Throwable $exception) {
            $challenge->delete();

            throw $exception;
        }

        return $challenge;
    }

    public function resend(VerificationChallenge $challenge): void
    {
        if ($challenge->resends >= 3) {
            abort(429, 'تم تجاوز عدد مرات إعادة إرسال الرمز.');
        }

        $emailCode = in_array('email', $challenge->channels, true) ? $this->newCode() : null;
        $phoneCode = in_array('phone', $challenge->channels, true) ? $this->newCode() : null;

        DB::transaction(function () use ($challenge, $emailCode, $phoneCode): void {
            $challenge->update([
                'email_code_hash' => $emailCode === null ? null : Hash::make($emailCode),
                'phone_code_hash' => $phoneCode === null ? null : Hash::make($phoneCode),
                'attempts' => 0,
                'resends' => $challenge->resends + 1,
                'expires_at' => now()->addMinutes(10),
            ]);
        });

        $this->deliver($challenge->fresh(), $emailCode, $phoneCode);
    }

    public function response(VerificationChallenge $challenge): array
    {
        return [
            'verification_id' => $challenge->id,
            'purpose' => $challenge->purpose,
            'channels' => $challenge->channels,
            'destinations' => [
                'email' => $challenge->email === null ? null : $this->maskEmail($challenge->email),
                'phone' => $challenge->phone_number === null ? null : $this->maskPhone($challenge->phone_number),
            ],
        ];
    }

    private function deliver(
        VerificationChallenge $challenge,
        ?string $emailCode,
        ?string $phoneCode,
    ): void {
        if ($emailCode !== null && $challenge->email !== null) {
            Notification::route('mail', $challenge->email)
                ->notify(new VerificationCodeNotification($emailCode));
        }

        if ($phoneCode !== null && $challenge->phone_number !== null) {
            $this->smsSender->send($challenge->phone_number, $phoneCode);
        }
    }

    private function newCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = explode('@', $email, 2);

        return substr($name, 0, 1).str_repeat('*', max(1, strlen($name) - 1)).'@'.$domain;
    }

    private function maskPhone(string $phoneNumber): string
    {
        return str_repeat('*', max(0, strlen($phoneNumber) - 4)).substr($phoneNumber, -4);
    }
}
