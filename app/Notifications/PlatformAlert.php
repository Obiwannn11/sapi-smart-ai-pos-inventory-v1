<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Peringatan operasional untuk pemilik SaaS.
 *
 * Satu kelas untuk semua jenis peringatan, dengan isi yang dirakit pemanggil.
 * Membuat satu notifikasi per jenis akan berakhir jadi selusin kelas yang
 * berbeda hanya di kalimatnya.
 *
 * Nadanya sengaja datar. Peringatan yang berteriak akan dibaca sebagai
 * berteriak juga saat kejadiannya ternyata biasa saja — dan setelah beberapa
 * kali, tidak dibaca sama sekali.
 */
class PlatformAlert extends Notification
{
    use Queueable;

    /**
     * @param  list<string>  $lines  Rincian, satu baris satu fakta.
     */
    public function __construct(
        public string $subject,
        public string $summary,
        public array $lines = [],
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
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
        $message = (new MailMessage)
            ->subject("[SAPI] {$this->subject}")
            ->greeting('Halo,')
            ->line($this->summary);

        foreach ($this->lines as $line) {
            $message->line($line);
        }

        if ($this->actionUrl !== null) {
            $message->action($this->actionLabel ?? 'Buka Panel Platform', $this->actionUrl);
        }

        return $message
            ->line('Peringatan ini dikirim otomatis. Ambang dan jedanya diatur di config/platform-alerts.php.')
            ->salutation('— Sistem SAPI');
    }
}
