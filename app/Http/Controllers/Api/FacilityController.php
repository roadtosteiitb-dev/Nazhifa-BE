<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Land;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FacilityController extends Controller
{
    // Raw OSM import: category is derived from the `amenity` tag rather than
    // a pre-cleaned "category" column.
    private const CATEGORY_CASE = "
        CASE amenity
            WHEN 'school' THEN 'Sekolah'
            WHEN 'hospital' THEN 'Rumah Sakit'
            WHEN 'clinic' THEN 'Klinik'
            WHEN 'pharmacy' THEN 'Apotek'
            WHEN 'marketplace' THEN 'Pasar'
            WHEN 'fuel' THEN 'SPBU'
            WHEN 'bank' THEN 'Bank'
            WHEN 'atm' THEN 'ATM'
            WHEN 'place_of_worship' THEN 'Tempat Ibadah'
            ELSE COALESCE(amenity, 'Lainnya')
        END
    ";

    // GET /api/facilities — all public facilities with coordinates (for admin spatial map)
    public function index(): JsonResponse
    {
        $results = DB::select('
            SELECT
                gid,
                name,
                ' . self::CATEGORY_CASE . ' AS category,
                ST_Y(geom::geometry) AS latitude,
                ST_X(geom::geometry) AS longitude
            FROM public_facilities
            WHERE geom IS NOT NULL AND amenity IS NOT NULL
            LIMIT 500
        ');

        return response()->json(collect($results)->map(fn($row) => [
            'id'       => (string) $row->gid,
            'name'     => $row->name ?: $row->category,
            'category' => $row->category,
            'lat'      => (float) $row->latitude,
            'lng'      => (float) $row->longitude,
        ]));
    }

    // GET /api/lands/{id}/facilities
    public function getNearestFacilities(string $id): JsonResponse
    {
        $land = Land::findOrFail($id);

        $coords = DB::selectOne("
            SELECT ST_Y(geom::geometry) AS latitude, ST_X(geom::geometry) AS longitude
            FROM lands WHERE id = :id
        ", ['id' => $id]);

        if (!$coords || !$coords->latitude || !$coords->longitude) {
            return response()->json([]);
        }

        $lat = (float) $coords->latitude;
        $lng = (float) $coords->longitude;

        // Query the nearest facility for each category using PostGIS ST_Distance
        $sql = "
            SELECT DISTINCT ON (category)
                category,
                name,
                ST_Distance(
                    geom::geography,
                    ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography
                ) / 1000 AS distance_km
            FROM (
                SELECT name, geom, " . self::CATEGORY_CASE . " AS category
                FROM public_facilities
                WHERE amenity IS NOT NULL
            ) f
            ORDER BY category, distance_km ASC
        ";

        $results = DB::select($sql, ['lng' => $lng, 'lat' => $lat]);

        // Map icons from riskData definitions in frontend or fallback from DB
        $iconMap = [
            'Sekolah' => 'school-outline',
            'Rumah Sakit' => 'medical-outline',
            'Klinik' => 'fitness-outline',
            'Apotek' => 'medkit-outline',
            'Pasar' => 'cart-outline',
            'SPBU' => 'car-outline',
            'Bank' => 'cash-outline',
            'ATM' => 'card-outline',
            'Tempat Ibadah' => 'home-outline',
        ];

        $facilities = collect($results)->map(function ($row) use ($iconMap) {
            $distance = round((float) $row->distance_km, 1);
            // Estimate travel time at ~40km/h: (distance / 40) * 60 = distance * 1.5 min, minimum 1 min
            $time = max(1, (int) round($distance * 1.5));

            $cat = $row->category;
            $icon = $iconMap[$cat] ?? 'business-outline';

            return [
                'category' => $cat,
                'icon' => $icon,
                'name' => $row->name ?? "$cat Terdekat",
                'distanceKm' => $distance,
                'travelTimeMinutes' => $time,
            ];
        });

        return response()->json($facilities);
    }
}
