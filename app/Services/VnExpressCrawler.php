<?php
declare(strict_types=1);

namespace App\Services;

/**
 * TOI YEU VNEXPRESS
 * Cào tin bóng đá từ chuyên mục VnExpress (https://vnexpress.net/bong-da).
 * Chỉ lấy tiêu đề, sapo, ảnh thu nhỏ và đường dẫn gốc để dẫn nguồn (không sao chép toàn bài).
 */
final class VnExpressCrawler
{
    public const NGUON = 'VNEXPRESS';

    private string $url;

    public function __construct(?string $url = null)
    {
        $this->url = $url ?? 'https://vnexpress.net/bong-da';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetch(int $gioiHan = 30): array
    {
        $html = $this->tai();
        if ($html === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xp = new \DOMXPath($doc);

        $items = [];
        $thuTu = 0;
        foreach ($xp->query("//article[contains(@class,'item-news')]") as $it) {
            if (count($items) >= $gioiHan) {
                break;
            }
            $a = $xp->query(".//h3[contains(@class,'title-news')]/a", $it)->item(0)
                ?? $xp->query(".//h2[contains(@class,'title-news')]/a", $it)->item(0);
            if (!$a instanceof \DOMElement) {
                continue;
            }
            $link = trim($a->getAttribute('href'));
            if (!preg_match('#^https?://vnexpress\.net/#', $link)) {
                continue;
            }
            $ma = $this->layMa($link);
            if ($ma === '') {
                continue;
            }
            $tieuDe = trim($a->getAttribute('title'));
            if ($tieuDe === '') {
                $tieuDe = trim($a->textContent);
            }
            $img = $xp->query('.//img', $it)->item(0);
            $anh = $img instanceof \DOMElement
                ? ($img->getAttribute('data-src') ?: $img->getAttribute('src'))
                : '';
            $p = $xp->query(".//p[contains(@class,'description')]", $it)->item(0);
            $sapo = $p instanceof \DOMElement ? trim($p->textContent) : '';

            $items[] = [
                'ma' => $ma,
                'tieu_de' => mb_substr($tieuDe, 0, 250, 'UTF-8'),
                'tom_tat' => mb_substr($sapo, 0, 580, 'UTF-8'),
                'anh_url' => mb_substr($anh, 0, 490, 'UTF-8'),
                'duong_dan' => mb_substr($link, 0, 490, 'UTF-8'),
                'ngay_dang' => null,
                'danh_muc' => 'Bóng đá',
                'thu_tu' => $thuTu++,
            ];
        }

        return $items;
    }

    /** Lấy mã bài viết (số ở cuối URL) làm khóa chống trùng. */
    private function layMa(string $link): string
    {
        return preg_match('/-(\d+)\.html$/', $link, $m) ? $m[1] : '';
    }

    private function tai(): string
    {
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FastFootBalInfo/1.0; do-an-hoc-tap)',
        ]);
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $http >= 400) {
            error_log('VnExpress crawl lỗi: HTTP ' . $http . ' ' . $err);
            return '';
        }
        return (string) $body;
    }
}
