<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ComplaintController extends Controller
{
    // GET /api/complaints (Admin: all; otherwise only the caller's own)
    public function index(Request $request): JsonResponse
    {
        $query = Complaint::with(['reporter', 'property']);

        $user = auth('api')->user();
        if ($user && $user->user_type !== 'admin') {
            $query->where('reporter_id', $user->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $complaints = $query->orderByDesc('created_at')->get();

        return response()->json($complaints->map(fn($c) => $c->toApiArray()));
    }

    // POST /api/complaints
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'category'   => 'required|string|max:50',
            'message'    => 'required|string',
            'propertyId' => 'nullable|uuid',
            'image'      => 'nullable|string',
        ]);

        $complaint = Complaint::create([
            'id'          => Str::uuid()->toString(),
            'reporter_id' => auth('api')->id(),
            'property_id' => $request->propertyId,
            'category'    => $request->category,
            'message'     => $request->message,
            'image'       => $request->image,
            'status'      => 'open',
        ]);

        return response()->json($complaint->toApiArray(), 201);
    }

    // PATCH /api/complaints/{id}/status (Admin only)
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:open,in_progress,resolved',
        ]);

        $complaint = Complaint::findOrFail($id);
        $complaint->update(['status' => $request->status]);

        return response()->json($complaint->toApiArray());
    }
}
