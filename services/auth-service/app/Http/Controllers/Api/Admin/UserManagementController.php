<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\CustomerOrderClient;
use App\Services\UserManagementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly UserManagementService $users,
        private readonly CustomerOrderClient $orders,
    ) {}

    public function staff(Request $request): JsonResponse
    {
        $staff = $this->users->staff($request->only(['page', 'limit', 'per_page', 'search', 'keyword', 'status']));

        return response()->json([
            'success' => true,
            'message' => 'Lay danh sach nhan vien thanh cong',
            'data' => $staff->items(),
            'pagination' => [
                'page' => $staff->currentPage(),
                'limit' => $staff->perPage(),
                'total' => $staff->total(),
                'totalPages' => $staff->lastPage(),
            ],
        ]);
    }

    public function customers(Request $request): JsonResponse
    {
        $customers = $this->users->customers($request->only(['page', 'limit', 'per_page', 'search', 'keyword', 'status']));

        return response()->json([
            'success' => true,
            'message' => 'Lay danh sach khach hang thanh cong',
            'data' => $customers->items(),
            'pagination' => [
                'page' => $customers->currentPage(),
                'limit' => $customers->perPage(),
                'total' => $customers->total(),
                'totalPages' => $customers->lastPage(),
            ],
        ]);
    }

    public function showCustomer(int|string $id): JsonResponse
    {
        $customer = $this->users->customer((int) $id);

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay khach hang',
                'data' => null,
            ], 404);
        }

        try {
            $orders = $this->orders->ordersForCustomer($customer->id);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lay chi tiet khach hang thanh cong',
            'data' => [
                'customer' => $this->customerProfile($customer),
                'addresses' => $customer->addresses,
                'orders' => $orders,
            ],
        ]);
    }

    public function updateCustomerStatus(UpdateUserStatusRequest $request, int|string $id): JsonResponse
    {
        $customer = $this->users->updateCustomerStatus((int) $id, $request->validated('status'));

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay khach hang',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cap nhat trang thai khach hang thanh cong',
            'data' => $this->customerProfile($customer),
        ]);
    }

    public function storeStaff(StoreStaffRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json([
            'success' => true,
            'message' => 'Tao nhan vien thanh cong',
            'data' => $this->users->createStaff($data),
        ], 201);
    }

    public function updateStaff(UpdateStaffRequest $request, int|string $id): JsonResponse
    {
        $staff = $this->users->updateStaff((int) $id, $request->validated());

        if (! $staff) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay nhan vien',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cap nhat nhan vien thanh cong',
            'data' => $staff,
        ]);
    }

    public function updateStaffStatus(UpdateUserStatusRequest $request, int|string $id): JsonResponse
    {
        $staff = $this->users->updateStaffStatus((int) $id, $request->validated('status'));

        if (! $staff) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay nhan vien',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cap nhat trang thai nhan vien thanh cong',
            'data' => $staff,
        ]);
    }

    public function updateRole(Request $request, int|string $id): JsonResponse
    {
        $request->merge([
            'role' => strtoupper((string) $request->input('role')),
        ]);

        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(User::assignableRoles())],
        ]);

        $user = User::with('role')->find((int) $id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay nguoi dung',
                'data' => null,
            ], 404);
        }

        try {
            $user = $this->users->updateRole($request->user(), $user, $data['role']);
        } catch (AuthorizationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cap nhat vai tro thanh cong',
            'data' => $user,
        ]);
    }

    public function destroyStaff(int|string $id): JsonResponse
    {
        if (! $this->users->deleteStaff((int) $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay nhan vien',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Xoa nhan vien thanh cong',
            'data' => null,
        ]);
    }

    public function destroyCustomer(int|string $id): JsonResponse
    {
        if (! $this->users->deleteCustomer((int) $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay khach hang',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Xoa khach hang thanh cong',
            'data' => null,
        ]);
    }

    private function customerProfile(User $customer): array
    {
        return [
            'id' => $customer->id,
            'full_name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'province' => $customer->province,
            'district' => $customer->district,
            'ward' => $customer->ward,
            'default_shipping_address' => $customer->default_shipping_address,
            'gender' => $customer->gender,
            'date_of_birth' => $customer->date_of_birth,
            'avatar' => $customer->avatar,
            'status' => $customer->status,
            'last_login_at' => $customer->last_login_at,
            'created_at' => $customer->created_at,
        ];
    }
}
