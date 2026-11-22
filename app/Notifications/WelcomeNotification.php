<?php

namespace App\Notifications;

use App\Models\SettingManagement\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject('Welcome to '.SiteSetting::current()->get('name'))
            ->greeting('Welcome, '.$notifiable->name.'!')
            ->line('Your email address is verified and your account is ready to use.')
            ->action('Go to your dashboard', route('dashboard'))
            ->line('Thank you for joining '.SiteSetting::current()->get('name').'.');
    }
}
