<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\CoffeeWaterInventoryController;
use App\Http\Controllers\Api\ConsumableAuditController;
use App\Http\Controllers\Api\ConsumableCategoryController;
use App\Http\Controllers\Api\ConsumableIssuanceController;
use App\Http\Controllers\Api\ConsumableItemController;
use App\Http\Controllers\Api\ConsumableInventoryController;
use App\Http\Controllers\Api\ConsumableMonthlyController;
use App\Http\Controllers\Api\ConsumableReceivalController;
use App\Http\Controllers\Api\ConsumableStockController;
use App\Http\Controllers\Api\AssetAssignmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\CdcRoomFurnitureDisposalController;
use App\Http\Controllers\Api\CdcRoomFurnitureItemController;
use App\Http\Controllers\Api\CdcRoomFurnitureItemLogController;
use App\Http\Controllers\Api\CdcRoomFurnitureItemVariantController;
use App\Http\Controllers\Api\CdcRoomFurnitureLogController;
use App\Http\Controllers\Api\CdcRoomInventoryController;
use App\Http\Controllers\Api\CdcRoomPurchaseController;
use App\Http\Controllers\Api\FdcRoomFurnitureDisposalController;
use App\Http\Controllers\Api\FdcRoomFurnitureItemController;
use App\Http\Controllers\Api\FdcRoomFurnitureItemLogController;
use App\Http\Controllers\Api\FdcRoomFurnitureItemVariantController;
use App\Http\Controllers\Api\FdcRoomFurnitureLogController;
use App\Http\Controllers\Api\FdcRoomInventoryController;
use App\Http\Controllers\Api\FdcRoomPurchaseController;
use App\Http\Controllers\Api\InventoryStockController;
use App\Http\Controllers\Api\ItemAssetController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RoomFurnitureItemController;
use App\Http\Controllers\Api\RoomFurnitureItemLogController;
use App\Http\Controllers\Api\RoomFurnitureDisposalController;
use App\Http\Controllers\Api\RoomFurnitureItemVariantController;
use App\Http\Controllers\Api\RoomFurnitureLogController;
use App\Http\Controllers\Api\RoomInventoryController;
use App\Http\Controllers\Api\RoomLocationController;
use App\Http\Controllers\Api\RoomPurchaseController;
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
    Route::get('inventory-stocks',                         [InventoryStockController::class, 'index']);
    Route::get('inventory-stocks/{item}/{department}',      [InventoryStockController::class, 'show']);
    Route::post('inventory-stocks/adjust',                 [InventoryStockController::class, 'adjust']);
    Route::patch('inventory-stocks/{itemId}/min-stock',    [InventoryStockController::class, 'setMinStock']);

    // Receive new consumable stock (creates StockReceival + increments InventoryStock)
    Route::post('stock-receivals/import',                               [StockReceivalController::class, 'import']);
    Route::get('stock-receivals/template',                              [StockReceivalController::class, 'template']);
    Route::post('stock-receivals/{stockReceival}/documents',            [StockReceivalController::class, 'uploadDocument']);
    Route::apiResource('stock-receivals', StockReceivalController::class)
        ->only(['index', 'show', 'store']);

    // Issue consumable stock to person/department (creates StockIssuance + decrements InventoryStock)
    Route::apiResource('stock-issuances', StockIssuanceController::class)
        ->only(['index', 'show', 'store']);

    // ── Room Furniture Inventory ──────────────────────────────────────────────
    Route::apiResource('room-locations', RoomLocationController::class);
    Route::apiResource('rooms',          RoomController::class);

    // Room inventory matrix (quantity per room × item)
    Route::get('room-inventory/matrix',                    [RoomInventoryController::class, 'matrix']);
    Route::put('room-inventory/cell/{roomId}/{itemId}',    [RoomInventoryController::class, 'updateCell']);
    Route::put('room-inventory/cell/{roomId}/{itemId}/{subItemId}', [RoomInventoryController::class, 'updateVariantCell']);
    Route::put('room-inventory/room/{room}',               [RoomInventoryController::class, 'updateRoom']);

    // Furniture item management (CRUD + stock)
    Route::get('room-furniture-items/template',  [RoomFurnitureItemController::class, 'template']);
    Route::post('room-furniture-items/import',   [RoomFurnitureItemController::class, 'import']);
    Route::apiResource('room-furniture-items', RoomFurnitureItemController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['room-furniture-items' => 'roomFurnitureItem']);

    // Purchase / receive stock records
    Route::get('room-purchases/{roomPurchase}/documents',  [RoomPurchaseController::class, 'indexDocuments']);
    Route::post('room-purchases/{roomPurchase}/documents', [RoomPurchaseController::class, 'storeDocuments']);
    Route::apiResource('room-purchases', RoomPurchaseController::class)->only(['index', 'store', 'destroy']);

    // Movement history log
    Route::get('room-furniture-logs',      [RoomFurnitureLogController::class,     'index']);
    // Furniture item lifecycle log (created / deleted)
    Route::get('room-furniture-item-logs', [RoomFurnitureItemLogController::class, 'index']);
    Route::apiResource('room-furniture-item-variants', RoomFurnitureItemVariantController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['room-furniture-item-variants' => 'roomFurnitureItemVariant']);

    // Disposal tracking
    Route::post('room-furniture-disposals/{roomFurnitureDisposal}/dispose', [RoomFurnitureDisposalController::class, 'dispose']);
    Route::get('room-furniture-disposed-history', [RoomFurnitureDisposalController::class, 'history']);
    Route::apiResource('room-furniture-disposals', RoomFurnitureDisposalController::class)
        ->only(['index', 'store', 'destroy'])
        ->parameters(['room-furniture-disposals' => 'roomFurnitureDisposal']);

    // ── FDC Room Furniture Inventory ──────────────────────────────────────────

    // FDC inventory matrix (quantity per room × item)
    Route::get('fdc-room-inventory/matrix',                    [FdcRoomInventoryController::class, 'matrix']);
    Route::put('fdc-room-inventory/cell/{roomId}/{itemId}',    [FdcRoomInventoryController::class, 'updateCell']);
    Route::put('fdc-room-inventory/cell/{roomId}/{itemId}/{subItemId}', [FdcRoomInventoryController::class, 'updateVariantCell']);
    Route::put('fdc-room-inventory/room/{room}',               [FdcRoomInventoryController::class, 'updateRoom']);

    // FDC furniture item management (CRUD + stock)
    Route::get('fdc-room-furniture-items/template',  [FdcRoomFurnitureItemController::class, 'template']);
    Route::post('fdc-room-furniture-items/import',   [FdcRoomFurnitureItemController::class, 'import']);
    Route::apiResource('fdc-room-furniture-items', FdcRoomFurnitureItemController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['fdc-room-furniture-items' => 'fdcRoomFurnitureItem']);

    // FDC purchase / receive stock records
    Route::get('fdc-room-purchases/{fdcRoomPurchase}/documents',  [FdcRoomPurchaseController::class, 'indexDocuments']);
    Route::post('fdc-room-purchases/{fdcRoomPurchase}/documents', [FdcRoomPurchaseController::class, 'storeDocuments']);
    Route::apiResource('fdc-room-purchases', FdcRoomPurchaseController::class)->only(['index', 'store', 'destroy']);

    // FDC variant management
    Route::apiResource('fdc-room-furniture-item-variants', FdcRoomFurnitureItemVariantController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['fdc-room-furniture-item-variants' => 'roomFurnitureItemVariant']);

    // FDC disposal tracking
    Route::post('fdc-room-furniture-disposals/{fdcRoomFurnitureDisposal}/dispose', [FdcRoomFurnitureDisposalController::class, 'dispose']);
    Route::get('fdc-room-furniture-disposed-history', [FdcRoomFurnitureDisposalController::class, 'history']);
    Route::apiResource('fdc-room-furniture-disposals', FdcRoomFurnitureDisposalController::class)
        ->only(['index', 'store', 'destroy'])
        ->parameters(['fdc-room-furniture-disposals' => 'fdcRoomFurnitureDisposal']);

    // FDC audit logs
    Route::get('fdc-room-furniture-logs',      [FdcRoomFurnitureLogController::class,     'index']);
    Route::get('fdc-room-furniture-item-logs', [FdcRoomFurnitureItemLogController::class, 'index']);

    // ── CDC Room Furniture Inventory ──────────────────────────────────────────

    Route::get('cdc-room-inventory/matrix',                                  [CdcRoomInventoryController::class, 'matrix']);
    Route::put('cdc-room-inventory/cell/{roomId}/{itemId}',                  [CdcRoomInventoryController::class, 'updateCell']);
    Route::put('cdc-room-inventory/cell/{roomId}/{itemId}/{subItemId}',      [CdcRoomInventoryController::class, 'updateVariantCell']);
    Route::put('cdc-room-inventory/room/{room}',                             [CdcRoomInventoryController::class, 'updateRoom']);

    Route::get('cdc-room-furniture-items/template',  [CdcRoomFurnitureItemController::class, 'template']);
    Route::post('cdc-room-furniture-items/import',   [CdcRoomFurnitureItemController::class, 'import']);
    Route::apiResource('cdc-room-furniture-items', CdcRoomFurnitureItemController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['cdc-room-furniture-items' => 'cdcRoomFurnitureItem']);

    Route::get('cdc-room-purchases/{cdcRoomPurchase}/documents',  [CdcRoomPurchaseController::class, 'indexDocuments']);
    Route::post('cdc-room-purchases/{cdcRoomPurchase}/documents', [CdcRoomPurchaseController::class, 'storeDocuments']);
    Route::apiResource('cdc-room-purchases', CdcRoomPurchaseController::class)->only(['index', 'store', 'destroy']);

    Route::apiResource('cdc-room-furniture-item-variants', CdcRoomFurnitureItemVariantController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['cdc-room-furniture-item-variants' => 'roomFurnitureItemVariant']);

    Route::post('cdc-room-furniture-disposals/{cdcRoomFurnitureDisposal}/dispose', [CdcRoomFurnitureDisposalController::class, 'dispose']);
    Route::get('cdc-room-furniture-disposed-history', [CdcRoomFurnitureDisposalController::class, 'history']);
    Route::apiResource('cdc-room-furniture-disposals', CdcRoomFurnitureDisposalController::class)
        ->only(['index', 'store', 'destroy'])
        ->parameters(['cdc-room-furniture-disposals' => 'cdcRoomFurnitureDisposal']);

    Route::get('cdc-room-furniture-logs',      [CdcRoomFurnitureLogController::class,     'index']);
    Route::get('cdc-room-furniture-item-logs', [CdcRoomFurnitureItemLogController::class, 'index']);

    // ── Consumable stock levels (dedicated table) ─────────────────────────────
    Route::patch('consumable-stocks/{itemId}/min-stock', [ConsumableStockController::class, 'setMinStock']);
    Route::get('consumable-stocks',                      [ConsumableStockController::class, 'index']);

    // ── Cleaning Supplies (Consumable Inventory) ──────────────────────────────
    // Sub-routes MUST be declared before the plain GET to avoid route conflicts
    Route::get('consumable-inventory/template',  [ConsumableInventoryController::class, 'template']);
    Route::post('consumable-inventory/import',   [ConsumableInventoryController::class, 'import']);
    Route::post('consumable-inventory/remark',   [ConsumableInventoryController::class, 'saveRemark']);
    Route::get('consumable-inventory/top-usage', [ConsumableInventoryController::class, 'topUsage']);
    // Daily inventory view (mirrors GalleyInventoryController pattern)
    Route::get('consumable-inventory',           [ConsumableInventoryController::class, 'index']);

    Route::apiResource('consumable-categories', ConsumableCategoryController::class);
    Route::apiResource('consumable-items',      ConsumableItemController::class);

    Route::apiResource('consumable-receivals',  ConsumableReceivalController::class)->only(['store', 'destroy']);
    Route::get('consumable-issuances/usage-history', [ConsumableIssuanceController::class, 'usageHistory']);
    Route::apiResource('consumable-issuances',  ConsumableIssuanceController::class)->only(['store', 'destroy']);

    Route::get('consumable-audit-logs', [ConsumableAuditController::class, 'index']);

    // ── Coffee & Bottled Water Inventory (weekly) ─────────────────────────────
    // Sub-routes MUST be declared before the plain GET to avoid route conflicts
    Route::get('coffee-water-inventory/template',  [CoffeeWaterInventoryController::class, 'template']);
    Route::post('coffee-water-inventory/import',   [CoffeeWaterInventoryController::class, 'import']);
    Route::get('coffee-water-inventory',           [CoffeeWaterInventoryController::class, 'index']);
});
