<?php

namespace App\Notifications\Admin;

use App\Models\Admin\BasicSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NewUserNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public $data;
    public $password;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($data,$password)
    {
        $this->data     = $data;
        $this->password     = $password;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $basic_settings     = BasicSettings::first();
        $data               = $this->data;
        $password           = $this->password;

        return (new MailMessage)
            ->subject("Your " . $basic_settings->site_name . " Account Has Been Successfully Created")
            ->greeting(__("Dear")." ".$data['firstname'] . ' ' . $data['lastname'] ." ,")
            ->line("We are pleased to inform you that your account has been successfully created on the ". $basic_settings->site_name ." platform.")
            ->line("Account Details:")
            ->line(__("Email").": " . $data['email'])
            ->line(__("Password").": " . $password)
            ->line(__("Login URL").": " . url('/login'));

    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
