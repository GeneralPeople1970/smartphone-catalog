<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The verification code mail. Replaces the framework's link-based VerifyEmail
 * notification; it is sent from App\Services\EmailVerification, which User
 * routes sendEmailVerificationNotification() to.
 *
 * Not queued on purpose: registration reports a mailer outage back to the form,
 * which only works while the send is still part of the request.
 */
class VerifyEmailCode extends Notification
{
    public function __construct(
        // Public so tests can read back the code that was actually mailed.
        public readonly string $code,
        public readonly int $expiresInMinutes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            // The code is in the subject too: most clients show it in the list,
            // which saves opening the mail at all.
            ->subject('邮箱验证码 '.$this->code.' - '.config('app.name', '智能手机参数站'))
            ->greeting('你好！')
            ->line('你正在验证邮箱地址 '.$notifiable->email.'，验证码是：')
            ->line('　　'.$this->code)
            ->line('请在注册页面输入这个验证码完成验证。验证码 '.$this->expiresInMinutes.' 分钟内有效，请勿转发给他人。')
            ->line('如果这不是你的操作，忽略本邮件即可。');
    }
}
