<?php

namespace App\Notifications;

use App\Models\EmergencyPanic;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class EmergencyPanicNotification extends Notification
{
    use Queueable;

    public function __construct(public EmergencyPanic $panic, public string $actorName) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'panic_id' => $this->panic->id,
            'resident_id' => $this->panic->resident_id,
            'title' => 'Alarm Darurat',
            'excerpt' => Str::limit($this->panic->reason ?? 'Ada warga yang membutuhkan bantuan segera.', 140),
            'reason' => $this->panic->reason,
            'meta' => $this->panic->meta,
            'actor' => $this->actorName,
            'actor_name' => $this->actorName,
            'created_at' => $this->panic->created_at->toDateTimeString(),
        ];
    }

    // public function toMail(MailMessage $mail): MailMessage
    // {
    //     return $mail->subject('[PERUMKU] Alert Darurat Dikirim')
    //         ->line('Ada alarm darurat yang dikirim dari akun warga.')
    //         ->line($this->actorName.' mengirim alert darurat.')
    //         ->line('Status: open');
    // }
}
