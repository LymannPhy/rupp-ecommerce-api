<?php

namespace App\Http\Controllers;

use App\Helpers\PaginationHelper;
use App\Http\Responses\ApiResponse;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Illuminate\Support\Facades\URL;


class SupplierController extends Controller
{
    public function showQRModal()
    {
        $apiBaseUrl = env('API_BASE_URL', config('app.url')); 

        return view('suppliers.qr_modal', compact('apiBaseUrl'));
    }


    /**
     * Show supplier profile page.
     *
     * @param string $uuid
     * @return \Illuminate\View\View
     */
    public function showSupplierProfile($uuid)
    {
        $supplier = Supplier::where('uuid', $uuid)->first();

        if (!$supplier) {
            abort(404, 'Supplier not found');
        }

        return view('suppliers.profile', compact('supplier'));
    }


    /**
     * Generate a QR code for a supplier profile.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateSupplierQRCode($uuid)
    {
        try {
            // Retrieve the supplier by UUID
            $supplier = Supplier::where('uuid', $uuid)->first();

            if (!$supplier) {
                return ApiResponse::error('Supplier not found', [], 404);
            }

            // ✅ Generate URL for supplier profile page
            $supplierProfileUrl = URL::to('/supplier/' . $supplier->uuid);

            // ✅ Generate QR Code with the profile URL
            $qrCode = QrCode::create($supplierProfileUrl) 
                ->setEncoding(new Encoding('UTF-8'))
                ->setErrorCorrectionLevel(ErrorCorrectionLevel::Low)
                ->setSize(300)
                ->setMargin(10);

            // Generate PNG format
            $writer = new PngWriter();
            $qrCodeResult = $writer->write($qrCode);

            // Convert to Base64
            $qrCodeBase64 = 'data:image/png;base64,' . base64_encode($qrCodeResult->getString());

            return ApiResponse::sendResponse([
                'qr_code' => $qrCodeBase64, 
            ], 'QR Code generated successfully', 200);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to generate QR Code', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Load all suppliers with optional search and pagination, using PaginationHelper.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllSuppliers(Request $request)
    {
        try {
            // Get search query (if any)
            $search = $request->query('search');

            // Define query
            $query = Supplier::query();

            // Apply search filter
            if ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            }

            // Paginate results (default 10 per page)
            $suppliers = $query->orderBy('created_at', 'desc')->paginate($request->query('per_page', 10));

            // Format the response using PaginationHelper
            $formattedResponse = PaginationHelper::formatPagination($suppliers, $suppliers->items());

            return ApiResponse::sendResponse($formattedResponse, 'Suppliers loaded successfully', 200);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to load suppliers', ['error' => $e->getMessage()], 500);
        }
    }

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
