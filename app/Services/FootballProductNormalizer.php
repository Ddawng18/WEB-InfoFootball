<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\SafeUrl;

/** Chuẩn hóa dữ liệu sản phẩm thô (HTML/JSON) thành bản ghi an toàn cho CSDL. */
final class FootballProductNormalizer
{
    private FootballProductClassifier $classifier;

    public function __construct(?FootballProductClassifier $classifier = null)
    {
        $this->classifier = $classifier ?? new FootballProductClassifier();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public function normalize(array $row, string $source, string $fallbackDomain): ?array
    {
        $title = $this->cleanTitle($this->text($row['ten'] ?? $row['name'] ?? $row['title'] ?? ''));
        if ($title === '') {
            return null;
        }
        $description = $this->cleanDescription($this->text(
            $row['mo_ta'] ?? $row['desc'] ?? $row['description'] ?? ''
        ));
        $categoryText = $this->text($row['danh_muc'] ?? $row['danh_muc_goc'] ?? $row['cate'] ?? $row['category'] ?? '');

        if (!$this->classifier->isFootballProduct($title, $description . ' ' . $categoryText)) {
            return null;
        }

        $image = $this->safeImage($this->text($row['anh_url'] ?? $row['image'] ?? ''));
        if ($image === null) {
            return null;
        }

        $link = $this->safeLink($this->text(
            $row['lien_ket_goc'] ?? $row['url'] ?? $row['link'] ?? $row['lien_ket_mua'] ?? ''
        ));
        $buyLink = $this->safeLink($this->text(
            $row['lien_ket_mua'] ?? $row['aff_link'] ?? $row['buy_link'] ?? $row['url'] ?? ''
        ));
        $buyLink = $buyLink ?? $link;
        if ($link === null && $buyLink === null) {
            return null;
        }

        $current = self::parsePrice($row['gia_hien_tai'] ?? $row['price'] ?? $row['sale_price'] ?? null);
        if ($current === null) {
            return null;
        }
        $original = self::parsePrice($row['gia_goc'] ?? $row['original_price'] ?? $row['price_original'] ?? null);
        $explicitPercent = self::parseDiscount(
            $row['phan_tram_giam'] ?? $row['discount_percent'] ?? $row['discount'] ?? null
        );
        if (($original === null || $original <= $current)
            && $explicitPercent !== null && $explicitPercent > 0 && $explicitPercent < 100) {
            $original = (int) round($current / (1 - $explicitPercent / 100));
        }
        if ($original === null || $original < $current) {
            $original = $current;
        }
        $discountAmount = max(0, $original - $current);
        $percent = $original > 0 ? (int) round($discountAmount * 100 / $original) : 0;
        if ($explicitPercent !== null && $explicitPercent > 0) {
            $percent = $explicitPercent;
        }
        $percent = min(99, max(0, $percent));
        if ($percent === 0) {
            $discountAmount = 0;
        }

        $domain = strtolower(trim($this->text($row['ten_mien'] ?? $row['domain'] ?? '')));
        if (preg_match('/^[a-z0-9.-]{1,100}$/', $domain) !== 1) {
            $domain = strtolower($fallbackDomain);
        }
        if (preg_match('/^[a-z0-9.-]{1,100}$/', $domain) !== 1) {
            $domain = 'khac';
        }

        $source = strtoupper(trim($source));
        if (preg_match('/^[A-Z0-9_]{2,30}$/', $source) !== 1) {
            $source = 'WEB';
        }

        $externalId = trim($this->text($row['ma_ngoai'] ?? $row['product_id'] ?? ''));
        if ($externalId === '') {
            $externalId = substr(sha1((string) ($link ?? $buyLink ?? $title)), 0, 40);
        }

        $extraImages = $this->extraImages($row['anh_phu'] ?? $row['images'] ?? null);
        $categorySlug = $this->classifier->classifyProduct($title, $description, $categoryText);
        $stock = $this->inStock($row['con_hang'] ?? $row['in_stock'] ?? 1);

        return [
            'nguon' => $source,
            'ma_ngoai' => mb_substr($externalId, 0, 100, 'UTF-8'),
            'ten' => mb_substr($title, 0, 255, 'UTF-8'),
            'mo_ta' => mb_substr($description, 0, 2000, 'UTF-8'),
            'anh_url' => $image,
            'anh_phu' => $extraImages === [] ? '' : (string) json_encode($extraImages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'gia_goc' => $original,
            'gia_hien_tai' => $current,
            'phan_tram_giam' => $percent,
            'danh_muc_slug' => $categorySlug,
            'thuong_hieu' => mb_substr($this->cleanTitle($this->text($row['thuong_hieu'] ?? $row['brand'] ?? '')), 0, 100, 'UTF-8'),
            'cua_hang' => mb_substr($this->cleanTitle($this->text(
                $row['cua_hang'] ?? $row['shop_name'] ?? $row['seller_name'] ?? ''
            )), 0, 150, 'UTF-8'),
            'ten_mien' => $domain,
            'lien_ket_mua' => $buyLink ?? $link,
            'lien_ket_goc' => $link,
            'con_hang' => $stock,
            'ngay_nguon' => self::parseDate($row['ngay_nguon'] ?? $row['update_time'] ?? null),
        ];
    }

    public function isFootballProduct(string $title, string $context = ''): bool
    {
        return $this->classifier->isFootballProduct($title, $context);
    }

    public static function parsePrice($value): ?int
    {
        if (is_int($value) || is_float($value)) {
            $number = (int) round((float) $value);
            return $number >= 0 && $number <= 1000000000 ? $number : null;
        }
        if (!is_string($value)) {
            return null;
        }
        $text = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($text === '') {
            return null;
        }
        $lower = mb_strtolower($text, 'UTF-8');
        $multiplier = 1;
        if (preg_match('/(triệu|trieu)\b/u', $lower) === 1) {
            $multiplier = 1000000;
        } elseif (preg_match('/(nghìn|nghin|ngàn|ngan)\b/u', $lower) === 1
            || preg_match('/\d\s*k\b/u', $lower) === 1) {
            $multiplier = 1000;
        }
        if (preg_match('/\d[\d.,\s]*\d|\d/u', $text, $match) !== 1) {
            return null;
        }
        $raw = preg_replace('/\s+/u', '', $match[0]);
        if ($raw === null || $raw === '') {
            return null;
        }
        $hasDecimal = preg_match('/[.,]\d{1,2}$/u', $raw) === 1;
        $number = $hasDecimal
            ? (float) str_replace(',', '.', $raw)
            : (float) str_replace(['.', ','], '', $raw);
        $number *= $multiplier;
        $result = (int) round($number);
        if ($result <= 0 || $result > 1000000000) {
            return null;
        }
        return $result;
    }

    public static function parseDiscount($value): ?int
    {
        if (is_int($value) || is_float($value)) {
            $percent = (int) round((float) $value);
            return $percent >= 0 && $percent <= 99 ? $percent : null;
        }
        if (!is_string($value)) {
            return null;
        }
        if (preg_match('/(\d{1,2})\s*%/u', $value, $match) === 1) {
            $percent = (int) $match[1];
            return $percent >= 0 && $percent <= 99 ? $percent : null;
        }
        return null;
    }

    public static function parseDate($value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        foreach (['Y-m-d H:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s\Z', 'd-m-Y\TH:i:s'] as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $value, new \DateTimeZone('UTC'));
            $errors = \DateTime::getLastErrors();
            if ($date !== false && (!is_array($errors) || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                $date->setTimezone(new \DateTimeZone('UTC'));
                return $date->format('Y-m-d H:i:s');
            }
        }
        try {
            $date = new \DateTime($value, new \DateTimeZone('UTC'));
            $date->setTimezone(new \DateTimeZone('UTC'));
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function safeImage(string $url): ?string
    {
        return SafeUrl::https($url);
    }

    private function safeLink(string $url): ?string
    {
        return SafeUrl::https($url);
    }

    /** @param mixed $value @return array<int, string> */
    private function extraImages($value): array
    {
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }
        if (!is_array($value)) {
            return [];
        }
        $images = [];
        foreach ($value as $image) {
            if (!is_scalar($image)) {
                continue;
            }
            $safe = $this->safeImage((string) $image);
            if ($safe !== null && !in_array($safe, $images, true)) {
                $images[] = $safe;
            }
            if (count($images) >= 5) {
                break;
            }
        }
        return $images;
    }

    private function inStock($value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_numeric($value)) {
            return (int) $value === 0 ? 0 : 1;
        }
        if (is_string($value)) {
            $text = mb_strtolower(trim($value), 'UTF-8');
            if (in_array($text, ['0', 'false', 'no', 'out of stock', 'hết hàng', 'het hang', 'tạm hết hàng'], true)) {
                return 0;
            }
        }
        return 1;
    }

    private function cleanTitle(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function cleanDescription(string $text): string
    {
        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $text));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);
        return trim($text);
    }

    private function text($value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
