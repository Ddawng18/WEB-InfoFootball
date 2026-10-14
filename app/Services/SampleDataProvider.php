<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Nguồn dữ liệu mẫu: đọc bundle JSON trong thư mục sample/.
 * Dùng khi chưa có key API hoặc API hết quota -> demo vẫn chạy.
 */
final class SampleDataProvider implements FootballDataProvider
{
    private const NGUON = 'SAMPLE';
    private string $sampleDir;

    public function __construct(?string $sampleDir = null)
    {
        $this->sampleDir = $sampleDir ?? APP_ROOT . '/sample';
    }

    public function name(): string
    {
        return self::NGUON;
    }

    public function fetchLive(): array
    {
        return $this->doc('live.json');
    }

    public function fetchFixtures(?string $date = null): array
    {
        return $this->doc('fixtures.json');
    }

    public function fetchLeagues(): array
    {
        return $this->doc('leagues.json');
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function doc(string $file): array
    {
        $path = $this->sampleDir . '/' . $file;
        $rong = ['countries' => [], 'leagues' => [], 'teams' => [], 'matches' => []];
        if (!is_file($path)) {
            return $rong;
        }
        $raw = file_get_contents($path);
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return $rong;
        }
        foreach (array_keys($rong) as $key) {
            $rong[$key] = isset($data[$key]) && is_array($data[$key]) ? $data[$key] : [];
        }
        return $rong;
    }
}
