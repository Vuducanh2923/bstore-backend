<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InventoryReservationException;
use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalInventoryReservationController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = $this->inventoryService->reserve($data['reference'], $data['items']);
        } catch (InventoryReservationException $exception) {
            return $this->error($exception);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ], $result['created'] ? 201 : 200);
    }

    public function commit(string $reference): JsonResponse
    {
        return $this->runAction(fn (): array => $this->inventoryService->commit($reference));
    }

    public function release(string $reference): JsonResponse
    {
        return $this->runAction(fn (): array => $this->inventoryService->release($reference));
    }

    public function restore(string $reference): JsonResponse
    {
        return $this->runAction(fn (): array => $this->inventoryService->restore($reference));
    }

    private function runAction(callable $action): JsonResponse
    {
        try {
            $data = $action();
        } catch (InventoryReservationException $exception) {
            return $this->error($exception);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    private function error(InventoryReservationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
            'data' => $exception->context ?: null,
        ], $exception->httpStatus);
    }
}
