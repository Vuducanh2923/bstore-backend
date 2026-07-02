<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderItem;
use App\Models\WarrantyRequest;
use App\Services\OrderNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    public function __construct(private readonly OrderNotificationService $notifications) {}

    private const RESOURCES = [
        'carts' => ['model' => Cart::class, 'relations' => ['items']],
        'cart-items' => ['model' => CartItem::class, 'relations' => ['cart']],
        'cart_items' => ['model' => CartItem::class, 'relations' => ['cart']],
        'orders' => ['model' => Order::class, 'relations' => ['items', 'discounts']],
        'order-items' => ['model' => OrderItem::class, 'relations' => ['order']],
        'order_items' => ['model' => OrderItem::class, 'relations' => ['order']],
        'discounts' => ['model' => Discount::class],
        'order-discounts' => ['model' => OrderDiscount::class, 'relations' => ['order', 'discount']],
        'order_discounts' => ['model' => OrderDiscount::class, 'relations' => ['order', 'discount']],
        'warranty-requests' => ['model' => WarrantyRequest::class, 'relations' => ['order', 'orderItem']],
        'warranty_requests' => ['model' => WarrantyRequest::class, 'relations' => ['order', 'orderItem']],
    ];

    public function index(Request $request): JsonResponse
    {
        [$modelClass, $relations] = $this->resolve($request);

        $query = $modelClass::query();

        if ($relations) {
            $query->with($relations);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lay danh sach du lieu thanh cong',
            'data' => $query->orderByDesc('id')->get(),
        ]);
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        [$modelClass, $relations] = $this->resolve($request);
        $query = $modelClass::query();

        if ($relations) {
            $query->with($relations);
        }

        $record = $query->find((int) $id);

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay du lieu',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lay du lieu thanh cong',
            'data' => $record,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        [$modelClass, $relations, $resource] = $this->resolve($request);
        $model = new $modelClass;
        $record = $modelClass::create($this->payload($request, $model, $resource));

        return response()->json([
            'success' => true,
            'message' => 'Tao du lieu thanh cong',
            'data' => $this->fresh($record, $relations),
        ], 201);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        [$modelClass, $relations, $resource] = $this->resolve($request);
        $record = $modelClass::find((int) $id);

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay du lieu',
            ], 404);
        }

        $record->fill($this->payload($request, $record, $resource));
        $record->save();
        $freshRecord = $this->fresh($record, $relations);

        if ($record instanceof Order && $record->wasChanged('status')) {
            $this->notifications->sendStatusUpdated($freshRecord instanceof Order ? $freshRecord : $record);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cap nhat du lieu thanh cong',
            'data' => $freshRecord,
        ]);
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        [$modelClass] = $this->resolve($request);
        $record = $modelClass::find((int) $id);

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay du lieu',
            ], 404);
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xoa du lieu thanh cong',
            'data' => null,
        ]);
    }

    private function resolve(Request $request): array
    {
        $resource = (string) $request->route('resource');
        $config = self::RESOURCES[$resource] ?? null;

        abort_if(! $config, 404, 'Resource khong duoc ho tro');

        return [
            $config['model'],
            $config['relations'] ?? [],
            $resource,
        ];
    }

    private function payload(Request $request, Model $model, string $resource): array
    {
        return collect($request->all())
            ->only($model->getFillable())
            ->all();
    }

    private function fresh(Model $record, array $relations): Model
    {
        return $record->fresh($relations) ?? $record;
    }
}
