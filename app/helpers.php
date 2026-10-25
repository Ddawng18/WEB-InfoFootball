<?php
declare(strict_types=1);

use App\Core\Url;
use App\Core\View;

/** Escape HTML (chống XSS khi in dữ liệu). */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Tạo URL tới route nội bộ. */
function route(string $r, array $params = []): string
{
    return Url::to('index.php', ['r' => $r] + $params);
}

/** Tạo URL tới endpoint API. */
function api(string $path, array $params = []): string
{
    return Url::to('api/' . ltrim($path, '/'), $params);
}

/** URL tới tài nguyên tĩnh trong public/. */
function asset(string $path): string
{
    return Url::base() . '/public/' . ltrim($path, '/');
}

function news_image_url(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $path = parse_url($value, PHP_URL_PATH);
    $path = is_string($path) ? $path : $value;
    $needle = 'public/uploads/news/';
    $pos = strpos($path, $needle);
    if ($pos !== false) {
        $name = basename(substr($path, $pos + strlen($needle)));
        if (preg_match('/^news_[A-Za-z0-9_.-]+$/', $name) === 1) {
            return asset('uploads/news/' . $name);
        }
    }
    return \App\Core\SafeUrl::https($value);
}

/** Nhãn tiếng Việt cho trạng thái trận. */
function nhan_trang_thai(string $tt): string
{
    return match ($tt) {
        'CHO_CAP_NHAT' => 'Chờ cập nhật',
        'SAP_DIEN_RA' => 'Sắp diễn ra',
        'DANG_DA' => 'Đang đá',
        'NGHI_GIUA_HIEP' => 'Nghỉ giữa hiệp',
        'KET_THUC' => 'Kết thúc',
        'HOAN' => 'Hoãn',
        'HUY' => 'Hủy',
        default => $tt,
    };
}

/** Nhãn tiếng Việt cho loại sự kiện (DB dùng mã, API dùng chuỗi tiếng Anh). */
function nhan_su_kien(string $loai): string
{
    $l = mb_strtolower(trim($loai), 'UTF-8');
    if ($l === 'phan_luoi' || str_contains($l, 'own goal')) {
        return 'Phản lưới';
    }
    if ($l === 'ban_thang' || str_contains($l, 'goal')) {
        return 'Bàn thắng';
    }
    if ($l === 'the_vang' || str_contains($l, 'yellow')) {
        return 'Thẻ vàng';
    }
    if ($l === 'the_do' || str_contains($l, 'red card')) {
        return 'Thẻ đỏ';
    }
    if (str_contains($l, 'subst')) {
        return 'Thay người';
    }
    if (str_contains($l, 'penalty')) {
        return 'Penalty';
    }
    return $loai;
}

/** Lớp chip trạng thái trận (ff-chip). */
function badge_trang_thai(string $tt): string
{
    return match ($tt) {
        'DANG_DA' => 'ff-chip ff-chip--live',
        'NGHI_GIUA_HIEP' => 'ff-chip ff-chip--warn',
        'CHO_CAP_NHAT' => 'ff-chip ff-chip--warn',
        'SAP_DIEN_RA' => 'ff-chip ff-chip--soon',
        'KET_THUC' => 'ff-chip ff-chip--ft',
        default => 'ff-chip ff-chip--muted',
    };
}

/** Màu accent (CSS) cho thẻ trận theo trạng thái. */
function accent_trang_thai(string $tt): string
{
    return match ($tt) {
        'DANG_DA', 'NGHI_GIUA_HIEP' => 'var(--ff-red)',
        'CHO_CAP_NHAT' => 'var(--ff-amber, #b45309)',
        'SAP_DIEN_RA' => 'var(--ff-blue)',
        'KET_THUC' => 'var(--ff-green)',
        default => 'var(--ff-ink-3)',
    };
}

