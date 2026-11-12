<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Trừu tượng hóa nguồn dữ liệu bóng đá.
 * Nguồn hiện tại: EspnClient (ESPN public API).
 * SyncService chỉ phụ thuộc interface này.
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

    /**
     * Lấy trạng thái/tỉ số hiện tại cho các trận đã lưu theo mã API.
     * Dùng khi trận đã rơi khỏi guide feed/scoreboard (server gián đoạn,
     * giải không nằm trong danh sách cấu hình...) nhưng vẫn còn trạng thái cũ.
     *
     * @param string[] $ids
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function fetchMatchesByIds(array $ids): array;

    /** @return array<string, array<int, array<string, mixed>>> */
    public function fetchLeagues(): array;

    public function name(): string;
}
