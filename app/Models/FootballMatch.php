<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Đối tượng trận đấu (dữ liệu đã lưu, đọc từ routine).
 * Lưu ý: PHP 8 dành riêng từ khóa `match` nên lớp đặt tên FootballMatch.
 */
final class FootballMatch
{
    private int $id;
    private string $maApi;
    private int $giaiDauId;
    private string $giaiTen;
    private int $doiNhaId;
    private int $doiKhachId;
    private string $doiNhaTen;
    private string $doiKhachTen;
    private string $doiNhaLogo;
    private string $doiKhachLogo;
    private string $thoiGianBatDau; // UTC, 'Y-m-d H:i:s'
    private string $trangThai;
    private int $phut;
    private int $tiSoNha;
    private int $tiSoKhach;
    private string $capNhatLuc;

    public function __construct(
        int $id, string $maApi, int $giaiDauId, string $giaiTen,
        int $doiNhaId, int $doiKhachId, string $doiNhaTen, string $doiKhachTen,
        string $thoiGianBatDau, string $trangThai, int $phut,
        int $tiSoNha, int $tiSoKhach, string $capNhatLuc,
        string $doiNhaLogo = '', string $doiKhachLogo = ''
    ) {
        $this->id = $id;
        $this->maApi = $maApi;
        $this->giaiDauId = $giaiDauId;
        $this->giaiTen = $giaiTen;
        $this->doiNhaId = $doiNhaId;
        $this->doiKhachId = $doiKhachId;
        $this->doiNhaTen = $doiNhaTen;
        $this->doiKhachTen = $doiKhachTen;
        $this->doiNhaLogo = $doiNhaLogo;
        $this->doiKhachLogo = $doiKhachLogo;
        $this->thoiGianBatDau = $thoiGianBatDau;
        $this->trangThai = $trangThai;
        $this->phut = $phut;
        $this->tiSoNha = $tiSoNha;
        $this->tiSoKhach = $tiSoKhach;
        $this->capNhatLuc = $capNhatLuc;
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self(
            (int) ($r['tran_dau_id'] ?? 0),
            (string) ($r['ma_api'] ?? ''),
            (int) ($r['giai_dau_id'] ?? 0),
            (string) ($r['giai_ten'] ?? ''),
            (int) ($r['doi_nha_id'] ?? 0),
            (int) ($r['doi_khach_id'] ?? 0),
            (string) ($r['doi_nha_ten'] ?? ''),
            (string) ($r['doi_khach_ten'] ?? ''),
            (string) ($r['thoi_gian_bat_dau'] ?? ''),
            (string) ($r['trang_thai'] ?? 'SAP_DIEN_RA'),
            (int) ($r['phut'] ?? 0),
            (int) ($r['ti_so_nha'] ?? 0),
            (int) ($r['ti_so_khach'] ?? 0),
            (string) ($r['cap_nhat_luc'] ?? ''),
            (string) ($r['doi_nha_logo'] ?? ''),
            (string) ($r['doi_khach_logo'] ?? '')
        );
    }

    public function getId(): int { return $this->id; }
    public function getMaApi(): string { return $this->maApi; }
    public function getGiaiDauId(): int { return $this->giaiDauId; }
    public function getGiaiTen(): string { return $this->giaiTen; }
    public function getDoiNhaId(): int { return $this->doiNhaId; }
    public function getDoiKhachId(): int { return $this->doiKhachId; }
    public function getDoiNhaTen(): string { return $this->doiNhaTen; }
    public function getDoiKhachTen(): string { return $this->doiKhachTen; }
    public function getDoiNhaLogo(): string { return $this->doiNhaLogo; }
    public function getDoiKhachLogo(): string { return $this->doiKhachLogo; }
    public function getThoiGianBatDau(): string { return $this->thoiGianBatDau; }
    public function getTrangThai(): string { return $this->trangThai; }
    public function getPhut(): int { return $this->phut; }
    public function getTiSoNha(): int { return $this->tiSoNha; }
    public function getTiSoKhach(): int { return $this->tiSoKhach; }
    public function getCapNhatLuc(): string { return $this->capNhatLuc; }

    public function isDangDienRa(): bool
    {
        return in_array($this->trangThai, ['DANG_DA', 'NGHI_GIUA_HIEP'], true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tran_dau_id' => $this->id,
            'ma_api' => $this->maApi,
            'giai_dau_id' => $this->giaiDauId,
            'giai_ten' => $this->giaiTen,
            'doi_nha_id' => $this->doiNhaId,
            'doi_khach_id' => $this->doiKhachId,
            'doi_nha_ten' => $this->doiNhaTen,
            'doi_khach_ten' => $this->doiKhachTen,
            'doi_nha_logo' => $this->doiNhaLogo,
            'doi_khach_logo' => $this->doiKhachLogo,
            'thoi_gian_bat_dau' => $this->thoiGianBatDau,
            'trang_thai' => $this->trangThai,
            'phut' => $this->phut,
            'ti_so_nha' => $this->tiSoNha,
            'ti_so_khach' => $this->tiSoKhach,
            'cap_nhat_luc' => $this->capNhatLuc,
        ];
    }

    public function __toString(): string
    {
        return "FootballMatch(#{$this->id}, {$this->doiNhaTen} {$this->tiSoNha}-{$this->tiSoKhach} {$this->doiKhachTen}, {$this->trangThai})";
    }
}
