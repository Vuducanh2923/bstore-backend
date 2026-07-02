<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\StoreAddressRequest;
use App\Http\Requests\Profile\UpdateAddressRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Lay thong tin tai khoan thanh cong',
            'data' => $this->profileData(request()->user()),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Cap nhat tai khoan thanh cong',
            'data' => $this->profileData($user->fresh('role')),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (! Hash::check($data['current_password'], (string) $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mat khau hien tai khong dung',
                'data' => null,
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($data['new_password']),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Doi mat khau thanh cong',
            'data' => null,
        ]);
    }

    public function addresses(): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();

        return response()->json([
            'success' => true,
            'message' => 'Lay danh sach dia chi thanh cong',
            'data' => $user->addresses()->orderByDesc('is_default')->orderByDesc('id')->get(),
        ]);
    }

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
            'message' => 'Them dia chi thanh cong',
            'data' => $address,
        ], 201);
    }

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
            'message' => 'Cap nhat dia chi thanh cong',
            'data' => $address->refresh(),
        ]);
    }

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
            'message' => 'Xoa dia chi thanh cong',
            'data' => null,
        ]);
    }

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
            'message' => 'Dat dia chi mac dinh thanh cong',
            'data' => $address->refresh(),
        ]);
    }

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

    private function ownedAddress(User $user, int $id): ?UserAddress
    {
        return $user->addresses()->whereKey($id)->first();
    }

    private function syncDefaultShippingAddress(User $user, UserAddress $address): void
    {
        $user->forceFill([
            'default_shipping_address' => $this->shippingAddressLine($address),
        ])->save();
    }

    private function shippingAddressLine(UserAddress $address): string
    {
        return collect([
            $address->address,
            $address->ward,
            $address->district,
            $address->province,
        ])->filter()->implode(', ');
    }

    private function notFoundAddress(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Khong tim thay dia chi',
            'data' => null,
        ], 404);
    }
}
