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
            'propertyId' => 'required|string',
            'buyerId'    => 'required|string',
            'ownerId'    => 'required|string',
        ]);

        $existing = Conversation::with(['property', 'buyer', 'owner'])
            ->where('property_id', $request->propertyId)
            ->where('buyer_id',    $request->buyerId)
            ->where('owner_id',    $request->ownerId)
            ->first();

        if ($existing) {
            return response()->json($existing->toApiArray());
        }

        // Create new conversation
        $conv = Conversation::create([
            'id'          => Str::uuid()->toString(),
            'property_id' => $request->propertyId,
            'buyer_id'    => $request->buyerId,
            'owner_id'    => $request->ownerId,
        ]);

        // Increment inquiries_count on the property
        Land::where('id', $request->propertyId)->increment('inquiries_count');

        $conv->load(['property', 'buyer', 'owner']);

        return response()->json($conv->toApiArray(), 201);
    }

    // PUT /api/conversations/{id}/read
    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->validate(['role' => 'required|in:buyer,owner']);

        $field = $request->role === 'buyer' ? 'unread_buyer' : 'unread_owner';

        Conversation::where('id', $id)->update([$field => 0]);

        // Mark messages as read
        \App\Models\Message::where('conversation_id', $id)
            ->where('sender_role', '!=', $request->role)
            ->where('status', 'sent')
            ->update(['status' => 'read']);

        return response()->json(['success' => true]);
    }
}
