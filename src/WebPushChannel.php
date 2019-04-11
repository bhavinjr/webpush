<?php

namespace NotificationChannels\WebPush;

use Minishlink\WebPush\WebPush;
use Illuminate\Notifications\Notification;
use convertifier\Models\NotificationLog;
use convertifier\Models\Subscriber;

class WebPushChannel
{
    /** @var \Minishlink\WebPush\WebPush */
    protected $webPush;

    /**
     * @param  \Minishlink\WebPush\WebPush $webPush
     * @return void
     */
    public function __construct(WebPush $webPush)
    {
        $this->webPush = $webPush;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed $notifiable
     * @param  \Illuminate\Notifications\Notification $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        $payload = json_encode($notification->toWebPush($notifiable, $notification)->toArray());
        $rawPublicKey  = str_replace(' ', '+', $notifiable->public_key);

        $this->webPush->sendNotification(
            $notifiable->browser_id,
            $payload,
            $rawPublicKey,
            $notifiable->auth_token
        );

        $response = $this->webPush->flush();

        $this->afterPush($notifiable);
        $this->deleteInvalidSubscriptions($response, $notifiable);
    }

    protected function afterPush($notifiable)
    {
        try {
            NotificationLog::where('push_status', 0)
                            ->where('id', $notifiable->id)
                            ->update(['push_status' => 1]);
        } catch (\Exception $ex) {
            \Log::warning('warning '.$ex->getMessage());
        }
    }

    protected function deleteInvalidSubscriptions($response, $notifiable)
    {
        if (! is_array($response)) {
            return;
        }

        try {
            Subscriber::where('browser_id', $notifiable->browser_id)
                        ->delete();
        } catch (\Exception $ex) {
            \Log::warning('warning '.$ex->getMessage());
        }
    }
}
