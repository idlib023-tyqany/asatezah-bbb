<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('رمز التحقق - أساتذة')
            ->greeting('مرحباً بك في أساتذة')
            ->line('استخدم الرمز التالي لتأكيد بيانات التواصل:')
            ->line($this->code)
            ->line('تنتهي صلاحية الرمز خلال 10 دقائق. لا تشاركه مع أي شخص.');
    }
}
