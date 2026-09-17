<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // GET /api/users (Admin only)
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->has('role')) {
            $query->where('user_type', $request->role);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->orderByDesc('created_at')->get();

        return response()->json($users->map(fn($u) => $u->toApiArray()));
    }

    // PATCH /api/users/{id}/status (Admin only)
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $user = User::findOrFail($id);
        $user->update(['status' => $request->status]);

        return response()->json($user->toApiArray());
    }
}
