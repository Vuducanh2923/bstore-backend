<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class UserManagementService
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    private const USER_FIELDS = [
        'full_name',
        'email',
        'phone',
        'address',
        'province',
        'district',
        'ward',
        'default_shipping_address',
        'gender',
        'date_of_birth',
        'avatar',
        'status',
    ];

    public function staff(array $filters = []): LengthAwarePaginator
    {
        return $this->usersByRole(User::ROLE_STAFF, $filters);
    }

    public function customers(array $filters = []): LengthAwarePaginator
    {
        return $this->usersByRole(User::ROLE_CUSTOMER, $filters)
            ->through(fn (User $user) => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'default_shipping_address' => $user->default_shipping_address,
                'status' => $user->status,
                'created_at' => $user->created_at,
            ]);
    }

    public function createStaff(array $data): User
    {
        $data = $this->normalizeName($data);
        $role = $this->role(User::ROLE_STAFF);

        $user = new User;
        $user->forceFill([
            ...Arr::only($data, self::USER_FIELDS),
            'password' => Hash::make($data['password']),
            'role_id' => $role->id,
            'status' => $data['status'] ?? 'active',
        ]);
        $user->save();

        return $user->refresh()->load('role');
    }

    public function customer(int $id): ?User
    {
        return $this->userByRole($id, User::ROLE_CUSTOMER)?->load(['role', 'addresses']);
    }

    public function updateCustomerStatus(int $id, string $status): ?User
    {
        return $this->updateStatusByRole($id, User::ROLE_CUSTOMER, $status);
    }

    public function updateStaff(int $id, array $data): ?User
    {
        $staff = $this->userByRole($id, User::ROLE_STAFF);

        if (! $staff) {
            return null;
        }

        $data = $this->normalizeName($data);
        $payload = Arr::only($data, self::USER_FIELDS);

        if (array_key_exists('password', $data) && filled($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $staff->fill($payload);
        $staff->save();

        return $staff->refresh()->load('role');
    }

    public function updateStaffStatus(int $id, string $status): ?User
    {
        return $this->updateStatusByRole($id, User::ROLE_STAFF, $status);
    }

    public function updateRole(User $actor, User $user, string $requestedRole): User
    {
        $role = $this->role(strtoupper($requestedRole));

        $this->authorizeRoleChange($actor, $user);

        $user->forceFill([
            'role_id' => $role->id,
        ])->save();

        return $user->refresh()->load('role');
    }

    public function deleteStaff(int $id): bool
    {
        return $this->deleteByRole($id, User::ROLE_STAFF);
    }

    public function deleteCustomer(int $id): bool
    {
        return $this->deleteByRole($id, User::ROLE_CUSTOMER);
    }

    private function usersByRole(string $role, array $filters = []): LengthAwarePaginator
    {
        $query = User::query()
            ->with('role')
            ->where('role_id', $this->roleId($role));

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $search = trim((string) ($filters['search'] ?? $filters['keyword'] ?? ''));

        if ($search !== '') {
            $query->where(function ($userQuery) use ($search): void {
                $userQuery
                    ->where('full_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        return $query
            ->orderByDesc('id')
            ->paginate($this->perPage($filters), ['*'], 'page', max(1, (int) ($filters['page'] ?? 1)));
    }

    private function userByRole(int $id, string $role): ?User
    {
        return User::query()
            ->where('role_id', $this->roleId($role))
            ->find($id);
    }

    private function updateStatusByRole(int $id, string $role, string $status): ?User
    {
        $user = $this->userByRole($id, $role);

        if (! $user) {
            return null;
        }

        $user->forceFill(['status' => $status])->save();

        return $user->refresh()->load('role');
    }

    private function deleteByRole(int $id, string $role): bool
    {
        $user = User::query()
            ->where('role_id', $this->roleId($role))
            ->find($id);

        if (! $user) {
            return false;
        }

        return (bool) $user->delete();
    }

    private function normalizeName(array $data): array
    {
        if (isset($data['name']) && empty($data['full_name'])) {
            $data['full_name'] = $data['name'];
        }

        unset($data['name']);

        return $data;
    }

    private function role(string $name): Role
    {
        return Role::query()->findOrFail($this->roleId($name));
    }

    private function authorizeRoleChange(User $actor, User $user): void
    {
        $actor->loadMissing('role');
        $user->loadMissing('role');

        if ($actor->is($user) && strtoupper((string) $user->role?->name) === User::ROLE_ADMIN) {
            throw new AuthorizationException('Admin khong duoc tu ha quyen');
        }

        if (strtoupper((string) $user->role?->name) === User::ROLE_ADMIN && $this->adminCount() <= 1) {
            throw new AuthorizationException('Khong duoc ha quyen admin cuoi cung');
        }
    }

    private function adminCount(): int
    {
        return User::where('role_id', $this->roleId(User::ROLE_ADMIN))->count();
    }

    private function roleId(string $name): int
    {
        $role = strtoupper($name);

        return (int) Cache::remember(
            'auth:role_id:'.$role,
            1800,
            fn () => Role::query()
                ->where('name', $role)
                ->value('id')
                ?? Role::query()->whereRaw('UPPER(name) = ?', [$role])->value('id')
        );
    }

    private function perPage(array $filters): int
    {
        return min(
            self::MAX_PER_PAGE,
            max(1, (int) ($filters['limit'] ?? $filters['per_page'] ?? self::DEFAULT_PER_PAGE))
        );
    }
}
