<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class UploadController extends Controller
{
    public function __construct(private readonly CloudinaryService $cloudinaryService) {}

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
                'message' => 'Upload anh len Cloudinary that bai',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Upload anh thanh cong',
            'data' => [
                'image_url' => $uploadedImage['secure_url'],
                'url' => $uploadedImage['secure_url'],
                'public_id' => $uploadedImage['public_id'],
            ],
        ], 201);
    }
}
