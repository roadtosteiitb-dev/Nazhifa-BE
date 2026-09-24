<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Land;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FavoriteController extends Controller
{
    // GET /api/favorites — ids of the properties the logged-in user saved
    public function index(): JsonResponse
    {
        $ids = DB::table('favorites')
            ->where('user_id', auth('api')->id())
            ->orderByDesc('created_at')
            ->pluck('property_id');

        return response()->json($ids);
    }

    // POST /api/lands/{id}/favorite
    public function store(string $id): JsonResponse
    {
        Land::findOrFail($id);

        DB::table('favorites')->insertOrIgnore([
            'id'          => Str::uuid()->toString(),
            'user_id'     => auth('api')->id(),
            'property_id' => $id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json(['favorited' => true, 'favorites' => $this->syncCount($id)]);
    }

    // DELETE /api/lands/{id}/favorite
    public function destroy(string $id): JsonResponse
    {
        DB::table('favorites')
            ->where('user_id', auth('api')->id())
            ->where('property_id', $id)
            ->delete();

        return response()->json(['favorited' => false, 'favorites' => $this->syncCount($id)]);
    }

    /** Keep lands.favorites equal to the real number of users who saved the property. */
    private function syncCount(string $id): int
    {
        $count = DB::table('favorites')->where('property_id', $id)->count();
        Land::where('id', $id)->toBase()->update(['favorites' => $count]);
        return $count;
    }
}
