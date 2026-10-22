<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\RoutineName;

/** Truy cập lịch cá nhân, nhắc trận và thông báo trong web. */
final class CalendarRepository extends BaseRepository
{
    /** @return array<string, mixed> */
    public function dat(int $taiKhoanId, int $tranDauId, bool $trongLich, bool $nhac): array
    {
        $rows = $this->call(RoutineName::DAT_LICH_CA_NHAN, [
            $taiKhoanId, $tranDauId, $trongLich ? 1 : 0, $nhac ? 1 : 0,
        ]);
        return $rows[0] ?? ['ma' => 'LOI', 'trong_lich' => 0, 'nhac' => 0];
    }

    /** @return array{trong_lich: bool, nhac: bool, da_nhac: bool} */
    public function kiemTra(int $taiKhoanId, int $tranDauId): array
    {
        $row = $this->call(RoutineName::KIEM_TRA_LICH_CA_NHAN, [$taiKhoanId, $tranDauId])[0] ?? [];
        return [
            'trong_lich' => (int) ($row['trong_lich'] ?? 0) === 1,
            'nhac' => (int) ($row['nhac'] ?? 0) === 1,
            'da_nhac' => (int) ($row['da_nhac'] ?? 0) === 1,
        ];
    }

    /** @return array{items: array<int, array<string, mixed>>, tong: int} */
    public function danhSach(int $taiKhoanId, string $loc, int $gioiHan, int $boQua): array
    {
        $rows = $this->call(RoutineName::DS_LICH_CA_NHAN, [$taiKhoanId, $loc, $gioiHan, $boQua]);
        $tong = $rows === [] ? 0 : (int) ($rows[0]['tong'] ?? count($rows));
        return ['items' => $rows, 'tong' => $tong];
    }

    /**
     * @param array<int, int|string> $tranDauIds
     * @return array<int, array{trong_lich: bool, nhac: bool}>
     */
    public function trangThaiNhieu(int $taiKhoanId, array $tranDauIds): array
    {
        $ids = [];
        foreach ($tranDauIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }
        $rows = $this->call(RoutineName::DS_LICH_THEO_NHIEU_TRAN, [
            $taiKhoanId, implode(',', array_values($ids)),
        ]);
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['tran_dau_id'] ?? 0);
            if ($id > 0) {
                $out[$id] = [
                    'trong_lich' => (int) ($row['trong_lich'] ?? 0) === 1,
                    'nhac' => (int) ($row['nhac'] ?? 0) === 1,
                ];
            }
        }
        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    public function doiDaThem(int $taiKhoanId, int $gioiHan = 24): array
    {
        return $this->call(RoutineName::DS_DOI_DA_THEM, [$taiKhoanId, $gioiHan]);
    }

    public function taoNhacDenHan(string $nowUtc): void
    {
        $this->call(RoutineName::TAO_THONG_BAO_NHAC, [$nowUtc]);
    }

    /** @return array{items: array<int, array<string, mixed>>, tong: int} */
    public function thongBao(int $taiKhoanId, int $gioiHan = 30, int $boQua = 0): array
    {
        $rows = $this->call(RoutineName::DS_THONG_BAO, [$taiKhoanId, $gioiHan, $boQua]);
        $tong = $rows === [] ? 0 : (int) ($rows[0]['tong'] ?? count($rows));
        return ['items' => $rows, 'tong' => $tong];
    }

    public function demChuaDoc(int $taiKhoanId): int
    {
        $row = $this->call(RoutineName::DEM_THONG_BAO_CHUA_DOC, [$taiKhoanId])[0] ?? [];
        return (int) ($row['chua_doc'] ?? 0);
    }

    public function danhDauDaDoc(int $taiKhoanId, ?int $thongBaoId = null): int
    {
        $row = $this->call(RoutineName::DANH_DAU_DA_DOC_THONG_BAO, [$taiKhoanId, $thongBaoId])[0] ?? [];
        return (int) ($row['so_dong'] ?? 0);
    }
}
