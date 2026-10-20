<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\RoutineName;

/** Truy cập dữ liệu tin tức (S4). */
final class NewsRepository extends BaseRepository
{
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
        string $anhUrl, string $duongDan, string $noiDung,
        ?string $ngayDang, string $tenDanhMuc, int $thuTu
    ): string {
        return $this->callMa(RoutineName::UPSERT_TIN_TUC, [
            $nguon, $maNgoai, $tieuDe, $tomTat, $anhUrl, $duongDan, $noiDung,
            $ngayDang, $tenDanhMuc, $thuTu,
        ]);
    }

    public function xoaTinTucAdmin(int $id): string
    {
        return $this->callMa(RoutineName::XOA_TIN_TUC_ADMIN, [$id]);
    }
}
