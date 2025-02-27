<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    /**
     * Create a new supplier.
     */
    public function store(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:suppliers,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'avatar' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation error', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Create supplier
            $supplier = Supplier::create([
                'uuid' => Str::uuid(),
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'avatar' => $request->avatar,
            ]);

            DB::commit();

            return ApiResponse::sendResponse($supplier, 'Supplier created successfully', 201);
        } catch (\Exception $e) {
            DB::rollback();
            return ApiResponse::error('Failed to create supplier', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing supplier by UUID.
     */
    public function update(Request $request, $uuid)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:suppliers,email,' . $uuid . ',uuid',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'avatar' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation error', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Find supplier by UUID
            $supplier = Supplier::where('uuid', $uuid)->first();

            if (!$supplier) {
                return ApiResponse::error('Supplier not found', [], 404);
            }

            // Update supplier data
            $supplier->update($request->only(['name', 'email', 'phone', 'address', 'avatar']));

            DB::commit();

            return ApiResponse::sendResponse($supplier, 'Supplier updated successfully', 200);
        } catch (\Exception $e) {
            DB::rollback();
            return ApiResponse::error('Failed to update supplier', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a supplier by UUID.
     */
    public function delete($uuid)
    {
        try {
            DB::beginTransaction();

            // Find supplier by UUID
            $supplier = Supplier::where('uuid', $uuid)->first();

            if (!$supplier) {
                return ApiResponse::error('Supplier not found', [], 404);
            }

            $supplier->delete();

            DB::commit();

            return ApiResponse::sendResponse([], 'Supplier deleted successfully', 200);
        } catch (\Exception $e) {
            DB::rollback();
            return ApiResponse::error('Failed to delete supplier', ['error' => $e->getMessage()], 500);
        }
    }
}
