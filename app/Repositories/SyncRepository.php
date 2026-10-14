<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\RoutineName;

/** Truy cập dữ liệu liên quan đồng bộ API (F6, F8). */
final class SyncRepository extends BaseRepository
{
    /** @return array{ma:string, nhat_ky_id:?int} */
    public function batDau(string $loai, int $ttl): array
    {
        $rows = $this->call(RoutineName::BAT_DAU_DONG_BO, [$loai, $ttl]);
        $row = $rows[0] ?? [];
        return [
            'ma' => (string) ($row['ma'] ?? 'LOI_HE_THONG'),
            'nhat_ky_id' => isset($row['nhat_ky_id']) ? (int) $row['nhat_ky_id'] : null,
        ];
    }

    public function ketThuc(int $id, string $trangThai, int $soBanGhi, int $soBoQua, string $thongBao): string
    {
        return $this->callMa(RoutineName::KET_THUC_DONG_BO, [$id, $trangThai, $soBanGhi, $soBoQua, $thongBao]);
    }

    public function upsertQuocGia(string $maApi, string $ten): string
    {
        return $this->callMa(RoutineName::UPSERT_QUOC_GIA, [$maApi, $ten]);
    }

    public function upsertGiaiDau(string $nguon, string $maApi, string $ten, ?string $maQuocGia, string $muaGiai): string
    {
        return $this->callMa(RoutineName::UPSERT_GIAI_DAU, [$nguon, $maApi, $ten, $maQuocGia, $muaGiai]);
    }

    public function upsertDoiBong(string $nguon, string $maApi, string $ten, ?string $maQuocGia, string $logoUrl = ''): string
    {
        return $this->callMa(RoutineName::UPSERT_DOI_BONG, [$nguon, $maApi, $ten, $maQuocGia, $logoUrl]);
    }

    /** @return array<string, mixed> dòng kết quả có ma, tran_dau_id, hanh_dong */
    public function upsertTranDau(
        string $nguon, string $maApi, string $maGiai, string $maNha, string $maKhach,
        string $thoiGianUtc, string $trangThai, int $phut, int $tiSoNha, int $tiSoKhach
    ): array {
        $rows = $this->call(RoutineName::UPSERT_TRAN_DAU, [
            $nguon, $maApi, $maGiai, $maNha, $maKhach,
            $thoiGianUtc, $trangThai, $phut, $tiSoNha, $tiSoKhach,
        ]);
        return $rows[0] ?? ['ma' => 'LOI_HE_THONG', 'hanh_dong' => 'BO_QUA'];
    }

    /** @return array<string, mixed> */
    public function thongKe(): array
    {
        $rows = $this->call(RoutineName::THONG_KE_TONG_QUAN);
        return $rows[0] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function nhatKy(int $gioiHan = 50): array
    {
        return $this->call(RoutineName::DS_NHAT_KY_DONG_BO, [$gioiHan]);
    }

    public function cauHinhGiaiDau(int $id, bool $theoDoi, ?int $quocGiaId, ?string $khuVuc): string
    {
        return $this->callMa(RoutineName::ADMIN_CAU_HINH_GIAI_DAU, [$id, $theoDoi ? 1 : 0, $quocGiaId, $khuVuc]);
    }
}
