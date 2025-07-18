<?php

namespace Mmedia\LaravelChat;

use Illuminate\Support\Facades\Broadcast;
use Mmedia\LaravelChat\Contracts\ChatParticipantInterface;
use Mmedia\LaravelChat\Http\Resources\ChatParticipantResource;
use Mmedia\LaravelChat\Models\ChatParticipant;
use Mmedia\LaravelChat\Models\Chatroom;

Broadcast::channel((new Chatroom)->broadcastChannelRoute(), function (ChatParticipantInterface|ChatParticipant $user, Chatroom $chatroom) {
    return $chatroom->hasParticipant($user) ?
        ChatParticipantResource::make($chatroom->participant($user))->resolve() :
        false;
});
