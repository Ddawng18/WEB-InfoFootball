<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\SafeUrl;

/**
 * Lấy sản phẩm bóng đá thật từ JSON API của Tiki.
 * - Duyệt danh mục bóng đá + từ khóa, tự lọc sản phẩm liên quan.
 * - API chi tiết bổ sung mô tả, ảnh thật và tên shop.
 * Mỗi sản phẩm giữ link sản phẩm cụ thể, ảnh sản phẩm và seller_name.
 */
final class TikiFootballProductProvider implements FootballProductProvider
{
    private string $sourcesFile;
    private string $detailApiUrl;
    private int $timeout;
    private int $maxPages;
    private int $detailLimit;
    private FootballProductNormalizer $normalizer;
    /** @var array<int, string> */
    private array $errors = [];
    private bool $blocked = false;

    public function __construct(
        ?string $sourcesFile = null,
        ?int $detailLimit = null,
        ?FootballProductNormalizer $normalizer = null
    ) {
        $configured = Config::string('san_pham', 'sources_file', 'config/tiki_source.json');
        $this->sourcesFile = $sourcesFile ?? (is_file($configured)
            ? $configured
            : APP_ROOT . '/' . ltrim($configured, '/\\'));
        $this->detailApiUrl = Config::string(
            'san_pham',
            'tiki_api_url',
            'https://api.tiki.vn/v2/products/{id}?platform=web&spid={spid}'
        );
        $this->timeout = min(30, max(3, Config::int('san_pham', 'timeout', 10)));
        $this->maxPages = min(12, max(1, Config::int('san_pham', 'max_trang', 10)));
        $this->detailLimit = $detailLimit ?? min(30, max(0, Config::int('san_pham', 'chi_tiet_toi_da', 30)));
        $this->normalizer = $normalizer ?? new FootballProductNormalizer();
    }

    public function name(): string
    {
        return 'TIKI';
    }

