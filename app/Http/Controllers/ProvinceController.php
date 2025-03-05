<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Province;
use App\Helpers\DateHelper;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    /**
     * Get all provinces with uuid, name, and formatted created_at.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $provinces = Province::select('uuid', 'name', 'created_at')->get()
                ->map(function ($province) {
                    return [
                        'uuid' => $province->uuid,
                        'name' => $province->name,
                        'created_at' => DateHelper::formatDate($province->created_at),
                    ];
                });

            return ApiResponse::sendResponse($provinces, 'Provinces retrieved successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to load provinces', ['error' => $e->getMessage()], 500);
        }
    }
}
