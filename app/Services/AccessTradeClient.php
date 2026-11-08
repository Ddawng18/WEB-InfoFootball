<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class AccessTradeClient implements PartnerProductProvider
{
    private string $baseUrl;
    private string $token;
    private array $keywords;
    private int $receivedCount = 0;
    private ?float $deadline = null;

    public function __construct()
    {
        $this->baseUrl = rtrim(Config::string('accesstrade', 'base_url', 'https://api.accesstrade.vn/'), '/') . '/';
        $this->token = trim(Config::string('accesstrade', 'token', ''));
        $this->keywords = array_values(array_filter(array_map(
            static fn (string $keyword): string => mb_strtolower(trim($keyword), 'UTF-8'),
            explode(',', Config::string('accesstrade', 'keywords', ''))
        )));
    }

    public function name(): string
    {
        return 'ACCESSTRADE';
    }

    public function setDeadline(float $deadline): void
    {
        $this->deadline = $deadline;
    }

    public function receivedCount(): int
    {
        return $this->receivedCount;
    }

    public function fetch(int $trang, int $gioiHan, string $tenMien): array
    {
        $this->receivedCount = 0;
        if (trim($this->token) === '') {
            throw new \RuntimeException('Thiếu token AccessTrade');
        }
        if (preg_match('/[\s\x00-\x1F\x7F]/', $this->token) === 1) {
            throw new \RuntimeException('Token AccessTrade không hợp lệ.');
        }
        $limit = min(200, max(1, $gioiHan));
        $url = $this->baseUrl . 'v1/datafeeds?' . http_build_query([
            'domain' => $tenMien,
            'page' => max(1, $trang),
            'limit' => $limit,
        ]);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) === false) {
            throw new \RuntimeException('Cấu hình endpoint AccessTrade không hợp lệ.');
        }

        $remaining = $this->deadline !== null ? $this->deadline - microtime(true) : 10.0;
        if ($remaining <= 0) {
            throw new \RuntimeException('Đã hết thời gian đồng bộ AccessTrade.');
        }
        $timeoutMs = min(10000, max(1, (int) floor($remaining * 1000)));
        $connectTimeoutMs = min(5000, $timeoutMs);
        $body = '';
        $overLimit = false;
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Không khởi tạo được kết nối AccessTrade.');
        }
        $configured = curl_setopt_array($handle, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT_MS => $connectTimeoutMs,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_HTTPHEADER => [
                'Authorization: Token ' . $this->token,
                'Content-Type: application/json',
            ],
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$overLimit): int {
                if (strlen($body) + strlen($chunk) > 5 * 1024 * 1024) {
                    $overLimit = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        if (!$configured) {
            curl_close($handle);
            throw new \RuntimeException('Không cấu hình được kết nối AccessTrade.');
        }
        $result = curl_exec($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($overLimit) {
            throw new \RuntimeException('Phản hồi AccessTrade vượt quá giới hạn 5 MB.');
        }
        if ($result === false) {
            throw new \RuntimeException('Không kết nối được AccessTrade.');
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException('AccessTrade trả mã HTTP ' . $httpCode . '.');
        }
        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('JSON AccessTrade không hợp lệ.');
        }
        if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
            throw new \RuntimeException('Cấu trúc phản hồi AccessTrade không hợp lệ.');
        }

        $this->receivedCount = count($payload['data']);
        $items = [];
        foreach ($payload['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = PartnerProductMapper::normalize($row, $tenMien, false);
            if ($item === null) {
                continue;
            }
            $name = mb_strtolower($item['ten'], 'UTF-8');
            if ($this->keywords !== [] && !$this->matchesKeyword($name)) {
                continue;
            }
            $items[] = $item;
        }
        return $items;
    }

    private function matchesKeyword(string $name): bool
    {
        foreach ($this->keywords as $keyword) {
            if ($keyword !== '' && mb_strpos($name, $keyword, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    }
}
