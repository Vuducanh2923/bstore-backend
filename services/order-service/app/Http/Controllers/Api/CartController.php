<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly CartService $cartService) {}

    // Lấy toàn bộ dữ liệu.
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Lấy giỏ hàng thành công',
            'data' => $this->cartService->forUser($this->authenticatedUserId($request)),
        ]);
    }

    // Lấy toàn bộ dữ liệu.
    public function show(Request $request, int|string $id): JsonResponse
    {
        $cart = $this->cartService->findForUser(
            $this->authenticatedUserId($request),
            (int) $id,
        );

        if (! $cart) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy giỏ hàng',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $cart,
        ]);
    }

    // Làm mới hoặc đặt lại cho paid đơn hàng.
    public function clearForPaidOrder(int|string $orderId): JsonResponse
    {
        $result = $this->cartService->clearForPaidOrder((int) $orderId);

        if (! $result['order_found']) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng để xóa giỏ hàng',
                'data' => $result,
            ], 404);
        }

        if (! $result['paid']) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ được xóa giỏ hàng của đơn đã thanh toán',
                'data' => $result,
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa sản phẩm trong giỏ hàng sau khi thanh toán thành công',
            'data' => $result,
        ]);
    }

    // Tạo hoặc lưu dữ liệu theo nghiệp vụ của hàm.
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
            'message' => 'Tạo giỏ hàng thành công',
            'data' => $cart,
        ], 201);
    }

    // Tạo hoặc lưu mặt hàng.
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
            'message' => 'Thêm sản phẩm vào giỏ hàng thành công',
            'data' => $item,
        ], 201);
    }

    // Cập nhật mặt hàng.
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
                'message' => 'Không tìm thấy sản phẩm trong giỏ hàng',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật giỏ hàng thành công',
            'data' => $item,
        ]);
    }

    // Xóa hoặc hủy mặt hàng.
    public function destroyItem(Request $request, int|string $id): JsonResponse
    {
        if (! $this->cartService->deleteItem($this->authenticatedUserId($request), (int) $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sản phẩm trong giỏ hàng',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Xóa sản phẩm khỏi giỏ hàng thành công',
            'data' => null,
        ]);
    }

    // Thực hiện authenticated người dùng id.
    private function authenticatedUserId(Request $request): int
    {
        return (int) data_get($request->attributes->get('auth_user'), 'id');
    }
}
