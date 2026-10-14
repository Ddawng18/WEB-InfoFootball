<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Đối tượng người dùng. KHÔNG có getter cho hash mật khẩu (đóng gói).
 * Hash chỉ được Service xử lý nội bộ, không rò ra View/JSON.
 */
final class User
{
    private int $id;
    private string $tenDangNhap;
    private string $email;
    private string $hoTen;
    private string $vaiTro;
    private string $trangThai;

    public function __construct(
        int $id, string $tenDangNhap, string $email, string $hoTen,
        string $vaiTro, string $trangThai
    ) {
        $this->id = $id;
        $this->tenDangNhap = $tenDangNhap;
        $this->email = $email;
        $this->hoTen = $hoTen;
        $this->vaiTro = $vaiTro;
        $this->trangThai = $trangThai;
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self(
            (int) ($r['tai_khoan_id'] ?? 0),
            (string) ($r['ten_dang_nhap'] ?? ''),
            (string) ($r['email'] ?? ''),
            (string) ($r['ho_ten'] ?? ''),
            (string) ($r['vai_tro_ma'] ?? 'USER'),
            (string) ($r['trang_thai'] ?? 'HOAT_DONG')
        );
    }

    public function getId(): int { return $this->id; }
    public function getTenDangNhap(): string { return $this->tenDangNhap; }
    public function getEmail(): string { return $this->email; }
    public function getHoTen(): string { return $this->hoTen; }
    public function getVaiTro(): string { return $this->vaiTro; }
    public function getTrangThai(): string { return $this->trangThai; }
    public function isAdmin(): bool { return $this->vaiTro === 'ADMIN'; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tai_khoan_id' => $this->id,
            'ten_dang_nhap' => $this->tenDangNhap,
            'email' => $this->email,
            'ho_ten' => $this->hoTen,
            'vai_tro' => $this->vaiTro,
            'trang_thai' => $this->trangThai,
        ];
    }

    public function __toString(): string
    {
        return "User({$this->tenDangNhap}, {$this->vaiTro})";
    }
}