/** Chữ cái đầu của tên (dùng cho crest tròn). */
function chu_cai_dau(string $ten): string
{
    $ten = trim($ten);
    return $ten === '' ? '?' : mb_strtoupper(mb_substr($ten, 0, 1, 'UTF-8'), 'UTF-8');
}

/**
 * Crest đội: hiển thị logo thật nếu có, tự động fallback chữ cái đầu khi thiếu/lỗi tải.
 * @param string $bienThe 'ink' | 'muted'
 * @param string $co 'sm' để dùng trong bảng
 */
function crest_html(string $ten, string $logoUrl = '', string $bienThe = 'ink', string $co = '', string $href = ''): string
{
    $cls = 'ff-crest ff-crest--' . ($bienThe === 'muted' ? 'muted' : 'ink')
        . ($co !== '' ? ' ff-crest--' . $co : '');
    $inner = '<span class="ff-crest__ini">' . h(chu_cai_dau($ten)) . '</span>';
    if ($logoUrl !== '') {
        $inner .= '<img class="ff-crest__img" src="' . h($logoUrl) . '" alt="" loading="lazy"'
            . ' referrerpolicy="no-referrer" onerror="this.remove()">';
    }
    if ($href !== '') {
        return '<a class="' . $cls . '" href="' . h($href) . '" aria-label="' . h($ten) . '">' . $inner . '</a>';
    }
    return '<span class="' . $cls . '">' . $inner . '</span>';
}

/** Thời điểm đồng bộ gần nhất (đã đổi sang giờ hiển thị), dùng cho footer. */function dong_bo_gan_nhat(): string
{
    try {
        $nk = (new App\Repositories\SyncRepository())->nhatKy(1);
        $t = (string) ($nk[0]['ket_thuc'] ?? $nk[0]['bat_dau'] ?? '');
        return $t !== '' ? App\Core\Time::toDisplay($t) : 'chưa có';
    } catch (\Throwable $e) {
        return 'chưa có';
    }
}

/**
 * Dựng thanh phân trang Bootstrap.
 * @param array<string, scalar|null> $params các tham số truy vấn khác (giữ nguyên khi đổi trang)
 */
function render_pager(
    int $tong,
    int $moiTrang,
    int $trang,
    array $params,
    string $routeName,
    string $param = 'trang',
    int $cuaSo = 3
): string {
    $soTrang = max(1, (int) ceil($tong / max(1, $moiTrang)));
    if ($soTrang <= 1) {
        return '';
    }
    $trang = min(max(1, $trang), $soTrang);
    $link = static fn (int $i): string => h(route($routeName, $params + [$param => $i]));

    $out = '<nav class="mt-2" aria-label="Phân trang"><ul class="pagination pagination-sm justify-content-center flex-wrap mb-0">';
    $out .= '<li class="page-item ' . ($trang <= 1 ? 'disabled' : '') . '"><a class="page-link" href="'
        . $link(max(1, $trang - 1)) . '">&laquo;</a></li>';

    $start = max(1, $trang - $cuaSo);
    $end = min($soTrang, $trang + $cuaSo);
    if ($start > 1) {
        $out .= '<li class="page-item"><a class="page-link" href="' . $link(1) . '">1</a></li>';
        if ($start > 2) {
            $out .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $out .= '<li class="page-item ' . ($i === $trang ? 'active' : '') . '"><a class="page-link" href="'
            . $link($i) . '">' . $i . '</a></li>';
    }
    if ($end < $soTrang) {
        if ($end < $soTrang - 1) {
            $out .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
        $out .= '<li class="page-item"><a class="page-link" href="' . $link($soTrang) . '">' . $soTrang . '</a></li>';
    }

    $out .= '<li class="page-item ' . ($trang >= $soTrang ? 'disabled' : '') . '"><a class="page-link" href="'
        . $link(min($soTrang, $trang + 1)) . '">&raquo;</a></li>';
    $out .= '</ul></nav>';
    return $out;
}

