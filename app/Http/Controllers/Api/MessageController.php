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
        $conv = Conversation::findOrFail($conversationId);
        if (!$conv->roleOf(auth('api')->user())) {
            return response()->json(['error' => 'Anda bukan peserta percakapan ini'], 403);
        }

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
            'text' => 'required|string|max:2000',
        ]);

        $user = auth('api')->user();
        $conv = Conversation::findOrFail($conversationId);

        // Sender role comes from the conversation, not from the client
        $role = $conv->roleOf($user);
        if (!$role) {
            return response()->json(['error' => 'Anda bukan peserta percakapan ini'], 403);
        }

        $text = trim($request->text);
        if ($text === '') {
            return response()->json(['error' => 'Pesan tidak boleh kosong'], 422);
        }

        $message = Message::create([
            'id'              => Str::uuid()->toString(),
            'conversation_id' => $conversationId,
            'sender_id'       => $user->id,
            'sender_role'     => $role,
            'sender_name'     => $user->full_name,
            'text'            => $text,
            'status'          => 'sent',
        ]);

        // Update conversation last_message + unread counter
        $unreadField = $role === 'buyer' ? 'unread_owner' : 'unread_buyer';

        Conversation::where('id', $conversationId)->update([
            'last_message'      => $text,
            'last_message_time' => now()->format('H:i'),
            'updated_at'        => now(),
        ]);

        Conversation::where('id', $conversationId)->increment($unreadField);

        return response()->json($message->toApiArray(), 201);
    }
}
