<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Land;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
                ST_Y(geom::geometry) AS latitude,
                ST_X(geom::geometry) AS longitude,
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
                'lat' => (float) $row->latitude,
                'lng' => (float) $row->longitude,
            ];
        });

        return response()->json($facilities);
    }

    // GET /api/lands/{id}/route?to_lat=&to_lng=
    // Road route from the property to a destination (e.g. a nearest facility).
    // There is no road-network table for pgRouting yet, so the route is
    // computed by OSRM (OpenStreetMap road data) and cached per coordinate pair.
    public function route(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'to_lat' => 'required|numeric|between:-90,90',
            'to_lng' => 'required|numeric|between:-180,180',
        ]);

        $from = DB::selectOne("
            SELECT ST_Y(geom::geometry) AS latitude, ST_X(geom::geometry) AS longitude
            FROM lands WHERE id = :id
        ", ['id' => $id]);

        if (!$from || $from->latitude === null || $from->longitude === null) {
            return response()->json(['error' => 'Koordinat properti tidak tersedia'], 404);
        }

        $fromLat = round((float) $from->latitude, 5);
        $fromLng = round((float) $from->longitude, 5);
        $toLat   = round((float) $request->to_lat, 5);
        $toLng   = round((float) $request->to_lng, 5);

        $cacheKey = "route:v1:{$fromLat},{$fromLng}:{$toLat},{$toLng}";

        $route = Cache::get($cacheKey);
        if ($route === null) {
            $route = $this->fetchOsrmRoute($fromLat, $fromLng, $toLat, $toLng);
            if ($route === null) {
                return response()->json(['error' => 'Rute tidak dapat dihitung saat ini'], 502);
            }
            Cache::put($cacheKey, $route, now()->addDays(30));
        }

        return response()->json($route);
    }

    private function fetchOsrmRoute(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        $base = rtrim(config('services.osrm.url'), '/');

        try {
            $res = Http::timeout(10)->get(
                "{$base}/route/v1/driving/{$fromLng},{$fromLat};{$toLng},{$toLat}",
                ['overview' => 'full', 'geometries' => 'geojson']
            );
        } catch (\Throwable $e) {
            Log::warning('OSRM request failed: ' . $e->getMessage());
            return null;
        }

        $best = $res->json('routes.0');
        if (!$res->ok() || $res->json('code') !== 'Ok' || !$best) {
            Log::warning('OSRM returned no route', ['status' => $res->status(), 'code' => $res->json('code')]);
            return null;
        }

        return [
            'distanceKm'      => round($best['distance'] / 1000, 1),
            'durationMinutes' => max(1, (int) round($best['duration'] / 60)),
            'coordinates'     => array_map(
                fn($pt) => ['latitude' => (float) $pt[1], 'longitude' => (float) $pt[0]],
                $best['geometry']['coordinates'] ?? []
            ),
            'from' => ['latitude' => $fromLat, 'longitude' => $fromLng],
            'to'   => ['latitude' => $toLat,   'longitude' => $toLng],
        ];
    }
}
