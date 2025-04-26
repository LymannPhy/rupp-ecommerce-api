<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class ServiceController extends Controller
{
    /**
     * Store a new service.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {
            $service = Service::create([
                'title' => $request->title,
                'description' => $request->description,
                'image' => $request->image,
            ]);

            DB::commit();
            return ApiResponse::sendResponse($service, 'Service created successfully.', 201);

        } catch (Exception $e) {
            return ApiResponse::rollback('Failed to create service.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Load all services.
     */
    public function index()
    {
        try {
            $services = Service::all();
            return ApiResponse::sendResponse($services, 'Services retrieved successfully.');
        } catch (Exception $e) {
            return ApiResponse::error('Failed to load services.', ['error' => $e->getMessage()]);
        }
    }
}
