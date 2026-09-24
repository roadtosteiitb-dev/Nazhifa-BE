<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Land;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    // GET /api/conversations
    public function index(): JsonResponse
    {
        $user = auth('api')->user();
        $role = $user->user_type;

        $field = $role === 'owner' ? 'owner_id' : 'buyer_id';

        $conversations = Conversation::with(['property', 'buyer', 'owner'])
            ->where($field, $user->id)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(
            $conversations->map(fn($c) => $c->toApiArray())
        );
    }

    // POST /api/conversations — get or create
    public function getOrCreate(Request $request): JsonResponse
    {
        $request->validate([
            'propertyId' => 'required|uuid',
        ]);

        // The buyer is always the logged-in user and the owner always comes from the
        // property itself — never trust ids sent by the client.
        $buyer = auth('api')->user();
        $land  = Land::find($request->propertyId);

        if (!$land) {
            return response()->json(['error' => 'Properti tidak ditemukan'], 404);
        }
        if (!$land->owner_id) {
            return response()->json(['error' => 'Properti ini belum memiliki pemilik yang dapat dihubungi'], 422);
        }
        if ($land->owner_id === $buyer->id) {
            return response()->json(['error' => 'Anda tidak dapat mengirim pesan ke properti milik sendiri'], 422);
        }

        $existing = Conversation::with(['property', 'buyer', 'owner'])
            ->where('property_id', $land->id)
            ->where('buyer_id',    $buyer->id)
            ->where('owner_id',    $land->owner_id)
            ->first();

        if ($existing) {
            return response()->json($existing->toApiArray());
        }

        // Create new conversation
        $conv = Conversation::create([
            'id'          => Str::uuid()->toString(),
            'property_id' => $land->id,
            'buyer_id'    => $buyer->id,
            'owner_id'    => $land->owner_id,
        ]);

        // Increment inquiries_count on the property
        Land::where('id', $land->id)->increment('inquiries_count');

        $conv->load(['property', 'buyer', 'owner']);

        return response()->json($conv->toApiArray(), 201);
    }

    // PUT /api/conversations/{id}/read
    public function markRead(string $id): JsonResponse
    {
        $conv = Conversation::findOrFail($id);
        $role = $conv->roleOf(auth('api')->user());
        if (!$role) {
            return response()->json(['error' => 'Anda bukan peserta percakapan ini'], 403);
        }

        $field = $role === 'buyer' ? 'unread_buyer' : 'unread_owner';

        // Plain query update so updated_at (used to sort the chat list) is not touched
        Conversation::where('id', $id)->toBase()->update([$field => 0]);

        // Mark the other side's messages as read
        \App\Models\Message::where('conversation_id', $id)
            ->where('sender_role', '!=', $role)
            ->where('status', 'sent')
            ->update(['status' => 'read']);

        return response()->json(['success' => true]);
    }
}
