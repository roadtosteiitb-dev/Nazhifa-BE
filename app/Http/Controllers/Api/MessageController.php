<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    // GET /api/conversations/{id}/messages
    public function index(string $conversationId): JsonResponse
    {
        $messages = Message::where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get();

        return response()->json(
            $messages->map(fn($m) => $m->toApiArray())
        );
    }

    // POST /api/conversations/{id}/messages
    public function store(Request $request, string $conversationId): JsonResponse
    {
        $request->validate([
            'text'       => 'required|string',
            'senderRole' => 'required|in:buyer,owner',
        ]);

        $user    = auth('api')->user();
        $timeStr = now()->format('H:i');

        $message = Message::create([
            'id'              => Str::uuid()->toString(),
            'conversation_id' => $conversationId,
            'sender_id'       => $user->id,
            'sender_role'     => $request->senderRole,
            'sender_name'     => $user->full_name,
            'text'            => $request->text,
            'status'          => 'sent',
        ]);

        // Update conversation last_message + unread counter
        $unreadField = $request->senderRole === 'buyer' ? 'unread_owner' : 'unread_buyer';

        Conversation::where('id', $conversationId)->update([
            'last_message'      => $request->text,
            'last_message_time' => $timeStr,
            'updated_at'        => now(),
        ]);

        Conversation::where('id', $conversationId)->increment($unreadField);

        return response()->json($message->toApiArray(), 201);
    }
}
