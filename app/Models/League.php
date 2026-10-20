<?php
declare(strict_types=1);

namespace App\Models;

/** Đối tượng giải đấu. */
final class League
{
    private int $id;
    private string $maApi;
    private string $ten;
    private string $muaGiai;
    private bool $dangTheoDoi;
    private string $quocGiaTen;
    private string $khuVuc;
    private int $quocGiaId;

    public function __construct(
        int $id, string $maApi, string $ten, string $muaGiai,
        bool $dangTheoDoi, string $quocGiaTen, string $khuVuc, int $quocGiaId = 0
    ) {
        $this->id = $id;
        $this->maApi = $maApi;
        $this->ten = $ten;
        $this->muaGiai = $muaGiai;
        $this->dangTheoDoi = $dangTheoDoi;
        $this->quocGiaTen = $quocGiaTen;
        $this->khuVuc = $khuVuc;
        $this->quocGiaId = $quocGiaId;
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self(
            (int) ($r['giai_dau_id'] ?? 0),
            (string) ($r['ma_api'] ?? ''),
            (string) ($r['ten'] ?? ''),
            (string) ($r['mua_giai'] ?? ''),
            (bool) ($r['dang_theo_doi'] ?? false),
            (string) ($r['quoc_gia_ten'] ?? 'Chưa phân loại'),
            (string) ($r['khu_vuc'] ?? 'Chưa phân loại'),
            (int) ($r['quoc_gia_id'] ?? 0)
        );
    }

    public function getId(): int { return $this->id; }
    public function getMaApi(): string { return $this->maApi; }
    public function getTen(): string { return $this->ten; }
    public function getMuaGiai(): string { return $this->muaGiai; }
    public function isDangTheoDoi(): bool { return $this->dangTheoDoi; }
    public function getQuocGiaTen(): string { return $this->quocGiaTen; }
    public function getKhuVuc(): string { return $this->khuVuc; }
    public function getQuocGiaId(): int { return $this->quocGiaId; }

    /** @return array<string, mixed> */
}
