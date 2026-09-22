<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LayerController extends Controller
{
    // Snap requested viewports to a coarse grid so nearby pans within the same
    // ~5.5km tile reuse the same cached, already-queried result.
    private const GRID_SIZE = 0.05;

    public const TABLE_MAP = [
        'flood' => 'flood',
        'landslide' => 'landslide',
        'drought' => 'drought',
        'eruption' => 'eruption',
        'liquefaction' => 'liquefaction',
        'extreme_weather' => 'extremeweather',
    ];

    // Imported hazard shapefiles were never reprojected/labeled consistently:
    // flood & landslide have no SRID set (raw UTM 49S), drought & liquefaction
    // are mislabeled 4326 but are actually still UTM 49S, and eruption &
    // extremeweather are mislabeled 4326 but are actually Web Mercator.
    // Force the real source SRID before transforming to 4326 (lat/lng).
    public const SOURCE_SRID = [
        'flood' => 32749,
        'landslide' => 32749,
        'drought' => 32749,
        'liquefaction' => 32749,
        'eruption' => 3857,
        'extreme_weather' => 3857,
    ];

    // drought, liquefaction and extreme_weather are dense hazard/weather GRIDS
    // (8k-23k tiny adjacent cells packed into a ~30km-wide area) — viewport
    // bbox filtering barely helps because the whole dataset already fits in
    // roughly one screen's worth of area. The fix that actually works is
    // dissolving all cells of the same severity level into one shape (ST_Union
    // grouped by gridcode) before simplifying, which turns tens of thousands
    // of tiny polygons into ~3 features per layer. That union is too slow
    // (~7s) to run inside a live request, so it's precomputed offline by
    // `php artisan layers:warm` and just read from cache here.
    public const HEAVY_TYPES = ['drought', 'liquefaction', 'extreme_weather'];

    // Simplify tolerance for HEAVY_TYPES, in METERS (native projected units of
    // the raw geom column) — applied before transforming to lat/lng, since
    // that's what ST_Simplify's tolerance is measured in here.
    public const HEAVY_SIMPLIFY_METERS = 150;

    // GET /api/layers/{type}?min_lat=&max_lat=&min_lng=&max_lng=
    public function getLayerPolygons(string $type, Request $request): JsonResponse
    {
        if (!isset(self::TABLE_MAP[$type])) {
            return response()->json(['error' => 'Invalid layer type'], 400);
        }

        if (in_array($type, self::HEAVY_TYPES, true)) {
            return response()->json(
                Cache::remember(
                    self::heavyCacheKey($type),
                    now()->addDays(30),
                    fn () => $this->computeHeavyLayer($type)
                )
            );
        }

        $table = self::TABLE_MAP[$type];
        $srid  = self::SOURCE_SRID[$type];
        $tolerance = 0.0001; // degrees — these tables are small (<1.5k rows), per-row simplify is cheap

        $minLat = $request->query('min_lat');
        $maxLat = $request->query('max_lat');
        $minLng = $request->query('min_lng');
        $maxLng = $request->query('max_lng');
        $hasBbox = $minLat !== null && $maxLat !== null && $minLng !== null && $maxLng !== null;

        $bboxKey = 'all';
        $snapMinLat = $snapMaxLat = $snapMinLng = $snapMaxLng = null;

        if ($hasBbox) {
            $snapMinLat = floor(((float) $minLat) / self::GRID_SIZE) * self::GRID_SIZE;
            $snapMaxLat = ceil(((float) $maxLat) / self::GRID_SIZE) * self::GRID_SIZE;
            $snapMinLng = floor(((float) $minLng) / self::GRID_SIZE) * self::GRID_SIZE;
            $snapMaxLng = ceil(((float) $maxLng) / self::GRID_SIZE) * self::GRID_SIZE;
            $bboxKey = "{$snapMinLat},{$snapMinLng},{$snapMaxLat},{$snapMaxLng}";
        }

        $cacheKey = "layer_polygons:v4:{$type}:{$tolerance}:{$bboxKey}";

        $polygons = Cache::remember($cacheKey, now()->addDays(30), function () use (
            $table, $srid, $tolerance, $type, $hasBbox, $snapMinLat, $snapMaxLat, $snapMinLng, $snapMaxLng
        ) {
            $where = '';
            $bindings = [];

            if ($hasBbox) {
                // "&&" is the index-backed bounding-box overlap operator — it's
                // what actually uses the GiST index, so only rows near the
                // viewport are ever pulled from disk instead of the whole table.
                $where = "WHERE geom && ST_Transform(ST_MakeEnvelope(?, ?, ?, ?, 4326), $srid)";
                $bindings = [$snapMinLng, $snapMinLat, $snapMaxLng, $snapMaxLat];
            }

            $results = DB::select("
                SELECT
                    gid,
                    gridcode,
                    ST_AsGeoJSON(
                        ST_SimplifyPreserveTopology(
                            ST_Transform(ST_SetSRID(geom, $srid), 4326),
                            $tolerance
                        ),
                        5
                    ) AS geojson
                FROM \"$table\"
                $where
            ", $bindings);

            return $this->rowsToPolygons($results, $type);
        });

        return response()->json($polygons);
    }

    public static function heavyCacheKey(string $type): string
    {
        return "layer_polygons:v4:heavy:{$type}:" . self::HEAVY_SIMPLIFY_METERS;
    }

    /**
     * Dissolve every geometry of a dense hazard-grid table into one shape per
     * severity level (gridcode), simplified aggressively. Takes several
     * seconds — meant to be called from `php artisan layers:warm`, not from a
     * live request.
     */
    public function computeHeavyLayer(string $type): array
    {
        $table = self::TABLE_MAP[$type];
        $srid  = self::SOURCE_SRID[$type];
        $tolerance = self::HEAVY_SIMPLIFY_METERS;

        $results = DB::select("
            SELECT
                gridcode,
                ST_AsGeoJSON(
                    ST_Transform(
                        ST_SetSRID(ST_Simplify(ST_Union(geom), $tolerance), $srid),
                        4326
                    ),
                    5
                ) AS geojson
            FROM \"$table\"
            GROUP BY gridcode
        ");

        return $this->rowsToPolygons($results, $type);
    }

    /** Convert raw GeoJSON rows into the flat DisasterPolygon[] shape the app expects. */
    private function rowsToPolygons(array $results, string $type): array
    {
        $polygons = [];
        foreach ($results as $row) {
            $geo = json_decode($row->geojson, true);
            if (!$geo || !isset($geo['coordinates'])) {
                continue;
            }

            // Collect every ring as its own outer-ring coordinate list — a Polygon
            // has one, a MultiPolygon can have many disjoint parts.
            $outerRings = [];
            if ($geo['type'] === 'Polygon') {
                if (isset($geo['coordinates'][0])) {
                    $outerRings[] = $geo['coordinates'][0];
                }
            } elseif ($geo['type'] === 'MultiPolygon') {
                foreach ($geo['coordinates'] as $poly) {
                    if (isset($poly[0])) {
                        $outerRings[] = $poly[0];
                    }
                }
            }

            if (empty($outerRings)) {
                continue;
            }

            $gridcode = (int) $row->gridcode;
            $level = 'low';
            if ($gridcode === 2) $level = 'medium';
            elseif ($gridcode >= 3) $level = 'high';

            // Assign standard overlay colors based on hazard severity
            $fillColor = 'rgba(22, 163, 74, 0.22)'; // low (green)
            $strokeColor = '#16A34A';
            if ($level === 'medium') {
                $fillColor = 'rgba(234, 179, 8, 0.28)'; // medium (yellow)
                $strokeColor = '#EAB308';
            } elseif ($level === 'high') {
                $fillColor = 'rgba(220, 38, 38, 0.33)'; // high (red)
                $strokeColor = '#DC2626';
            }

            $rowId = $row->gid ?? $gridcode;
            foreach ($outerRings as $partIndex => $ring) {
                $coords = [];
                foreach ($ring as $pt) {
                    $coords[] = [
                        'latitude' => (float) $pt[1],
                        'longitude' => (float) $pt[0]
                    ];
                }

                if (empty($coords)) {
                    continue;
                }

                $polygons[] = [
                    'id' => $type . '-' . $rowId . '-' . $partIndex,
                    'disasterType' => $type,
                    'level' => $level,
                    'title' => ucfirst($type) . ' Area (' . $level . ')',
                    'coordinates' => $coords,
                    'fillColor' => $fillColor,
                    'strokeColor' => $strokeColor
                ];
            }
        }

        return $polygons;
    }
}
