<?php

use App\Http\Controllers\Api\LayerController;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('layers:warm', function () {
    $controller = new LayerController();

    foreach (LayerController::HEAVY_TYPES as $type) {
        $this->info("Computing {$type} (union + simplify — this can take ~10s)...");
        $start = microtime(true);

        $polygons = $controller->computeHeavyLayer($type);
        Cache::put(LayerController::heavyCacheKey($type), $polygons, now()->addDays(30));

        $ms = round((microtime(true) - $start) * 1000);
        $this->info("  -> {$type}: " . count($polygons) . " feature(s) cached in {$ms}ms");
    }

    $this->info('Done. The app will now serve these instantly from cache.');
})->purpose('Precompute and cache the dense hazard-grid layers (drought, liquefaction, extreme_weather) so the app never has to run the slow union+simplify live');
