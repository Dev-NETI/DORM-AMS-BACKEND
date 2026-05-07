<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AssetAssignmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\InventoryStockController;
use App\Http\Controllers\Api\ItemAssetController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\StockIssuanceController;
use App\Http\Controllers\Api\StockReceivalController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Public routes ────────────────────────────────────────────────────────────
Route::post('/register',            [AuthController::class, 'register']);
Route::post('/login',               [AuthController::class, 'login']);
Route::post('/verify-code',         [AuthController::class, 'verifyCode']);
Route::post('/resend-verification', [AuthController::class, 'resendCode']);

// Public asset detail lookup (used by QR code scan page — no auth required)
Route::get('/item-assets/code/{code}', [ItemAssetController::class, 'showByCode']);

// ── Protected routes (Sanctum token required) ────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('/user',      fn(Request $request) => $request->user());
    Route::post('/logout',   [AuthController::class, 'logout']);

    // Account settings (authenticated user's own profile)
    Route::put('/account', [AccountController::class, 'update']);

    // ── Reference / lookup resources ─────────────────────────────────────────
    Route::apiResource('departments', DepartmentController::class);

    // Custom import/template routes MUST be declared before apiResource so that
    // Laravel does not swallow "import" / "template" as a {category} parameter.
    Route::post('categories/import',  [CategoryController::class, 'import']);
    Route::get('categories/template', [CategoryController::class, 'template']);
    Route::apiResource('categories',  CategoryController::class);

    Route::apiResource('units', UnitController::class);

    Route::post('suppliers/import',   [SupplierController::class, 'import']);
    Route::get('suppliers/template',  [SupplierController::class, 'template']);
    Route::apiResource('suppliers',   SupplierController::class);

    // ── Item definitions ──────────────────────────────────────────────────────
    Route::post('items/import',       [ItemController::class, 'import']);
    Route::get('items/template',      [ItemController::class, 'template']);
    Route::apiResource('items',       ItemController::class);

    // ── People ────────────────────────────────────────────────────────────────
    Route::apiResource('users',     UserController::class);
    Route::apiResource('employees', EmployeeController::class);

    // ── Fixed-asset management ────────────────────────────────────────────────
    // Custom actions MUST be declared before apiResource to avoid {itemAsset} swallowing them
    Route::post('item-assets/import',   [ItemAssetController::class, 'import']);
    Route::get('item-assets/template',  [ItemAssetController::class, 'template']);
    Route::post('item-assets/{itemAsset}/assign',                 [ItemAssetController::class, 'assign']);
    Route::post('item-assets/{itemAsset}/return',                 [ItemAssetController::class, 'returnAsset']);
    Route::post('item-assets/{itemAsset}/upload-dr',              [ItemAssetController::class, 'uploadDeliveryReceipt']);
    Route::post('item-assets/{itemAsset}/documents',              [ItemAssetController::class, 'uploadDocument']);
    Route::delete('item-assets/{itemAsset}/documents/{document}', [ItemAssetController::class, 'deleteDocument']);
    Route::apiResource('item-assets', ItemAssetController::class);

    // Asset assignment history (read + update notes/status + delete closed records)
    Route::apiResource('asset-assignments', AssetAssignmentController::class)
        ->only(['index', 'show', 'update', 'destroy']);

    // ── Consumable stock management ───────────────────────────────────────────
    // Stock levels per item per department
    Route::get('inventory-stocks',                    [InventoryStockController::class, 'index']);
    Route::get('inventory-stocks/{item}/{department}', [InventoryStockController::class, 'show']);
    Route::post('inventory-stocks/adjust',            [InventoryStockController::class, 'adjust']);

    // Receive new consumable stock (creates StockReceival + increments InventoryStock)
    Route::post('stock-receivals/import',                               [StockReceivalController::class, 'import']);
    Route::get('stock-receivals/template',                              [StockReceivalController::class, 'template']);
    Route::post('stock-receivals/{stockReceival}/documents',            [StockReceivalController::class, 'uploadDocument']);
    Route::apiResource('stock-receivals', StockReceivalController::class)
        ->only(['index', 'show', 'store']);

    // Issue consumable stock to person/department (creates StockIssuance + decrements InventoryStock)
    Route::apiResource('stock-issuances', StockIssuanceController::class)
        ->only(['index', 'show', 'store']);
});
