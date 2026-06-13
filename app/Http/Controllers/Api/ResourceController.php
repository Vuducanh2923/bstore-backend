<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Models\WarrantyPolicy;
use App\Models\WarrantyRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ResourceController extends Controller
{
    private const RESOURCES = [
        'roles' => ['model' => Role::class],
        'users' => ['model' => User::class, 'relations' => ['role']],
        'brands' => ['model' => Brand::class],
        'categories' => ['model' => Category::class],
        'products' => ['model' => Product::class, 'relations' => ['category', 'brand', 'variants', 'images', 'warrantyPolicy']],
        'product-variants' => ['model' => ProductVariant::class, 'relations' => ['product', 'images', 'inventory']],
        'product_variants' => ['model' => ProductVariant::class, 'relations' => ['product', 'images', 'inventory']],
        'inventories' => ['model' => Inventory::class, 'relations' => ['variant']],
        'inventory-transactions' => ['model' => InventoryTransaction::class, 'relations' => ['variant']],
        'inventory_transactions' => ['model' => InventoryTransaction::class, 'relations' => ['variant']],
        'product-images' => ['model' => ProductImage::class, 'relations' => ['product', 'variant']],
        'product_images' => ['model' => ProductImage::class, 'relations' => ['product', 'variant']],
        'warranty-policies' => ['model' => WarrantyPolicy::class],
        'warranty_policies' => ['model' => WarrantyPolicy::class],
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
        'payments' => ['model' => Payment::class, 'relations' => ['transactions', 'invoices']],
        'payment-transactions' => ['model' => PaymentTransaction::class, 'relations' => ['payment']],
        'payment_transactions' => ['model' => PaymentTransaction::class, 'relations' => ['payment']],
        'invoices' => ['model' => Invoice::class, 'relations' => ['payment']],
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

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay du lieu',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        [$modelClass, $relations, $resource] = $this->resolve($request);
        $model = new $modelClass();
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

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay du lieu',
            ], 404);
        }

        $record->fill($this->payload($request, $record, $resource));
        $record->save();

        return response()->json([
            'success' => true,
            'message' => 'Cap nhat du lieu thanh cong',
            'data' => $this->fresh($record, $relations),
        ]);
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        [$modelClass] = $this->resolve($request);
        $record = $modelClass::find((int) $id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay du lieu',
            ], 404);
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xoa du lieu thanh cong',
        ]);
    }

    private function resolve(Request $request): array
    {
        $resource = (string) $request->route('resource');
        $config = self::RESOURCES[$resource] ?? null;

        abort_if(!$config, 404, 'Resource khong duoc ho tro');

        return [
            $config['model'],
            $config['relations'] ?? [],
            $resource,
        ];
    }

    private function payload(Request $request, Model $model, string $resource): array
    {
        $input = $request->all();

        if ($model instanceof User && isset($input['name']) && empty($input['full_name'])) {
            $input['full_name'] = $input['name'];
        }

        $payload = collect($input)
            ->only($model->getFillable())
            ->all();

        if ($model instanceof User) {
            if (!empty($payload['password'])) {
                $payload['password'] = Hash::make($payload['password']);
            }
        }

        foreach (['specifications', 'response_data'] as $jsonColumn) {
            if (isset($payload[$jsonColumn]) && is_string($payload[$jsonColumn])) {
                $decoded = json_decode($payload[$jsonColumn], true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload[$jsonColumn] = $decoded;
                }
            }
        }

        return $payload;
    }

    private function fresh(Model $record, array $relations): Model
    {
        return $record->fresh($relations) ?? $record;
    }
}
