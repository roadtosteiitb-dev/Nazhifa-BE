<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\FacilityController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\LandController;
use App\Http\Controllers\Api\LayerController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// =============================================
// Welcome endpoint
// =============================================
Route::get('/', function () {
    return response()->json([
        'name'        => 'LOKATANI API',
        'version'     => '1.0.0',
        'description' => 'Backend API untuk LOKATANI – Sistem Informasi Lahan Berbasis GIS',
        'stack'       => 'Laravel 12 + PostgreSQL + PostGIS',
        'endpoints'   => [
            'auth'          => '/api/auth',
            'lands'         => '/api/lands',
            'conversations' => '/api/conversations',
            'notifications' => '/api/notifications',
        ],
    ]);
});

// =============================================
// Auth routes (public)
// =============================================
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// =============================================
// Auth routes (protected)
// =============================================
Route::prefix('auth')->middleware('auth:api')->group(function () {
    Route::get('/me',     [AuthController::class, 'me']);
    Route::post('/logout',[AuthController::class, 'logout']);
    Route::put('/profile',          [AuthController::class, 'updateProfile']);
    Route::post('/profile/photo',   [AuthController::class, 'uploadPhoto']);
    Route::delete('/profile/photo', [AuthController::class, 'deletePhoto']);
});

// =============================================
// Lands (public read, protected write)
// =============================================
Route::prefix('lands')->group(function () {
    // ⚠️ Spatial route MUST come before /{id} to avoid conflict
    Route::get('/spatial/nearby', [LandController::class, 'nearby']);
    Route::get('/',               [LandController::class, 'index']);
    Route::get('/{id}/risk',      [LandController::class, 'getRiskAnalysis']);
    Route::get('/{id}/facilities',[FacilityController::class, 'getNearestFacilities']);
    Route::get('/{id}/route',     [FacilityController::class, 'route']);
    Route::get('/{id}',           [LandController::class, 'show']);

    // Protected
    Route::middleware('auth:api')->group(function () {
        Route::post('/',                   [LandController::class, 'store']);
        Route::patch('/{id}/status',       [LandController::class, 'updateStatus']);
        Route::put('/{id}/analytics',      [LandController::class, 'incrementAnalytic']);
        Route::post('/{id}/favorite',      [FavoriteController::class, 'store']);
        Route::delete('/{id}/favorite',    [FavoriteController::class, 'destroy']);
    });
});

// =============================================
// Favorites (saved properties of the logged-in user)
// =============================================
Route::middleware('auth:api')->get('/favorites', [FavoriteController::class, 'index']);

// =============================================
// Layers (hazard polygon overlays)
// =============================================
Route::prefix('layers')->group(function () {
    Route::get('/{type}', [LayerController::class, 'getLayerPolygons']);
});

// =============================================
// Facilities (public facilities list, for admin spatial map)
// =============================================
Route::get('/facilities', [FacilityController::class, 'index']);

// =============================================
// Users (Admin management, all protected)
// =============================================
Route::middleware('auth:api')->prefix('users')->group(function () {
    Route::get('/',               [UserController::class, 'index']);
    Route::patch('/{id}/status',  [UserController::class, 'updateStatus']);
});

// =============================================
// Complaints (all protected)
// =============================================
Route::middleware('auth:api')->prefix('complaints')->group(function () {
    Route::get('/',              [ComplaintController::class, 'index']);
    Route::post('/',             [ComplaintController::class, 'store']);
    Route::patch('/{id}/status', [ComplaintController::class, 'updateStatus']);
});

// =============================================
// Conversations & Messages (all protected)
// =============================================
Route::middleware('auth:api')->prefix('conversations')->group(function () {
    Route::get('/',               [ConversationController::class, 'index']);
    Route::post('/',              [ConversationController::class, 'getOrCreate']);
    Route::put('/{id}/read',      [ConversationController::class, 'markRead']);

    // Nested messages
    Route::get('/{id}/messages',  [MessageController::class, 'index']);
    Route::post('/{id}/messages', [MessageController::class, 'store']);
});

// =============================================
// Notifications (all protected)
// =============================================
Route::middleware('auth:api')->prefix('notifications')->group(function () {
    Route::get('/',          [NotificationController::class, 'index']);
    Route::put('/read-all',  [NotificationController::class, 'markAllRead']);
    Route::put('/{id}/read', [NotificationController::class, 'markRead']);
});
