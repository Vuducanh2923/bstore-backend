<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Lay gio hang thanh cong',
            'data' => $this->cartService->forUser($this->authenticatedUserId($request)),
        ]);
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $cart = $this->cartService->findForUser(
            $this->authenticatedUserId($request),
            (int) $id,
        );

        if (! $cart) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay gio hang',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $cart,
        ]);
    }

    public function clearForPaidOrder(int|string $orderId): JsonResponse
    {
        $result = $this->cartService->clearForPaidOrder((int) $orderId);

        if (! $result['order_found']) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay don hang de xoa gio hang',
                'data' => $result,
            ], 404);
        }

        if (! $result['paid']) {
            return response()->json([
                'success' => false,
                'message' => 'Chi duoc xoa gio hang cua don da thanh toan',
                'data' => $result,
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Da xoa san pham trong gio hang sau khi thanh toan thanh cong',
            'data' => $result,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $data['user_id'] = $this->authenticatedUserId($request);
        $data['status'] = 'active';

        $cart = $this->cartService->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Tao gio hang thanh cong',
            'data' => $cart,
        ], 201);
    }

    public function storeItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cart_id' => ['required', 'integer', 'min:1'],
            'product_variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $item = $this->cartService->addItem($this->authenticatedUserId($request), $data);

        return response()->json([
            'success' => true,
            'message' => 'Them san pham vao gio hang thanh cong',
            'data' => $item,
        ], 201);
    }

    public function updateItem(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $item = $this->cartService->updateItem(
            $this->authenticatedUserId($request),
            (int) $id,
            (int) $data['quantity'],
        );

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay san pham trong gio hang',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cap nhat gio hang thanh cong',
            'data' => $item,
        ]);
    }

    public function destroyItem(Request $request, int|string $id): JsonResponse
    {
        if (! $this->cartService->deleteItem($this->authenticatedUserId($request), (int) $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay san pham trong gio hang',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Xoa san pham khoi gio hang thanh cong',
            'data' => null,
        ]);
    }

    private function authenticatedUserId(Request $request): int
    {
        return (int) data_get($request->attributes->get('auth_user'), 'id');
    }
}
