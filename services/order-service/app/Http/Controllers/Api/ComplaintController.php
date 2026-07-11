<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function __construct(private readonly ComplaintService $complaints) {}

    public function index(Request $request): JsonResponse
    {
        $complaints = $this->complaints->paginated(
            $request->only(['page', 'limit', 'per_page', 'status']),
            $this->authenticatedActor($request),
        );

        return response()->json([
            'success' => true,
            'message' => 'Lay danh sach khieu nai thanh cong',
            'data' => $this->complaints->serializeMany($complaints->items()),
            'pagination' => [
                'page' => $complaints->currentPage(),
                'limit' => $complaints->perPage(),
                'total' => $complaints->total(),
                'totalPages' => $complaints->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $complaint = $this->complaints->find((int) $id, $this->authenticatedActor($request));

        if (! $complaint) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay khieu nai',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lay chi tiet khieu nai thanh cong',
            'data' => $this->complaints->serialize($complaint),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:191'],
            'content' => ['required', 'string'],
        ]);

        $complaint = $this->complaints->create($data, $this->authenticatedActor($request));

        return response()->json([
            'success' => true,
            'message' => 'Gui khieu nai thanh cong',
            'data' => $this->complaints->serialize($complaint),
        ], 201);
    }

    public function process(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'reply' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        return $this->transitionResponse(
            $this->complaints->process((int) $id, $this->authenticatedActor($request), $data['reply'] ?? $data['note'] ?? null),
            'Nhan xu ly khieu nai thanh cong',
        );
    }

    public function resolve(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'reply' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        return $this->transitionResponse(
            $this->complaints->resolve((int) $id, $this->authenticatedActor($request), $data['reply'] ?? $data['note'] ?? null),
            'Giai quyet khieu nai thanh cong',
        );
    }

    public function reject(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'reply' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        return $this->transitionResponse(
            $this->complaints->reject((int) $id, $this->authenticatedActor($request), $data['reply'] ?? $data['note'] ?? null),
            'Tu choi khieu nai thanh cong',
        );
    }

    private function transitionResponse($complaint, string $message): JsonResponse
    {
        if (! $complaint) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay khieu nai',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->complaints->serialize($complaint),
        ]);
    }

    private function authenticatedActor(Request $request): array
    {
        return (array) $request->attributes->get('auth_user', []);
    }
}
