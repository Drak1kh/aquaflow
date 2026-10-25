<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EscalatedTicketNotification extends Notification
{
    use Queueable;

    public function __construct(public int $ticketId, public string $subject, public string $providerName) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('AquaFlow dispute requires review')
            ->line("{$this->providerName} escalated a support ticket: {$this->subject}")
            ->action('Review ticket', route('admin.tickets.show', $this->ticketId));
    }
}
