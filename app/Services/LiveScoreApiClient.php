<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Nguồn dữ liệu thật: gọi Live Score API qua cURL TỪ MÁY CHỦ.
 * Key/secret không bao giờ lộ ra trình duyệt.
 *
 * LƯU Ý: cấu trúc JSON thật cần xác minh ở công việc T0 (xem đề cương mục 2).
 * Phần ánh xạ dưới đây là khung; các tên trường có thể phải chỉnh theo JSON thật.
 */
final class LiveScoreApiClient implements FootballDataProvider
{
    private const NGUON = 'LIVESCORE';
    private string $baseUrl;
    private string $key;
    private string $secret;

    public function __construct()
    {
        $this->baseUrl = rtrim(Config::string('api', 'base_url', ''), '/') . '/';
        $this->key = Config::string('api', 'key', '');
        $this->secret = Config::string('api', 'secret', '');
    }

    public function name(): string
    {
        return self::NGUON;
    }

    public function fetchLive(): array
    {
        return $this->bao(self::NGUON, $this->get('scores/live.json', []));
    }

    public function fetchFixtures(?string $date = null): array
    {
        $params = [];
        if ($date !== null && $date !== '') {
            $params['date'] = $date;
        }
        return $this->bao(self::NGUON, $this->get('fixtures/matches.json', $params));
    }

    public function fetchLeagues(): array
    {
        return $this->bao(self::NGUON, $this->get('leagues/list.json', []));
    }

    /**
     * Gọi một endpoint, trả JSON đã giải mã hoặc null nếu lỗi.
     * @param array<string, string> $params
     * @return array<string, mixed>|null
     */
    private function get(string $path, array $params): ?array
    {
        if ($this->key === '' || $this->secret === '') {
            return null;
        }
        $params['key'] = $this->key;
        $params['secret'] = $this->secret;
        $url = $this->baseUrl . $path . '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'FastFootBalInfo/1.0',
        ]);
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $http >= 400) {
            error_log('LiveScore API lỗi: HTTP ' . $http . ' ' . $err);
            return null;
        }
        $data = json_decode((string) $body, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Ánh xạ JSON API thô sang bundle chuẩn hóa.
     * @param array<string, mixed>|null $json
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function bao(string $nguon, ?array $json): array
    {
        $out = ['countries' => [], 'leagues' => [], 'teams' => [], 'matches' => []];
        if ($json === null) {
            return $out;
        }

        // Danh sách trận có thể nằm ở data.match[] (theo repo mẫu).
        $matches = $json['data']['match'] ?? $json['data']['matches'] ?? [];
        if (is_array($matches)) {
            foreach ($matches as $m) {
                if (!is_array($m)) {
                    continue;
                }
                $out['matches'][] = [
                    'ma' => (string) ($m['id'] ?? ''),
                    'ma_giai' => (string) ($m['competition_id'] ?? $m['league_id'] ?? ''),
                    'ma_doi_nha' => (string) ($m['home_id'] ?? $m['home_name'] ?? ''),
                    'ma_doi_khach' => (string) ($m['away_id'] ?? $m['away_name'] ?? ''),
                    'thoi_gian_utc' => (string) ($m['time'] ?? $m['date'] ?? ''),
                    'trang_thai' => $this->anhXaTrangThai((string) ($m['status'] ?? '')),
                    'phut' => (int) ($m['minute'] ?? 0),
                    'ti_so_nha' => $this->tachTiSo($m['score'] ?? '', 'nha'),
                    'ti_so_khach' => $this->tachTiSo($m['score'] ?? '', 'khach'),
                ];
            }
        }

        return $out;
    }

    private function anhXaTrangThai(string $status): string
    {
        $s = mb_strtolower(trim($status), 'UTF-8');
        return match (true) {
            str_contains($s, 'finish'), str_contains($s, 'ft'), str_contains($s, 'end') => 'KET_THUC',
            str_contains($s, 'postpon') => 'HOAN',
            str_contains($s, 'cancel') => 'HUY',
            str_contains($s, 'half') => 'NGHI_GIUA_HIEP',
            str_contains($s, 'live'), str_contains($s, 'play') => 'DANG_DA',
            default => 'SAP_DIEN_RA',
        };
    }

    /** @param mixed $score chuỗi dạng "1 - 0" */
    private function tachTiSo($score, string $ben): int
    {
        if (!is_string($score)) {
            return 0;
        }
        $parts = preg_split('/\s*-\s*/', trim($score));
        if (!is_array($parts) || count($parts) < 2) {
            return 0;
        }
        $index = $ben === 'nha' ? 0 : 1;
        return isset($parts[$index]) && is_numeric($parts[$index]) ? (int) $parts[$index] : 0;
    }
}