    /** @return array<int, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function fetch(int $limit, float $deadline): array
    {
        $limit = max(1, $limit);
        $this->errors = [];
        $this->blocked = false;
        $source = $this->tikiSource();
        $queries = $source['queries'];
        $categories = $source['categories'];
        $pages = min($this->maxPages, max(1, (int) ($source['max_pages'] ?? $this->maxPages)));
        $pageLimit = 40;

        $state = ['rows' => [], 'seen' => [], 'total' => 0];
        $stop = false;

        // Ưu tiên duyệt danh mục để lấy toàn bộ sản phẩm thuộc các nhánh bóng đá.
        if ($categories !== [] && $source['api_category_url'] !== '') {
            foreach ($categories as $categoryId) {
                if ($stop) {
                    break;
                }
                $catCount = 0;
                for ($page = 1; $page <= $pages; $page++) {
                    if ($stop || $state['total'] >= $limit || microtime(true) >= $deadline) {
                        $stop = true;
                        break;
                    }
                    $url = str_replace(
                        ['{category}', '{page}', '{limit}'],
                        [(string) $categoryId, (string) $page, (string) $pageLimit],
                        $source['api_category_url']
                    );
                    if (!$this->isTikiUrl($url)) {
                        $this->errors[] = 'URL API danh mục Tiki không hợp lệ.';
                        break;
                    }
                    try {
                        $json = $this->httpGet($url, $deadline, 'application/json');
                    } catch (\Throwable $e) {
                        $this->errors[] = 'Danh mục Tiki #' . $categoryId . ': '
                            . mb_substr($e->getMessage(), 0, 150, 'UTF-8');
                        if ($this->blocked) {
                            $stop = true;
                        }
                        break;
                    }
                    $products = $this->parseSearch($json);
                    if ($products === []) {
                        if ($this->blocked) {
                            $stop = true;
                        }
                        break;
                    }
                    $stop = $this->consumeProducts(
                        $products, $state, $catCount, $limit, $limit, $deadline
                    );
                    if ($stop) {
                        break;
                    }
                }
            }
        }

        // Bổ sung bằng tìm kiếm từ khóa cho các sản phẩm nằm ngoài danh mục.
        $remaining = max(0, $limit - $state['total']);
        $perQuery = max(1, (int) ceil($remaining / max(1, count($queries))));
        foreach ($queries as $query) {
            if ($stop) {
                break;
            }
            $queryCount = 0;
            for ($page = 1; $page <= $pages; $page++) {
                if ($stop || $state['total'] >= $limit || microtime(true) >= $deadline) {
                    $stop = true;
                    break;
                }
                if ($queryCount >= $perQuery) {
                    break;
                }
                $url = str_replace(
                    ['{query}', '{page}', '{limit}'],
                    [rawurlencode($query), (string) $page, (string) $pageLimit],
                    $source['api_search_url']
                );
                if (!$this->isTikiUrl($url)) {
                    $this->errors[] = 'URL API tìm kiếm Tiki không hợp lệ.';
                    break;
                }
                try {
                    $json = $this->httpGet($url, $deadline, 'application/json');
                } catch (\Throwable $e) {
                    $this->errors[] = 'Tìm kiếm Tiki: ' . mb_substr($e->getMessage(), 0, 150, 'UTF-8');
                    if ($this->blocked) {
                        $stop = true;
                    }
                    break;
                }
                $products = $this->parseSearch($json);
                if ($products === []) {
                    if ($this->blocked) {
                        $stop = true;
                    }
                    break;
                }
                $stop = $this->consumeProducts(
                    $products, $state, $queryCount, $perQuery, $limit, $deadline
                );
                if ($stop) {
                    break;
                }
            }
        }

        $this->enrichDetails($state['rows'], $deadline);

        $plain = [];
        $detailed = [];
        foreach ($state['rows'] as $entry) {
            $item = $this->normalizer->normalize($entry['row'], 'TIKI', 'tiki.vn');
            if ($item === null) {
                continue;
            }
            if (!empty($entry['detail'])) {
                $detailed[] = $item;
            } else {
                $plain[] = $item;
            }
        }

        // Chèn sản phẩm có mô tả chi tiết sau cùng để chúng đứng đầu trang (sort mới nhất).
        return array_merge($plain, $detailed);
    }

    /**
     * @param array<int, array{product: array<string, mixed>, row: array<string, mixed>, detail: bool}> $rows
     */
    private function enrichDetails(array &$rows, float $deadline): void
    {
        if ($this->detailLimit <= 0 || $rows === []) {
            return;
        }
        $take = min($this->detailLimit, count($rows));
        $urls = [];
        for ($i = 0; $i < $take; $i++) {
            if (microtime(true) >= $deadline) {
                return;
            }
            $product = $rows[$i]['product'];
            $id = trim((string) ($product['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $spid = trim((string) ($product['seller_product_id'] ?? $id));
            $url = str_replace(['{id}', '{spid}'], [$id, $spid], $this->detailApiUrl);
            if ($this->isTikiUrl($url)) {
                $urls[$i] = $url;
            }
        }
        foreach (array_chunk($urls, 6, true) as $chunk) {
            if (microtime(true) >= $deadline) {
                return;
            }
            foreach ($this->httpGetMany($chunk, $deadline) as $index => $body) {
                $detail = $this->parseDetail($body, $rows[$index]['product']);
                if ($detail !== null) {
                    $rows[$index]['row'] = $this->mergeDetail($rows[$index]['row'], $detail);
                    $rows[$index]['detail'] = true;
                }
            }
            if ($this->blocked) {
                return;
            }
            usleep(250000);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @param array{rows: array<int, array{product: array<string, mixed>, row: array<string, mixed>, detail: bool}>, seen: array<string, bool>, total: int} $state
     */
    private function consumeProducts(
        array $products,
        array &$state,
        int &$feedCount,
        int $feedQuota,
        int $limit,
        float $deadline
    ): bool {
        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }
            if ($feedCount >= $feedQuota) {
                break;
            }
            if ($state['total'] >= $limit || microtime(true) >= $deadline) {
                return true;
            }

            $id = trim((string) ($product['id'] ?? ''));
            $name = trim((string) ($product['name'] ?? ''));
            if ($id === '' || $name === '' || isset($state['seen'][$id])) {
                continue;
            }
            $category = $this->categoryText($product);
            $brand = (string) ($product['brand_name'] ?? '');
            if (!$this->normalizer->isFootballProduct($name, $category . ' ' . $brand)) {
                continue;
            }

            $state['rows'][] = [
                'product' => $product,
                'row' => $this->searchRow($product),
                'detail' => false,
            ];
            $state['seen'][$id] = true;
            $feedCount++;
            $state['total']++;
        }
        return false;
    }

    /**
     * @return array{
     *   api_search_url: string,
     *   api_category_url: string,
     *   queries: array<int, string>,
     *   categories: array<int, int>,
     *   max_pages: int
     * }
     */
    private function tikiSource(): array
    {
        if (!is_file($this->sourcesFile)) {
            throw new \RuntimeException('Không tìm thấy cấu hình nguồn Tiki.');
        }
        $raw = @file_get_contents($this->sourcesFile);
        if ($raw === false) {
            throw new \RuntimeException('Không đọc được cấu hình nguồn Tiki.');
        }
        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Cấu hình nguồn Tiki không phải JSON hợp lệ.');
        }
        $sources = is_array($payload) ? ($payload['sources'] ?? []) : [];
        foreach ($sources as $source) {
            if (!is_array($source) || strtolower(trim((string) ($source['key'] ?? ''))) !== 'tiki') {
                continue;
            }
            if (isset($source['enabled']) && !$source['enabled']) {
                throw new \RuntimeException('Nguồn Tiki đang tắt trong cấu hình.');
            }
            $searchUrl = trim((string) ($source['api_search_url'] ?? ''));
            $categoryUrl = trim((string) ($source['api_category_url'] ?? ''));
            $queries = $source['queries'] ?? [];
            $categories = $source['category_ids'] ?? [];
            if ($searchUrl === '' || !is_array($queries) || $queries === []) {
                throw new \RuntimeException('Nguồn Tiki thiếu api_search_url hoặc queries.');
            }
            $queries = array_values(array_filter(array_map(
                static fn ($query): string => is_scalar($query) ? trim((string) $query) : '',
                $queries
            ), static fn (string $query): bool => $query !== ''));
            if ($queries === []) {
                throw new \RuntimeException('Nguồn Tiki không có từ khóa tìm kiếm.');
            }
            $categoryIds = [];
            foreach ((array) $categories as $category) {
                if (!is_scalar($category)) {
                    continue;
                }
                $categoryId = (int) $category;
                if ($categoryId > 0) {
                    $categoryIds[] = $categoryId;
                }
            }
            $categoryIds = array_values(array_unique($categoryIds));
            return [
                'api_search_url' => $searchUrl,
                'api_category_url' => $categoryUrl,
                'queries' => $queries,
                'categories' => $categoryIds,
                'max_pages' => min(12, max(1, (int) ($source['max_pages'] ?? $this->maxPages))),
            ];
        }
        throw new \RuntimeException('Không có nguồn Tiki trong ' . $this->sourcesFile . '.');
    }

    /** @return array<int, array<string, mixed>> */
    private function parseSearch(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            if ($this->isWafChallenge($json)) {
                $this->blocked = true;
                $this->errors[] = 'Tiki đang chặn truy cập tạm thời (WAF), thử lại sau.';
            } else {
                $this->errors[] = 'JSON tìm kiếm Tiki không hợp lệ.';
            }
            return [];
        }
        if (!is_array($data)) {
            return [];
        }
        $rows = $data['data'] ?? null;
        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, mixed> $product */
    private function categoryText(array $product): string
    {
        $parts = [
            (string) ($product['primary_category_name'] ?? ''),
            (string) ($product['primary_category_path'] ?? ''),
        ];
        $info = $product['visible_impression_info']['amplitude'] ?? null;
        if (is_array($info)) {
            foreach (['category_l1_name', 'category_l2_name', 'category_l3_name', 'category_l4_name'] as $key) {
                $parts[] = (string) ($info[$key] ?? '');
            }
        }
        return trim(implode(' ', array_filter(
            $parts,
            static fn (string $part): bool => $part !== ''
        )));
    }

    /** @param array<string, mixed> $product @return array<string, mixed> */
    private function searchRow(array $product): array
    {
        $path = ltrim((string) ($product['url_path'] ?? ''), '/');
        $link = $path !== '' ? 'https://tiki.vn/' . $path : '';
        return [
            'ten' => (string) ($product['name'] ?? ''),
            'mo_ta' => '',
            'anh_url' => $this->absoluteTikiImage((string) ($product['thumbnail_url'] ?? '')),
            'anh_phu' => [],
            'gia_hien_tai' => $product['price'] ?? null,
            'gia_goc' => $product['original_price'] ?? null,
            'phan_tram_giam' => $product['discount_rate'] ?? null,
            'danh_muc' => $this->categoryText($product),
            'thuong_hieu' => (string) ($product['brand_name'] ?? ''),
            'cua_hang' => (string) ($product['seller_name'] ?? ''),
            'ten_mien' => 'tiki.vn',
            'lien_ket_goc' => $link,
            'lien_ket_mua' => $link,
            'ma_ngoai' => 'tiki-' . (string) ($product['id'] ?? ''),
            'con_hang' => (int) ($product['availability'] ?? 1) === 1 ? 1 : 0,
            'ngay_nguon' => null,
        ];
    }

    /** @param array<string, mixed> $product @return array<string, mixed>|null */
    private function parseDetail(string $json, array $product): ?array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }
        if (!is_array($data)) {
            return null;
        }

        $images = [];
        foreach ((array) ($data['images'] ?? []) as $image) {
            if (!is_array($image)) {
                continue;
            }
            $candidate = (string) ($image['thumbnail_url'] ?? $image['small_url']
                ?? $image['medium_url'] ?? $image['large_url'] ?? $image['base_url'] ?? '');
            $candidate = $this->absoluteTikiImage($candidate);
            if ($candidate !== '' && !in_array($candidate, $images, true)) {
                $images[] = $candidate;
            }
            if (count($images) >= 5) {
                break;
            }
        }

        $path = ltrim((string) ($data['url_path'] ?? $product['url_path'] ?? ''), '/');
        $link = $path !== '' ? 'https://tiki.vn/' . $path : '';
        $seller = $data['current_seller']['name'] ?? $data['seller']['name'] ?? $product['seller_name'] ?? '';
        $brand = $data['brand']['name'] ?? $product['brand_name'] ?? '';
        $stock = ($data['inventory_status'] ?? '') === 'available'
            || (int) ($data['stock_item']['qty'] ?? 0) > 0;

        return [
            'ten' => (string) ($data['name'] ?? ''),
            'mo_ta' => (string) ($data['description'] ?? $data['short_description'] ?? ''),
            'anh_url' => $images[0] ?? '',
            'anh_phu' => $images,
            'gia_hien_tai' => $data['price'] ?? null,
            'gia_goc' => $data['original_price'] ?? $data['list_price'] ?? null,
            'phan_tram_giam' => $data['discount_rate'] ?? null,
            'thuong_hieu' => (string) $brand,
            'cua_hang' => (string) $seller,
            'lien_ket_goc' => $link,
            'lien_ket_mua' => $link,
            'con_hang' => $stock ? 1 : 0,
        ];
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $detail @return array<string, mixed> */
    private function mergeDetail(array $row, array $detail): array
    {
        $description = (string) ($detail['mo_ta'] ?? '');
        if ($description !== ''
            && mb_strlen($description, 'UTF-8') >= mb_strlen((string) ($row['mo_ta'] ?? ''), 'UTF-8')) {
            $row['mo_ta'] = $description;
        }
        foreach (['ten', 'thuong_hieu', 'cua_hang'] as $key) {
            if (!empty($detail[$key])) {
                $row[$key] = $detail[$key];
            }
        }
        if (!empty($detail['gia_hien_tai'])) {
            $row['gia_hien_tai'] = $detail['gia_hien_tai'];
        }
        if (!empty($detail['gia_goc'])) {
            $row['gia_goc'] = $detail['gia_goc'];
        }
        if (isset($detail['phan_tram_giam']) && $detail['phan_tram_giam'] !== null) {
            $row['phan_tram_giam'] = $detail['phan_tram_giam'];
        }
        if (!empty($detail['lien_ket_goc'])) {
            $row['lien_ket_goc'] = $detail['lien_ket_goc'];
            $row['lien_ket_mua'] = $detail['lien_ket_mua'] ?? $detail['lien_ket_goc'];
        }
        if (empty($row['anh_url']) && !empty($detail['anh_url'])) {
            $row['anh_url'] = $detail['anh_url'];
        }

        $detailImages = (array) ($detail['anh_phu'] ?? []);
        if ($detailImages !== []) {
            // Ảnh đầu tiên của API chi tiết trùng với ảnh chính.
            array_shift($detailImages);
        }
        $images = [];
        foreach (array_merge((array) ($row['anh_phu'] ?? []), $detailImages) as $image) {
            if (!is_scalar($image)) {
                continue;
            }
            $safe = SafeUrl::https((string) $image);
            if ($safe !== null && !in_array($safe, $images, true)) {
                $images[] = $safe;
            }
            if (count($images) >= 5) {
                break;
            }
        }
        $row['anh_phu'] = $images;
        if (isset($detail['con_hang'])) {
            $row['con_hang'] = (int) $detail['con_hang'];
        }
        return $row;
    }

    private function absoluteTikiImage(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        if (str_starts_with($url, '/')) {
            return 'https://tiki.vn' . $url;
        }
        return $url;
    }

    private function isTikiUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }
        $host = strtolower($host);
        if ($host !== 'tiki.vn' && !str_ends_with($host, '.tiki.vn')) {
            return false;
        }
        return SafeUrl::httpsAny($url) !== null;
    }

    /**
     * Tải nhiều trang chi tiết song song để giảm thời gian cào.
     * @param array<int|string, string> $urls
     * @return array<int|string, string>
     */
    private function httpGetMany(array $urls, float $deadline): array
    {
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0 || $urls === []) {
            return [];
        }
        $timeout = max(1, min($this->timeout, (int) floor($remaining)));
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
        $multi = curl_multi_init();
        $handles = [];
        $results = [];

        foreach ($urls as $key => $url) {
            if (!$this->isTikiUrl($url)) {
                $this->errors[] = 'Chỉ cho phép URL HTTPS thuộc Tiki.';
                continue;
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => max(1, min(8, $timeout)),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => $userAgent,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Accept-Language: vi-VN,vi;q=0.9,en;q=0.8',
                    'Referer: https://tiki.vn/',
                ],
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[spl_object_id($ch)] = ['key' => $key, 'handle' => $ch];
        }

        if ($handles === []) {
            curl_multi_close($multi);
            return [];
        }

        while ($handles !== []) {
            do {
                $status = curl_multi_exec($multi, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);
            if ($status !== CURLM_OK) {
                break;
            }
            if ($running > 0 && curl_multi_select($multi, 1.0) === -1) {
                usleep(1000);
            }
            while (($info = curl_multi_info_read($multi)) !== false) {
                $ch = $info['handle'];
                $id = spl_object_id($ch);
                $meta = $handles[$id] ?? null;
                if ($meta === null) {
                    continue;
                }
                $body = curl_multi_getcontent($ch);
                $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = curl_error($ch);
                curl_multi_remove_handle($multi, $ch);
                curl_close($ch);
                unset($handles[$id]);
                if ($body === false || $http < 200 || $http >= 300) {
                    $this->errors[] = 'Chi tiết Tiki HTTP ' . $http . ($err !== '' ? ': ' . $err : '');
                    continue;
                }
                if ($this->isWafChallenge((string) $body)) {
                    $this->blocked = true;
                    $this->errors[] = 'Tiki đang chặn truy cập tạm thời (WAF), thử lại sau.';
                    continue;
                }
                if (strlen((string) $body) > 3 * 1024 * 1024) {
                    $this->errors[] = 'Phản hồi Tiki vượt quá 3 MB.';
                    continue;
                }
                $results[$meta['key']] = (string) $body;
            }
        }

        foreach ($handles as $meta) {
            curl_multi_remove_handle($multi, $meta['handle']);
            curl_close($meta['handle']);
        }
        curl_multi_close($multi);

        return $results;
    }

    private function httpGet(string $url, float $deadline, string $accept): string
    {
        if (!$this->isTikiUrl($url)) {
            throw new \RuntimeException('Chỉ cho phép URL HTTPS thuộc Tiki.');
        }
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0) {
            throw new \RuntimeException('Hết thời gian tải Tiki.');
        }
        $timeout = max(1, min($this->timeout, (int) floor($remaining)));
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: ' . $userAgent . "\r\n"
                    . 'Accept: ' . $accept . "\r\n"
                    . "Accept-Language: vi-VN,vi;q=0.9,en;q=0.8\r\n"
                    . "Referer: https://tiki.vn/\r\n",
                'timeout' => $timeout,
                'follow_location' => 1,
                'max_redirects' => 2,
                'ignore_errors' => true,
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $lastError = error_get_last();
        if ($body === false) {
            $message = is_array($lastError) ? (string) ($lastError['message'] ?? '') : '';
            throw new \RuntimeException('Không tải được Tiki: ' . mb_substr($message, 0, 150, 'UTF-8'));
        }
        if (strlen($body) > 3 * 1024 * 1024) {
            throw new \RuntimeException('Phản hồi Tiki vượt quá 3 MB.');
        }
        $http = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $line, $match) === 1) {
                $http = (int) $match[1];
            }
        }
        if ($http < 200 || $http >= 300) {
            throw new \RuntimeException('Tiki trả HTTP ' . $http . '.');
        }
        if ($this->isWafChallenge($body)) {
            $this->blocked = true;
            throw new \RuntimeException('Tiki đang chặn truy cập tạm thời (WAF), thử lại sau.');
        }
        // Giãn nhịp để tránh bị giới hạn tần suất khi quét nhiều trang.
        usleep(250000);
        return $body;
    }

    private function isWafChallenge(string $body): bool
    {
        $trimmed = ltrim($body);
        return str_contains($body, '__waf_verification_challenge')
            || str_contains($body, 'Security Check in Progress')
            || str_starts_with($trimmed, '<!DOCTYPE html')
            || str_starts_with($trimmed, '<html');
    }
}
