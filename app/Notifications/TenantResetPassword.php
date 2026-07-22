<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Surel pemulihan kata sandi untuk pengguna tenant (owner & staf).
 *
 * Notifikasi sendiri, bukan bawaan Laravel, semata agar isinya berbahasa
 * Indonesia seperti seluruh antarmuka lain. Tautannya memakai rute tenant
 * (`password.reset`) — berbeda dari padanannya di panel platform.
 */
class TenantResetPassword extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $minutes = config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi — SAPI')
            ->greeting('Halo,')
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun SAPI Anda.')
            ->action('Atur Ulang Kata Sandi', $url)
            ->line("Tautan ini kedaluwarsa dalam {$minutes} menit.")
            ->line('Jika Anda tidak meminta ini, abaikan surel ini — kata sandi Anda tidak berubah.')
            ->salutation('Terima kasih.');
    }
}
