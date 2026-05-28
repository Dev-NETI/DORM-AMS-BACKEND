<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumableAuditLog;
use App\Models\ConsumableItem;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumableAuditController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = ConsumableAuditLog::with('performedByUser')
            ->orderByDesc('created_at');

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        if ($request->filled('action_type')) {
            $query->where('action_type', $request->input('action_type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $q = $request->input('search');
            $query->where('description', 'like', "%{$q}%");
        }

        // Filter logs to only those related to a specific module's items
        if ($request->filled('module')) {
            $module  = $request->input('module');
            $itemIds = ConsumableItem::whereHas('category', fn ($q) => $q->where('module', $module))
                ->pluck('id')->all();

            if (empty($itemIds)) {
                $query->whereRaw('0 = 1');
            } else {
                $query->where(function ($q) use ($itemIds) {
                    $q->where(function ($inner) use ($itemIds) {
                        $inner->where('entity_type', 'issuance')
                              ->whereIn('entity_id', fn ($sub) =>
                                  $sub->select('id')->from('consumable_issuances')
                                      ->whereIn('consumable_item_id', $itemIds)
                              );
                    })->orWhere(function ($inner) use ($itemIds) {
                        $inner->where('entity_type', 'receival')
                              ->whereIn('entity_id', fn ($sub) =>
                                  $sub->select('id')->from('consumable_receivals')
                                      ->whereIn('consumable_item_id', $itemIds)
                              );
                    })->orWhere(function ($inner) use ($itemIds) {
                        $inner->where('entity_type', 'item')
                              ->whereIn('entity_id', $itemIds);
                    });
                });
            }
        }

        $perPage = min((int) $request->input('per_page', 30), 100);
        $logs    = $query->paginate($perPage);

        return $this->success($logs);
    }
}
