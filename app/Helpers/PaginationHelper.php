<?php

namespace App\Helpers;

class PaginationHelper
{
    /**
     * Format Laravel pagination response with custom metadata structure.
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $pagination
     * @param array $data
     * @return array
     */
    public static function formatPagination($pagination, $data)
    {
        return [
            'data' => $data,
            'metadata' => [
                'page' => $pagination->currentPage(),
                'page_size' => $pagination->perPage(),
                'total_items' => $pagination->total(),
                'total_pages' => $pagination->lastPage(),
            ],
        ];
    }
}
