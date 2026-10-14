<?php
declare(strict_types=1);

namespace App\Models;

/** Đối tượng đội bóng. */
final class Team
{
    private int $id;
    private string $maApi;
    private string $ten;
    private string $quocGiaTen;
    private string $logoUrl;
    private int $soTran;
    private int $thang;
    private int $hoa;
    private int $thua;

    public function __construct(
        int $id, string $maApi, string $ten, string $quocGiaTen, string $logoUrl,
        int $soTran = 0, int $thang = 0, int $hoa = 0, int $thua = 0
    ) {
        $this->id = $id;
        $this->maApi = $maApi;
        $this->ten = $ten;
        $this->quocGiaTen = $quocGiaTen;
        $this->logoUrl = $logoUrl;
        $this->soTran = $soTran;
        $this->thang = $thang;
        $this->hoa = $hoa;
        $this->thua = $thua;
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self(
            (int) ($r['doi_bong_id'] ?? 0),
            (string) ($r['ma_api'] ?? ''),
            (string) ($r['ten'] ?? ''),
            (string) ($r['quoc_gia_ten'] ?? 'Chưa phân loại'),
            (string) ($r['logo_url'] ?? ''),
            (int) ($r['so_tran'] ?? 0),
            (int) ($r['thang'] ?? 0),
            (int) ($r['hoa'] ?? 0),
            (int) ($r['thua'] ?? 0)
        );
    }

    public function getId(): int { return $this->id; }
    public function getMaApi(): string { return $this->maApi; }
    public function getTen(): string { return $this->ten; }
    public function getQuocGiaTen(): string { return $this->quocGiaTen; }
    public function getLogoUrl(): string { return $this->logoUrl; }
    public function getSoTran(): int { return $this->soTran; }
    public function getThang(): int { return $this->thang; }
    public function getHoa(): int { return $this->hoa; }
    public function getThua(): int { return $this->thua; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'doi_bong_id' => $this->id,
            'ma_api' => $this->maApi,
            'ten' => $this->ten,
            'quoc_gia_ten' => $this->quocGiaTen,
            'logo_url' => $this->logoUrl,
            'so_tran' => $this->soTran,
            'thang' => $this->thang,
            'hoa' => $this->hoa,
            'thua' => $this->thua,
        ];
    }

    public function __toString(): string
    {
        return "Team({$this->ten})";
    }
}
