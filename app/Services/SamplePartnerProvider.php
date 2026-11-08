<?php
declare(strict_types=1);

namespace App\Services;

final class SamplePartnerProvider implements PartnerProductProvider
{
    private string $samplePath;

    public function __construct(?string $samplePath = null)
    {
        $this->samplePath = $samplePath ?? APP_ROOT . '/sample/accesstrade_datafeeds.json';
    }

    public function name(): string
    {
        return 'MAU';
    }

    public function fetch(int $trang, int $gioiHan, string $tenMien): array
    {
        if (!is_file($this->samplePath)) {
            throw new \RuntimeException('Không tìm thấy dữ liệu mẫu AccessTrade.');
        }
        $raw = @file_get_contents($this->samplePath);
        if ($raw === false) {
            throw new \RuntimeException('Không đọc được dữ liệu mẫu AccessTrade.');
        }
        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Dữ liệu mẫu AccessTrade không hợp lệ.');
        }
        if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
            throw new \RuntimeException('Dữ liệu mẫu AccessTrade không hợp lệ.');
        }

        $items = [];
        foreach ($payload['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = PartnerProductMapper::normalize($row, $tenMien, true);
            if ($item !== null && $item['ten_mien'] === $tenMien) {
                $items[] = $item;
            }
        }
        $offset = (max(1, $trang) - 1) * max(1, $gioiHan);
        return array_slice($items, $offset, max(1, $gioiHan));
    }
}
