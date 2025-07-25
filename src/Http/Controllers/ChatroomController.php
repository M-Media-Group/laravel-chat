<?php

namespace Mmedia\LeChat\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Mmedia\LeChat\Contracts\ChatParticipantInterface;
use Mmedia\LeChat\Http\Resources\ChatroomResource;
use Mmedia\LeChat\Models\ChatParticipant;
use Mmedia\LeChat\Models\Chatroom;

class ChatroomController extends Controller
{
    /**
     * Display a listing of the chatrooms.
     */
    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (! ($user instanceof ChatParticipantInterface)) {
            return response()->json(['error' => 'Authenticated user is not a chat participant'], 400);
        }

        $chatrooms = Chatroom::havingParticipants([$user], true)
            ->with([
                'participants',
                'latestMessage' => function ($query) use ($user) {
                    $query->visibleTo($user)->with('sender');
                },
            ])
            ->withUnreadMessagesCountFor($user)
            ->get();

        return ChatroomResource::collection($chatrooms);
    }

    /**
     * Show the form for creating a new chatroom.
     */
    public function show(Request $request, Chatroom $chatroom): ChatroomResource|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (! ($user instanceof ChatParticipantInterface)) {
            return response()->json(['error' => 'Authenticated user is not a chat participant'], 400);
        }

        if (! $chatroom->hasOrHadParticipant($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $chatroom->load(['participants', 'messages' => function ($query) use ($user) {
            $query->visibleTo($user)->with('sender');
        }]);

        return new ChatroomResource($chatroom);
    }

    /**
     * Store a newly created chatroom in storage.
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (! ($user instanceof ChatParticipantInterface)) {
            return response()->json(['error' => 'Authenticated user is not a chat participant'], 400);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $chatroom = Chatroom::create($request->only('name', 'description'));

        $chatroom->addParticipant($user, 'admin');

        return response()->json($chatroom, 201);
    }

    /**
     * Update the specified chatroom in storage.
     */
    public function update(Request $request, Chatroom $chatroom): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (! ($user instanceof ChatParticipantInterface)) {
            return response()->json(['error' => 'Authenticated user is not a chat participant'], 400);
        }

        $chatParticipant = $chatroom->participant($user);

        if (! $chatParticipant || $chatParticipant->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $chatroom->update($request->only('name', 'description'));

        return response()->json($chatroom, 200);
    }

    /**
     * Store a message in the chatroom.
     */
    public function storeMessage(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (! ($user instanceof ChatParticipantInterface)) {
            return response()->json(['error' => 'Authenticated user is not a chat participant'], 400);
        }

        $request->validate([
            'to_entity_type' => 'required|string|in:chatroom,chat_participant',
            'to_entity_id' => 'required|integer',
            'message' => 'required|string',
        ]);

        $model = $request->to_entity_type === 'chatroom'
            ? Chatroom::findOrFail($request->to_entity_id)
            : ChatParticipant::findOrFail($request->to_entity_id);

        try {
            $user->sendMessageTo(
                $model,
                $request->message
            );

            return response()->json(['message' => 'Message sent successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to send message', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Mark the chatroom as read.
     */
    public function markAsRead(Request $request, Chatroom $chatroom): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (! ($user instanceof ChatParticipantInterface)) {
            return response()->json(['error' => 'Authenticated user is not a chat participant'], 400);
        }

        if (! $chatroom->hasOrHadParticipant($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (! $chatroom->markAsReadBy($user)) {
            return response()->json(['error' => 'Failed to mark chatroom as read'], 400);
        }

        return response()->json(['message' => 'Chatroom marked as read'], 200);
    }
}
