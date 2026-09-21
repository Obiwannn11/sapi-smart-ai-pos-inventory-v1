<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Surel pemulihan kata sandi untuk akun platform.
 *
 * Notifikasi sendiri, bukan bawaan Laravel: yang bawaan menyusun tautan lewat
 * route('password.reset') — rute milik tenant. Akun platform ada di tabel lain,
 * jadi tautannya harus mengarah ke /platform/reset-password atau penerimanya
 * mendarat di halaman yang tak akan pernah mengenali akunnya.
 */
class PlatformResetPassword extends Notification
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
        $url = url(route('platform.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $minutes = config('auth.passwords.platform_users.expire');

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi — Panel Platform SAPI')
            ->greeting('Halo,')
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun panel platform Anda.')
            ->action('Atur Ulang Kata Sandi', $url)
            ->line("Tautan ini kedaluwarsa dalam {$minutes} menit.")
            ->line('Jika Anda tidak meminta ini, abaikan surel ini — kata sandi Anda tidak berubah.')
            ->salutation('Terima kasih.');
    }
}
