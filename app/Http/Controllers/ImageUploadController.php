<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Http\Responses\ApiResponse;

class ImageUploadController extends Controller
{
    /**
     * Upload a single image.
     */
    public function uploadSingle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            $image = $request->file('image');
            $fileName = Str::uuid() . '.' . $image->getClientOriginalExtension();

            // Store in public/uploads
            $filePath = $image->storeAs('public/uploads', $fileName);

            return ApiResponse::sendResponse([
                'file_name' => $fileName,
                'file_path' => Storage::url($filePath), // Corrected public URL
                'file_type' => $image->getClientMimeType(),
                'file_size' => $image->getSize(),
                'message' => 'Image uploaded successfully ✅',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to upload image', ['error' => $e->getMessage()], 500);
        }
    }
    /**
     * Upload multiple images.
     */
    public function uploadMultiple(Request $request)
    {
        // Ensure request contains files
        if (!$request->hasFile('images')) {
            return ApiResponse::error('Validation Error', ['images' => ['No images uploaded.']], 422);
        }

        // Retrieve files and force them into an array if only one file exists
        $images = $request->file('images');

        if (!is_array($images)) {
            $images = [$images]; // Convert single file into an array
        }

        // Validate images
        $validator = Validator::make(['images' => $images], [
            'images' => 'required|array|min:1',
            'images.*' => 'file|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            $uploadedImages = [];

            foreach ($images as $image) {
                $fileName = Str::uuid() . '.' . $image->getClientOriginalExtension();
                $filePath = $image->storeAs('public/uploads', $fileName);

                $uploadedImages[] = [
                    'file_name' => $fileName,
                    'file_path' => Storage::url($filePath),
                    'file_type' => $image->getClientMimeType(),
                    'file_size' => $image->getSize(),
                ];
            }

            return ApiResponse::sendResponse([
                'images' => $uploadedImages,
                'message' => 'Images uploaded successfully ✅',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to upload images', ['error' => $e->getMessage()], 500);
        }
    }
}
