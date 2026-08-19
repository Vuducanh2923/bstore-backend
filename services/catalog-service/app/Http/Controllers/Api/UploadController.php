<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class UploadController extends Controller
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly CloudinaryService $cloudinaryService) {}

    // Thực hiện hình ảnh.
    public function image(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        try {
            $uploadedImage = $this->cloudinaryService->uploadProductImage($data['image']);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Tải ảnh lên Cloudinary thất bại',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tải ảnh thành công',
            'data' => [
                'image_url' => $uploadedImage['secure_url'],
                'url' => $uploadedImage['secure_url'],
                'public_id' => $uploadedImage['public_id'],
            ],
        ], 201);
    }

    public function avatar(Request $request): JsonResponse
    {
        $data = $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);

        try {
            $uploadedImage = $this->cloudinaryService->uploadAvatarImage($data['image']);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['success' => false, 'message' => 'Tải ảnh đại diện lên Cloudinary thất bại'], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tải ảnh đại diện thành công',
            'data' => [
                'image_url' => $uploadedImage['secure_url'],
                'url' => $uploadedImage['secure_url'],
                'public_id' => $uploadedImage['public_id'],
            ],
        ], 201);
    }
}
