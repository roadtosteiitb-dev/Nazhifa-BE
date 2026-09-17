<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Land;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LandController extends Controller
{
    // GET /api/lands
    public function index(Request $request): JsonResponse
    {
        $query = Land::with('owner');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('ownerId')) {
            $query->where('owner_id', $request->ownerId);
        }

        $lands = $query->orderByDesc('updated_at')->get();

        return response()->json(
            $lands->map(fn($l) => $l->toApiArray())
        );
    }

    // GET /api/lands/spatial/nearby?lat=&lng=&radius= (PostGIS ST_DWithin)
    public function nearby(Request $request): JsonResponse
    {
        $request->validate([
            'lat'    => 'required|numeric',
            'lng'    => 'required|numeric',
            'radius' => 'required|numeric|min:0.1|max:100',
        ]);

        $lat    = (float) $request->lat;
        $lng    = (float) $request->lng;
        $radius = (float) $request->radius * 1000; // km → meters

        $sql = "
            SELECT
                l.*,
                u.full_name AS owner_name,
                ST_Y(l.geom::geometry) AS latitude,
                ST_X(l.geom::geometry) AS longitude,
                ST_Distance(
                    l.geom::geography,
                    ST_MakePoint(:lng, :lat)::geography
                ) / 1000 AS distance_km
            FROM lands l
            LEFT JOIN users u ON u.id = l.owner_id
            WHERE ST_DWithin(
                l.geom::geography,
                ST_MakePoint(:lng2, :lat2)::geography,
                :radius
            )
            " . ($request->has('status') ? "AND l.status = :status" : "") . "
            ORDER BY distance_km ASC
        ";

        $bindings = ['lat' => $lat, 'lng' => $lng, 'lat2' => $lat, 'lng2' => $lng, 'radius' => $radius];
        if ($request->has('status')) {
            $bindings['status'] = $request->status;
        }

        $rows = DB::select($sql, $bindings);

        return response()->json(
            collect($rows)->map(function ($row) {
                $land = new Land((array) $row);
                $land->id = $row->id;
                $land->setAttribute('latitude',    $row->latitude ?? null);
                $land->setAttribute('longitude',   $row->longitude ?? null);
                return $land->toApiArray((float) ($row->distance_km ?? 0));
            })
        );
    }

    // GET /api/lands/{id}
    public function show(string $id): JsonResponse
    {
        $row = DB::selectOne("
            SELECT
                l.*,
                u.full_name AS owner_name,
                ST_Y(l.geom::geometry) AS latitude,
                ST_X(l.geom::geometry) AS longitude
            FROM lands l
            LEFT JOIN users u ON u.id = l.owner_id
            WHERE l.id = :id
        ", ['id' => $id]);

        if (!$row) {
            return response()->json(['error' => 'Land not found'], 404);
        }

        $land = new Land((array) $row);
        $land->id = $row->id;
        $land->setAttribute('latitude',  $row->latitude ?? null);
        $land->setAttribute('longitude', $row->longitude ?? null);
        // Override owner with joined full_name
        $land->setAttribute('owner', $row->owner_name ?? null);

        // Increment views
        Land::where('id', $id)->increment('views');

        return response()->json($land->toApiArray());
    }

    // GET /api/lands/{id}/risk
    public function getRiskAnalysis(string $id): JsonResponse
    {
        $land = Land::findOrFail($id);

        $coords = DB::selectOne("
            SELECT ST_Y(geom::geometry) AS latitude, ST_X(geom::geometry) AS longitude
            FROM lands WHERE id = :id
        ", ['id' => $id]);

        if (!$coords || !$coords->latitude || !$coords->longitude) {
            return response()->json([
                'overallRisk' => 1,
                'risk' => [
                    'flood' => 1,
                    'landslide' => 1,
                    'eruption' => 1,
                    'extreme_weather' => 1,
                    'drought' => 1,
                    'liquefaction' => 1,
                ],
                'distribution' => ['low' => 6, 'medium' => 0, 'high' => 0]
            ]);
        }

        $lat = (float) $coords->latitude;
        $lng = (float) $coords->longitude;

        $tables = [
            'flood' => 'flood',
            'landslide' => 'landslide',
            'eruption' => 'eruption',
            'extreme_weather' => 'extremeweather',
            'drought' => 'drought',
            'liquefaction' => 'liquefaction',
        ];

        // Imported hazard shapefiles were never reprojected/labeled consistently:
        // flood & landslide have no SRID set (raw UTM 49S), drought & liquefaction
        // are mislabeled 4326 but are actually still UTM 49S, and eruption &
        // extremeweather are mislabeled 4326 but are actually Web Mercator.
        $sourceSrid = [
            'flood' => 32749,
            'landslide' => 32749,
            'drought' => 32749,
            'liquefaction' => 32749,
            'eruption' => 3857,
            'extreme_weather' => 3857,
        ];

        $risks = [];
        foreach ($tables as $key => $table) {
            $srid = $sourceSrid[$key];
            $result = DB::selectOne("
                SELECT gridcode FROM \"$table\"
                WHERE ST_Intersects(ST_SetSRID(geom, $srid), ST_Transform(ST_SetSRID(ST_MakePoint(:lng, :lat), 4326), $srid))
                LIMIT 1
            ", ['lng' => $lng, 'lat' => $lat]);

            $risks[$key] = $result ? (int) $result->gridcode : 1;
        }

        $lowCount = 0;
        $medCount = 0;
        $highCount = 0;
        foreach ($risks as $rVal) {
            if ($rVal === 1) $lowCount++;
            elseif ($rVal === 2) $medCount++;
            elseif ($rVal >= 3) $highCount++;
        }

        $overallRisk = max(array_values($risks));

        return response()->json([
            'overallRisk' => $overallRisk,
            'risk' => $risks,
            'distribution' => [
                'low' => $lowCount,
                'medium' => $medCount,
                'high' => $highCount
            ]
        ]);
    }

    // POST /api/lands
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'location' => 'required|string',
        ]);

        $data = [
            'id'               => Str::uuid()->toString(),
            'name'             => $request->name,
            'location'         => $request->location,
            'price'            => $request->price,
            'is_for_sale'      => $request->boolean('isForSale', true),
            'type'             => $request->type ?? 'house',
            'status'           => 'Pending',
            'owner_id'         => auth('api')->id(),
            'description'      => $request->description,
            'image'            => $request->image,
            'images'           => $request->images ? json_encode($request->images) : null,
            'floors'           => $request->floors,
            'bedrooms'         => $request->bedrooms,
            'bathrooms'        => $request->bathrooms,
            'electricity'      => $request->electricity,
            'certificate'      => $request->certificate ?? 'SHM',
            'certificate_image'=> $request->certificateImage,
            'garage'           => $request->garage,
            'unit_floor'       => $request->unitFloor,
            'unit_type'        => $request->unitType,
            'furnished'        => $request->furnished,
            'land_area'        => $request->landArea,
            'building_area'    => $request->buildingArea,
            'facilities'       => $request->facilities ? json_encode($request->facilities) : null,
        ];

        // Insert with optional geom
        if ($request->latitude && $request->longitude) {
            DB::statement(
                "INSERT INTO lands (" . implode(',', array_keys($data)) . ", geom, created_at, updated_at)
                 VALUES (" . implode(',', array_fill(0, count($data), '?')) . ",
                 ST_SetSRID(ST_MakePoint(?, ?), 4326), NOW(), NOW())",
                [...array_values($data), (float)$request->longitude, (float)$request->latitude]
            );
        } else {
            DB::statement(
                "INSERT INTO lands (" . implode(',', array_keys($data)) . ", created_at, updated_at)
                 VALUES (" . implode(',', array_fill(0, count($data), '?')) . ", NOW(), NOW())",
                array_values($data)
            );
        }

        // Create submitted notification
        $owner = auth('api')->user();
        Notification::create([
            'id'            => Str::uuid()->toString(),
            'property_id'   => $data['id'],
            'property_name' => $data['name'],
            'type'          => 'submitted',
            'owner_id'      => $owner->id,
        ]);

        return response()->json(['id' => $data['id'], 'status' => 'Pending'], 201);
    }

    // PATCH /api/lands/{id}/status  (Admin only)
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status'          => 'required|in:Approved,Rejected,Sold,Archived',
            'rejectionReason' => 'nullable|string',
        ]);

        $land = Land::findOrFail($id);
        $land->update([
            'status'           => $request->status,
            'rejection_reason' => $request->rejectionReason,
        ]);

        // Create notification for owner
        $typeMap = [
            'Approved' => 'approved', 'Rejected' => 'rejected',
            'Sold'     => 'sold',     'Archived' => 'archived',
        ];

        Notification::create([
            'id'            => Str::uuid()->toString(),
            'property_id'   => $land->id,
            'property_name' => $land->name,
            'type'          => $typeMap[$request->status],
            'owner_id'      => $land->owner_id,
            'reason'        => $request->rejectionReason,
        ]);

        return response()->json(['success' => true, 'status' => $request->status]);
    }

    // PUT /api/lands/{id}/analytics
    public function incrementAnalytic(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'field' => 'required|in:views,favorites,inquiries_count',
        ]);

        Land::where('id', $id)->increment($request->field);
        return response()->json(['success' => true]);
    }
}
