<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Nguồn dữ liệu thật: API-Football (api-sports.io), xác thực bằng header x-apisports-key.
 * Chỉ gọi từ máy chủ; key không bao giờ ra trình duyệt.
 *
 * Lưu ý gói Free: 100 request/ngày; /fixtures chỉ trong khoảng ngày hiện tại ±1;
 * /leagues chỉ truy cập mùa 2022-2024 (đặt qua config [api] season).
 */
final class ApiFootballClient implements FootballDataProvider
{
    private const NGUON = 'APIFOOTBALL';

    private string $baseUrl;
    private string $key;
    private int $season;
    /** @var int[] */
    private array $leagueIds;
    /** @var string[] */
    private array $countries;

    public function __construct()
    {
        $this->baseUrl = rtrim(Config::string('api', 'base_url', 'https://v3.football.api-sports.io'), '/') . '/';
        $this->key = Config::string('api', 'key', '');
        $this->season = Config::int('api', 'season', 2023);
        $rawLeagueIds = Config::string('api', 'league_ids', '39,140,2,78,135,61,88,94,98,340');
        $this->leagueIds = array_values(array_filter(array_map('intval', explode(',', $rawLeagueIds))));
        $raw = Config::string('api', 'countries', 'Vietnam,Japan,England');
        $this->countries = array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function name(): string
    {
        return self::NGUON;
    }

    public function fetchLive(): array
    {
        return $this->mapFixtures($this->get('fixtures', ['live' => 'all']));
    }

    public function fetchFixtures(?string $date = null): array
    {
        // Gói Free giới hạn ±1 ngày; mặc định lấy hôm nay (UTC).
        $date = ($date !== null && $date !== '') ? $date : gmdate('Y-m-d');
        return $this->mapFixtures($this->get('fixtures', ['date' => $date]));
    }

    public function fetchLeagues(): array
    {
        $bundle = ['countries' => [], 'leagues' => [], 'teams' => [], 'matches' => []];

        $c = $this->get('countries', []);
        foreach (($c['response'] ?? []) as $row) {
            if (isset($row['name'])) {
                $bundle['countries'][] = ['ma' => (string) $row['name'], 'ten' => (string) $row['name']];
            }
        }

        foreach ($this->leagueIds as $leagueId) {
            $l = $this->get('leagues', ['id' => $leagueId, 'season' => $this->season]);
            foreach (($l['response'] ?? []) as $item) {
                $lg = $item['league'] ?? [];
                $ct = $item['country'] ?? [];
                if (!isset($lg['id'])) {
                    continue;
                }
                $tenQg = is_array($ct) ? (string) ($ct['name'] ?? '') : (string) $ct;
                $bundle['leagues'][] = [
                    'ma' => (string) $lg['id'],
                    'ten' => (string) ($lg['name'] ?? ''),
                    'ma_quoc_gia' => $tenQg,
                    'mua_giai' => (string) $this->season,
                ];
                if ($tenQg !== '') { $bundle['countries'][] = ['ma' => $tenQg, 'ten' => $tenQg]; }
                $teams = $this->get('teams', ['league' => $leagueId, 'season' => $this->season]);
                foreach (($teams['response'] ?? []) as $row) {
                    $team = $row['team'] ?? [];
                    if (empty($team['id'])) { continue; }
                    $bundle['teams'][(string) $team['id']] = [
                        'ma' => (string) $team['id'], 'ten' => (string) ($team['name'] ?? ''),
                        'ma_quoc_gia' => $tenQg, 'logo_url' => (string) ($team['logo'] ?? ''),
                    ];
                }
            }
        }
        $bundle['teams'] = array_values($bundle['teams']);
        $bundle['countries'] = array_values(array_column(array_reverse($bundle['countries']), null, 'ma'));
        return $bundle;
    }

    /** @return array{events:array,lineups:array,statistics:array} */
    public function fetchMatchDetails(string $fixtureId): array
    {
        if (!ctype_digit($fixtureId) || $this->key === '') {
            return ['events' => [], 'lineups' => [], 'statistics' => []];
        }
        $cacheDir = APP_ROOT . '/storage/cache';
        if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0775, true); }
        $cacheFile = $cacheDir . '/fixture-details-' . $fixtureId . '.json';
        $ttl = 300;
        if (is_file($cacheFile) && filemtime($cacheFile) > time() - $ttl) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) { return $cached; }
        }
        $details = ['events' => [], 'lineups' => [], 'statistics' => []];
        foreach (['events', 'lineups', 'statistics'] as $endpoint) {
            $response = $this->get('fixtures/' . $endpoint, ['fixture' => (int) $fixtureId]);
            $details[$endpoint] = is_array($response['response'] ?? null) ? $response['response'] : [];
        }
        if ($details['events'] !== [] || $details['lineups'] !== [] || $details['statistics'] !== []) {
            file_put_contents($cacheFile, json_encode($details, JSON_UNESCAPED_UNICODE));
        }
        return $details;
    }

    public function findTeamLogo(string $teamName): string
    {
        $name = trim($teamName);
        if ($name === '' || $this->key === '') { return ''; }
        $aliases = [
            'urawa red diamonds' => ['Urawa Reds', 'Urawa'],
            'hà nội fc' => ['Hanoi FC', 'Hanoi'],
            'hoàng anh gia lai' => ['Hoang Anh Gia Lai', 'HAGL'],
            'vietnam' => ['Vietnam'],
        ];
        $queries = array_values(array_unique(array_merge([$name], $aliases[mb_strtolower($name, 'UTF-8')] ?? [])));
        $cache = APP_ROOT . '/storage/cache/team-logo-v2-' . sha1(mb_strtolower($name, 'UTF-8')) . '.txt';
        if (is_file($cache) && filemtime($cache) > time() - 86400) {
            $cached = trim((string) file_get_contents($cache));
            return $cached === '-' ? '' : $cached;
        }
        foreach ($queries as $query) {
            $data = $this->get('teams', ['search' => $query]);
            $rows = $data['response'] ?? [];
            foreach ($rows as $row) {
                $found = mb_strtolower((string) ($row['team']['name'] ?? ''), 'UTF-8');
                $wanted = mb_strtolower($query, 'UTF-8');
                if ($found !== '' && ($found === $wanted || str_contains($found, $wanted) || str_contains($wanted, $found))) {
                    $url = (string) ($row['team']['logo'] ?? '');
                    if (str_starts_with($url, 'https://media.api-sports.io/football/teams/')) {
                        if (!is_dir(dirname($cache))) { @mkdir(dirname($cache), 0775, true); }
                        @file_put_contents($cache, $url);
                        return $url;
                    }
                }
            }
        }
        if (!is_dir(dirname($cache))) { @mkdir(dirname($cache), 0775, true); }
        @file_put_contents($cache, '-');
        return '';
    }

    /**
     * @param array<string, string|int> $params
     * @return array<string, mixed>
     */
    private function get(string $path, array $params): array
    {
        if ($this->key === '') {
            return [];
        }
        $url = $this->baseUrl . $path . ($params !== [] ? '?' . http_build_query($params) : '');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['x-apisports-key: ' . $this->key],
            CURLOPT_USERAGENT => 'FastFootBalInfo/1.0',
        ]);
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $http >= 400) {
            error_log('API-Football lỗi: HTTP ' . $http . ' ' . $err);
            return [];
        }
        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            return [];
        }
        if (!empty($data['errors'])) {
            error_log('API-Football errors: ' . json_encode($data['errors'], JSON_UNESCAPED_UNICODE));
            return [];
        }
        return $data;
    }

    /**
     * Ánh xạ response của /fixtures sang bundle chuẩn hóa, đồng thời suy ra
     * quốc gia/giải/đội có trong dữ liệu để upsert trước khi upsert trận.
     * @param array<string, mixed> $json
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function mapFixtures(array $json): array
    {
        $bundle = ['countries' => [], 'leagues' => [], 'teams' => [], 'matches' => []];
        $resp = $json['response'] ?? [];
        if (!is_array($resp)) {
            return $bundle;
        }

        $seenC = [];
        $seenL = [];
        $seenT = [];

        foreach ($resp as $f) {
            if (!is_array($f)) {
                continue;
            }
            $fx = $f['fixture'] ?? [];
            $lg = $f['league'] ?? [];
            $leagueCountry = trim((string) ($lg['country'] ?? ''));
            if (!isset($lg['id']) || !in_array((int) $lg['id'], $this->leagueIds, true)) {
                continue;
            }
            $tm = $f['teams'] ?? [];
            $goals = $f['goals'] ?? [];
            $st = $fx['status'] ?? [];

            $lgId = isset($lg['id']) ? (string) $lg['id'] : '';
            $country = isset($lg['country']) && is_string($lg['country']) ? $lg['country'] : '';

            if ($country !== '' && !isset($seenC[$country])) {
                $seenC[$country] = true;
                $bundle['countries'][] = ['ma' => $country, 'ten' => $country];
            }
            if ($lgId !== '' && !isset($seenL[$lgId])) {
                $seenL[$lgId] = true;
                $bundle['leagues'][] = [
                    'ma' => $lgId,
                    'ten' => (string) ($lg['name'] ?? ''),
                    'ma_quoc_gia' => $country,
                    'mua_giai' => isset($lg['season']) ? (string) $lg['season'] : '',
                ];
            }
            foreach (['home', 'away'] as $side) {
                $t = $tm[$side] ?? null;
                if (!is_array($t) || !isset($t['id'])) {
                    continue;
                }
                $id = (string) $t['id'];
                if (!isset($seenT[$id])) {
                    $seenT[$id] = true;
                    $bundle['teams'][] = [
                        'ma' => $id,
                        'ten' => (string) ($t['name'] ?? ''),
                        'ma_quoc_gia' => $country,
                        'logo_url' => (string) ($t['logo'] ?? ''),
                    ];
                }
            }

            if (!empty($fx['id'])) {
                $bundle['matches'][] = [
                    'ma' => (string) $fx['id'],
                    'ma_giai' => $lgId,
                    'ma_doi_nha' => (string) ($tm['home']['id'] ?? ''),
                    'ma_doi_khach' => (string) ($tm['away']['id'] ?? ''),
                    'thoi_gian_utc' => $this->toUtc((string) ($fx['date'] ?? '')),
                    'trang_thai' => $this->mapStatus((string) ($st['short'] ?? '')),
                    'phut' => (int) ($st['elapsed'] ?? 0),
                    'ti_so_nha' => (int) ($goals['home'] ?? 0),
                    'ti_so_khach' => (int) ($goals['away'] ?? 0),
                ];
            }
        }

        return $bundle;
    }

    /** Ánh xạ mã trạng thái ngắn của API-Football sang ENUM nội bộ. */
    private function mapStatus(string $s): string
    {
        return match (true) {
            in_array($s, ['FT', 'AET', 'PEN'], true) => 'KET_THUC',
            $s === 'HT' => 'NGHI_GIUA_HIEP',
            in_array($s, ['1H', '2H', 'ET', 'BT', 'P', 'SUSP', 'INT', 'LIVE'], true) => 'DANG_DA',
            $s === 'PST' => 'HOAN',
            in_array($s, ['CANC', 'ABD', 'AWD', 'WO'], true) => 'HUY',
            default => 'SAP_DIEN_RA',
        };
    }

    /** ISO-8601 (có offset) -> 'Y-m-d H:i:s' UTC. */
    private function toUtc(string $iso): string
    {
        if ($iso === '') {
            return gmdate('Y-m-d H:i:s');
        }
        try {
            $d = new \DateTime($iso);
            $d->setTimezone(new \DateTimeZone('UTC'));
            return $d->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return gmdate('Y-m-d H:i:s');
        }
    }
}
