<?php
declare(strict_types=1);

namespace App\Core;

/** Chuẩn hóa phản hồi JSON cho API: { ok, data, meta }. */
final class JsonResponse
{
    /** @param array<string, mixed> $payload */
    public static function send(array $payload, int $http = 200): void
    {
        if (!headers_sent()) {
            http_response_code($http);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param mixed $data @param array<string, mixed> $meta */
    public static function ok($data, array $meta = [], int $http = 200): void
    {
        self::send(['ok' => true, 'data' => $data, 'meta' => $meta], $http);
    }

    /** @param array<string, mixed> $meta */
    public static function error(string $message, array $meta = [], int $http = 400): void
    {
        self::send(['ok' => false, 'data' => null, 'meta' => ['thong_bao' => $message] + $meta], $http);
    }
}
