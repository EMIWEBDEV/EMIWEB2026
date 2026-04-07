<?php

namespace App\Helpers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class ResponseHelper
{
    /**
     * Hitung jumlah data yang benar-benar dikembalikan
     */
    private static function countRecords($data): int
    {
        if (is_null($data)) {
            return 0;
        }

        if ($data instanceof Collection) {
            return $data->count();
        }

        if (is_array($data)) {
            return count($data);
        }

        return 1; // single object
    }

    /**
     * Build meta response (enterprise safe)
     */
    private static function buildMeta(
        string $version_api,
        int $status,
        string $responseMessage,
        int $recordsReturned
    ): array {
        $safeBody = in_array(Request::method(), ['GET', 'DELETE'])
            ? Request::all()
            : new \stdClass(); // hindari bocor payload sensitif

        return [
            // tracing
            'request_id'        => (string) Str::uuid(),
            'trace_id'          => Str::random(16),

            // waktu & versi
            'timestamp'         => now()->toISOString(),
            'version_api'       => $version_api,
            'environment'       => app()->environment(),

            // request info
            'path'              => Request::path(),
            'method'            => Request::method(),
            'ip'                => Request::ip(),
            'user_agent'        => Request::header('User-Agent'),
            'locale'            => Request::header('Accept-Language'),
            'request_query'     => Request::query(),
            'request_body'      => $safeBody,

            // response info
            'response_status'   => $status,
            'response_message'  => $responseMessage,

            // performance
            'execution_time_ms' => defined('LARAVEL_START')
                ? round((microtime(true) - LARAVEL_START) * 1000, 2)
                : null,

            // summary data (AMAN)
            'data_summary' => [
                'records_returned' => $recordsReturned,
            ],
        ];
    }

    /**
     * Response sukses tanpa pagination
     */
    public static function success(
        $data = null,
        string $message = 'Berhasil',
        int $status = 200,
        string $version_api = 'v1'
    ) {
        return response()->json([
            'success' => true,
            'status'  => $status,
            'message' => $message,
            'result'  => $data,
            'meta'    => self::buildMeta(
                $version_api,
                $status,
                $message,
                self::countRecords($data)
            ),
        ], $status);
    }

    /**
     * Response sukses dengan pagination (custom data)
     */
    public static function successWithPaginationV2(
        $data,
        int $page,
        int $limit,
        int $total,
        string $message = 'Berhasil',
        int $status = 200,
        string $version_api = 'v1'
    ) {
        $paginator = new LengthAwarePaginator(
            $data,
            $total,
            $limit,
            $page,
            [
                'path'  => Request::url(),
                'query' => Request::query(),
            ]
        );

        $pg = $paginator->toArray();

        return response()->json([
            'success' => true,
            'status'  => $status,
            'message' => $message,
            'result'  => $pg['data'],
            'pagination' => [
                'total'        => $pg['total'],
                'per_page'     => $pg['per_page'],
                'current_page' => $pg['current_page'],
                'total_pages'  => $pg['last_page'],
                'from'         => $pg['from'],
                'to'           => $pg['to'],
                'links'        => $pg['links'],
            ],
            'meta' => self::buildMeta(
                $version_api,
                $status,
                $message,
                count($pg['data']) // hanya data halaman ini
            ),
        ], $status);
    }

    /**
     * Response error (aman & konsisten)
     */
    public static function error(
        string $message = 'Terjadi kesalahan',
        int $status = 500,
        string $version_api = 'v1'
    ) {
        return response()->json([
            'success' => false,
            'status'  => $status,
            'message' => $message,
            'result'  => null,
            'meta'    => self::buildMeta(
                $version_api,
                $status,
                $message,
                0
            ),
        ], $status);
    }
}
