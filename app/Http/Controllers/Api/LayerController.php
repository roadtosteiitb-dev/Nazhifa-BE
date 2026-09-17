<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LayerController extends Controller
{
    // GET /api/layers/{type}
    public function getLayerPolygons(string $type): JsonResponse
    {
        $tableMap = [
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
        $sourceSrid = [
            'flood' => 32749,
            'landslide' => 32749,
            'drought' => 32749,
            'liquefaction' => 32749,
            'eruption' => 3857,
            'extreme_weather' => 3857,
        ];

        if (!isset($tableMap[$type])) {
            return response()->json(['error' => 'Invalid layer type'], 400);
        }

        $table = $tableMap[$type];
        $srid  = $sourceSrid[$type];

        // Fetch polygons from hazard table, convert to GeoJSON
        // Limit to 150 features to prevent sending massive datasets that slow down react-native-maps
        $results = DB::select("
            SELECT
                gid,
                gridcode,
                ST_AsGeoJSON(ST_Transform(ST_SetSRID(geom, $srid), 4326)) AS geojson
            FROM \"$table\"
            LIMIT 150
        ");

        $polygons = [];
        foreach ($results as $row) {
            $geo = json_decode($row->geojson, true);
            if ($geo && isset($geo['coordinates'])) {
                $coords = [];
                if ($geo['type'] === 'Polygon') {
                    foreach ($geo['coordinates'][0] as $pt) {
                        $coords[] = [
                            'latitude' => (float) $pt[1],
                            'longitude' => (float) $pt[0]
                        ];
                    }
                } elseif ($geo['type'] === 'MultiPolygon') {
                    // Pull coordinates of the first polygon outer ring
                    if (isset($geo['coordinates'][0][0])) {
                        foreach ($geo['coordinates'][0][0] as $pt) {
                            $coords[] = [
                                'latitude' => (float) $pt[1],
                                'longitude' => (float) $pt[0]
                            ];
                        }
                    }
                }

                if (!empty($coords)) {
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

                    $polygons[] = [
                        'id' => $type . '-' . $row->gid,
                        'disasterType' => $type,
                        'level' => $level,
                        'title' => ucfirst($type) . ' Area (' . $level . ')',
                        'coordinates' => $coords,
                        'fillColor' => $fillColor,
                        'strokeColor' => $strokeColor
                    ];
                }
            }
        }

        return response()->json($polygons);
    }
}
