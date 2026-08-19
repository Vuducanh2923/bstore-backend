<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\StoreAddressRequest;
use App\Http\Requests\Profile\UpdateAddressRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\AuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly AuthTokenService $tokens) {}

    // Lấy toàn bộ dữ liệu.
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Lấy thông tin tài khoản thành công',
            'data' => $this->profileData(request()->user()),
        ]);
    }

    // Cập nhật dữ liệu theo nghiệp vụ của hàm.
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật tài khoản thành công',
            'data' => $this->profileData($user->fresh('role')),
        ]);
    }

    // Cập nhật mật khẩu.
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (! Hash::check($data['current_password'], (string) $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mật khẩu hiện tại không đúng',
                'data' => null,
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($data['new_password']),
        ])->save();

        $this->tokens->revokeAllForUser($user);

        return response()->json([
            'success' => true,
            'message' => 'Đổi mật khẩu thành công',
            'data' => null,
        ]);
    }

    // Thực hiện addresses.
    public function addresses(): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách địa chỉ thành công',
            'data' => $user->addresses()->orderByDesc('is_default')->orderByDesc('id')->get(),
        ]);
    }

    // Tạo hoặc lưu địa chỉ.
    public function storeAddress(StoreAddressRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $address = DB::connection('bstore_auth')->transaction(function () use ($user, $data): UserAddress {
            $shouldBeDefault = (bool) ($data['is_default'] ?? false)
                || ! $user->addresses()->exists();

            if ($shouldBeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            $address = $user->addresses()->create([
                ...$data,
                'is_default' => $shouldBeDefault,
            ]);

            if ($shouldBeDefault) {
                $this->syncDefaultShippingAddress($user, $address);
            }

            return $address->refresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Thêm địa chỉ thành công',
            'data' => $address,
        ], 201);
    }

    // Cập nhật địa chỉ.
    public function updateAddress(UpdateAddressRequest $request, int|string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $address = $this->ownedAddress($user, (int) $id);

        if (! $address) {
            return $this->notFoundAddress();
        }

        $data = $request->validated();

        DB::connection('bstore_auth')->transaction(function () use ($user, $address, $data): void {
            if ((bool) ($data['is_default'] ?? false)) {
                $user->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }

            $address->fill($data);
            $address->save();

            if ($address->is_default) {
                $this->syncDefaultShippingAddress($user, $address->refresh());
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật địa chỉ thành công',
            'data' => $address->refresh(),
        ]);
    }

    // Xóa hoặc hủy địa chỉ.
    public function destroyAddress(int|string $id): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();
        $address = $this->ownedAddress($user, (int) $id);

        if (! $address) {
            return $this->notFoundAddress();
        }

        DB::connection('bstore_auth')->transaction(function () use ($user, $address): void {
            $wasDefault = (bool) $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $user->forceFill(['default_shipping_address' => null])->save();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Xóa địa chỉ thành công',
            'data' => null,
        ]);
    }

    // Cập nhật mặc định địa chỉ.
    public function setDefaultAddress(int|string $id): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();
        $address = $this->ownedAddress($user, (int) $id);

        if (! $address) {
            return $this->notFoundAddress();
        }

        DB::connection('bstore_auth')->transaction(function () use ($user, $address): void {
            $user->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            $address->forceFill(['is_default' => true])->save();
            $this->syncDefaultShippingAddress($user, $address->refresh());
        });

        return response()->json([
            'success' => true,
            'message' => 'Đặt địa chỉ mặc định thành công',
            'data' => $address->refresh(),
        ]);
    }

    // Thực hiện hồ sơ dữ liệu.
    private function profileData(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'province' => $user->province,
            'district' => $user->district,
            'ward' => $user->ward,
            'default_shipping_address' => $user->default_shipping_address,
            'gender' => $user->gender,
            'date_of_birth' => $user->date_of_birth,
            'avatar' => $user->avatar,
            'status' => $user->status,
            'last_login_at' => $user->last_login_at,
            'created_at' => $user->created_at,
            'role' => $user->role,
        ];
    }

    // Thực hiện owned địa chỉ.
    private function ownedAddress(User $user, int $id): ?UserAddress
    {
        return $user->addresses()->whereKey($id)->first();
    }

    // Thực hiện đồng bộ mặc định shipping địa chỉ.
    private function syncDefaultShippingAddress(User $user, UserAddress $address): void
    {
        $user->forceFill([
            'default_shipping_address' => $this->shippingAddressLine($address),
        ])->save();
    }

    // Thực hiện shipping địa chỉ line.
    private function shippingAddressLine(UserAddress $address): string
    {
        return collect([
            $address->address,
            $address->ward,
            $address->district,
            $address->province,
        ])->filter()->implode(', ');
    }

    // Thực hiện not found địa chỉ.
    private function notFoundAddress(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Không tìm thấy địa chỉ',
            'data' => null,
        ], 404);
    }
}
