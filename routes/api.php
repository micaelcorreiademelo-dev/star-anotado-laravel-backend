<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemAdditionalController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\DatabaseOptimizationController;
use App\Http\Controllers\SwaggerController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public routes
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()]);
});

// Swagger Documentation
Route::get('/documentation', [SwaggerController::class, 'index']);
Route::get('/swagger.json', [SwaggerController::class, 'json']);

// Monitoring routes
Route::prefix('monitoring')->group(function () {
    Route::get('/status', [MonitoringController::class, 'status']);
    Route::get('/performance', [MonitoringController::class, 'performance']);
    Route::get('/logs', [MonitoringController::class, 'logs']);
});

// Database optimization routes
Route::prefix('database')->group(function () {
    Route::get('/analyze', [DatabaseOptimizationController::class, 'analyze']);
    Route::post('/optimize', [DatabaseOptimizationController::class, 'optimize']);
    Route::get('/performance', [DatabaseOptimizationController::class, 'performance']);
});

// Categories routes
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{id}', [CategoryController::class, 'show']);
    Route::get('/{id}/items', [CategoryController::class, 'items']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{id}', [CategoryController::class, 'update']);
        Route::delete('/{id}', [CategoryController::class, 'destroy']);
    });
});

// Items routes
Route::prefix('items')->group(function () {
    Route::get('/', [ItemController::class, 'index']);
    Route::get('/search', [ItemController::class, 'search']);
    Route::get('/{id}', [ItemController::class, 'show']);
    Route::get('/{id}/additionals', [ItemController::class, 'additionals']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [ItemController::class, 'store']);
        Route::put('/{id}', [ItemController::class, 'update']);
        Route::delete('/{id}', [ItemController::class, 'destroy']);
        Route::post('/{id}/upload-image', [ItemController::class, 'uploadImage']);
    });
});

// Item Additionals routes
Route::prefix('item-additionals')->group(function () {
    Route::get('/', [ItemAdditionalController::class, 'index']);
    Route::get('/{id}', [ItemAdditionalController::class, 'show']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [ItemAdditionalController::class, 'store']);
        Route::put('/{id}', [ItemAdditionalController::class, 'update']);
        Route::delete('/{id}', [ItemAdditionalController::class, 'destroy']);
    });
});

// Cart routes
Route::prefix('cart')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/add', [CartController::class, 'addItem']);
    Route::put('/update/{cartItemId}', [CartController::class, 'updateItem']);
    Route::delete('/remove/{cartItemId}', [CartController::class, 'removeItem']);
    Route::delete('/clear', [CartController::class, 'clear']);
    Route::get('/total', [CartController::class, 'getTotal']);
});

// Orders routes
Route::prefix('orders')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/{id}', [OrderController::class, 'show']);
    Route::post('/', [OrderController::class, 'store']);
    Route::put('/{id}/status', [OrderController::class, 'updateStatus']);
    Route::delete('/{id}', [OrderController::class, 'destroy']);
    Route::get('/{id}/items', [OrderController::class, 'items']);
});