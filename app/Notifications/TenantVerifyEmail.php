<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Surel verifikasi alamat untuk pemilik usaha yang baru mendaftar.
 *
 * Mewarisi VerifyEmail bawaan Laravel hanya untuk pembuatan tautan
 * bertanda-tangannya — bagian yang tidak boleh ditulis ulang sendiri — lalu
 * mengganti isinya dengan bahasa Indonesia, seperti yang sudah dilakukan
 * TenantResetPassword.
 */
class TenantVerifyEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $minutes = config('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject('Verifikasi Alamat Email — SAPI POS')
            ->greeting('Halo,')
            ->line('Terima kasih sudah mendaftarkan usaha Anda di SAPI POS.')
            ->line('Satu langkah lagi: pastikan alamat email ini benar milik Anda, supaya kami bisa mengirimkan tagihan dan tautan pemulihan kata sandi ke tempat yang tepat.')
            ->action('Verifikasi Email Saya', $url)
            ->line("Tautan ini kedaluwarsa dalam {$minutes} menit. Bila kedaluwarsa, Anda bisa meminta yang baru dari halaman verifikasi.")
            ->line('Jika Anda tidak merasa mendaftar, abaikan surel ini — tidak ada akun yang bisa dipakai tanpa langkah ini.')
            ->salutation('Terima kasih.');
    }
}
