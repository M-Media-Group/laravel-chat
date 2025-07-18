<?php

namespace Mmedia\LaravelChat\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Mmedia\LaravelChat\Events\MessageCreated;
use Mmedia\LaravelChat\Notifications\NewMessage;

class SendMessageCreatedNotification implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(MessageCreated $event): void
    {
        // For each participant in the chatroom, send a notification
        $chatroom = $event->message->chatroom;
        $participants = $chatroom->getNotifiableParticipants();
        foreach ($participants as $participant) {
            // If the participant is the sender, skip sending notification
            if ($participant->id === $event->message->sender_id) {
                continue;
            }
            $actualParticipant = $participant->participant;
            $actualParticipant->notify(new NewMessage($event->message, $participant));
        }
    }
}
