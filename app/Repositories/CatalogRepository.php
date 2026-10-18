<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\RoutineName;
use App\Models\League;
use App\Models\Team;

/** Truy cập dữ liệu danh mục: giải đấu, đội bóng, quốc gia (F4, F5). */
final class CatalogRepository extends BaseRepository
{
    /** @return array{items: League[], tong: int} */
    public function getLeagues(
        ?int $quocGiaId = null,
        ?string $khuVuc = null,
        ?string $tuKhoa = null,
        int $gioiHan = 1000,
        int $boQua = 0
    ): array {
        $rows = $this->call(RoutineName::DS_GIAI_DAU, [$quocGiaId, $khuVuc, $tuKhoa, $gioiHan, $boQua]);
        $items = [];
        foreach ($rows as $row) {
            $items[] = League::fromRow($row);
        }
        $tong = $rows === [] ? 0 : (int) ($rows[0]['tong'] ?? count($rows));
        return ['items' => $items, 'tong' => $tong];
    }

    /** @return array{items: Team[], tong: int} */
    public function getTeams(?int $giaiId = null, ?string $tuKhoa = null, int $gioiHan = 50, int $boQua = 0): array
    {
        $rows = $this->call(RoutineName::DS_DOI_BONG, [$giaiId, $tuKhoa, $gioiHan, $boQua]);
        $items = [];
        foreach ($rows as $row) {
            $items[] = Team::fromRow($row);
        }
        $tong = $rows === [] ? 0 : (int) ($rows[0]['tong'] ?? count($rows));
        return ['items' => $items, 'tong' => $tong];
    }

    public function getTeamById(int $id): ?Team
    {
        $rows = $this->call(RoutineName::CHI_TIET_DOI_BONG, [$id]);
        if ($rows === [] || ($rows[0]['ma'] ?? '') !== 'OK') {
            return null;
        }
        return Team::fromRow($rows[0]);
    }

    /** @return array{items: array<int, array<string, mixed>>, tong: int} */
    public function getNews(int $gioiHan = 10, int $boQua = 0): array
    {
        $rows = $this->call(RoutineName::DS_TIN_TUC, [$gioiHan, $boQua]);
        $tong = $rows === [] ? 0 : (int) ($rows[0]['tong'] ?? count($rows));
        return ['items' => $rows, 'tong' => $tong];
    }

    /** @return array<string, mixed>|null */
    public function getNewsById(int $id): ?array
    {
        $rows = $this->call(RoutineName::CHI_TIET_TIN_TUC, [$id]);
        return $rows[0] ?? null;
    }

    /** Thêm/cập nhật một tin (dùng cho cào tin và admin). Trả về mã kết quả. */
    public function upsertTinTuc(
        string $nguon, string $maNgoai, string $tieuDe, string $tomTat,
        string $anhUrl, string $duongDan, ?string $ngayDang, string $tenDanhMuc, int $thuTu
    ): string {
        return $this->callMa(RoutineName::UPSERT_TIN_TUC, [
            $nguon, $maNgoai, $tieuDe, $tomTat, $anhUrl, $duongDan, $ngayDang, $tenDanhMuc, $thuTu,
        ]);
    }
}
