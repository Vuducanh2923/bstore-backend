<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function image(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]);

        $file = $data['image'];
        $directory = public_path('uploads/products');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = now()->format('YmdHis').'-'.Str::random(12).'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);

        $path = 'uploads/products/'.$filename;

        return response()->json([
            'success' => true,
            'message' => 'Upload anh thanh cong',
            'data' => [
                'image_url' => $path,
                'url' => asset($path),
            ],
        ], 201);
    }
}
