<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Trừu tượng hóa nguồn dữ liệu bóng đá (đa hình).
 * - LiveScoreApiClient: gọi API thật qua cURL.
 * - SampleDataProvider: đọc JSON mẫu trong thư mục sample/.
 * SyncService chỉ phụ thuộc interface này, nên đổi nguồn dữ liệu không đổi SyncService.
 *
 * Mỗi phương thức trả về "bundle" chuẩn hóa:
 *   [
 *     'countries' => [ ['ma'=>..., 'ten'=>...], ... ],
 *     'leagues'   => [ ['ma'=>..., 'ten'=>..., 'ma_quoc_gia'=>..., 'mua_giai'=>...], ... ],
 *     'teams'     => [ ['ma'=>..., 'ten'=>..., 'ma_quoc_gia'=>...], ... ],
 *     'matches'   => [ ['ma'=>..., 'ma_giai'=>..., 'ma_doi_nha'=>..., 'ma_doi_khach'=>...,
 *                       'thoi_gian_utc'=>..., 'trang_thai'=>..., 'phut'=>..., 'ti_so_nha'=>..., 'ti_so_khach'=>...], ... ],
 *   ]
 */
interface FootballDataProvider
{
    /** @return array<string, array<int, array<string, mixed>>> */
    public function fetchLive(): array;

    /** @return array<string, array<int, array<string, mixed>>> */
    public function fetchFixtures(?string $date = null): array;

    /** @return array<string, array<int, array<string, mixed>>> */
    public function fetchLeagues(): array;

    public function name(): string;
}
