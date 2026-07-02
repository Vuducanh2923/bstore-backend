<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ResourceController extends Controller
{
    private const RESOURCES = [
        'roles' => ['model' => Role::class],
        'users' => ['model' => User::class, 'relations' => ['role']],
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
        $input = $request->all();

        if ($model instanceof User && isset($input['name']) && empty($input['full_name'])) {
            $input['full_name'] = $input['name'];
        }

        if ($model instanceof User) {
            unset($input['role_id'], $input['role']);
        }

        $payload = collect($input)
            ->only($model->getFillable())
            ->all();

        if ($model instanceof User && ! empty($payload['password'])) {
            $payload['password'] = Hash::make($payload['password']);
        }

        return $payload;
    }

    private function fresh(Model $record, array $relations): Model
    {
        return $record->fresh($relations) ?? $record;
    }
}
