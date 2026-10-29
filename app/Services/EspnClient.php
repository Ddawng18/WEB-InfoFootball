<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Nguồn dữ liệu: ESPN public scoreboard API (không cần key/tài khoản).
 *
 * Endpoint: https://site.api.espn.com/apis/site/v2/sports/soccer/{league}/scoreboard?dates=YYYYMMDD
 * - Không có API key nên không có cơ chế suspend tài khoản do nhiều IP.
 * - Là API không chính thức: có thể đổi/rate-limit; chỉ gọi từ server, có TTL/cache.
 * - fetchLive(): lấy trận hôm nay, gồm cả trạng thái đang đá.
 * - fetchFixtures(null): lấy hôm nay + 2 cuối tuần kế tiếp để có trận sắp đá.
 */
final class EspnClient implements FootballDataProvider
{
    private const NGUON = 'ESPN';

    /** @var array<string, array{0:string,1:string}> slug => [tên giải, quốc gia/khu vực] */
    private const LEAGUE_MAP = [
        'eng.1' => ['Premier League', 'England'],
        'eng.2' => ['Championship', 'England'],
        'eng.fa' => ['FA Cup', 'England'],
        'esp.1' => ['La Liga', 'Spain'],
        'esp.copa_del_rey' => ['Copa del Rey', 'Spain'],
        'ita.1' => ['Serie A', 'Italy'],
        'ita.coppa_italia' => ['Coppa Italia', 'Italy'],
        'ger.1' => ['Bundesliga', 'Germany'],
        'ger.dfb_pokal' => ['DFB Pokal', 'Germany'],
        'fra.1' => ['Ligue 1', 'France'],
        'fra.coupe_de_france' => ['Coupe de France', 'France'],
        'uefa.champions' => ['UEFA Champions League', 'Europe'],
        'uefa.europa' => ['UEFA Europa League', 'Europe'],
        'uefa.europa.conf' => ['UEFA Conference League', 'Europe'],
        'uefa.wchampions' => ["UEFA Women's Champions League", 'Europe'],
        'mex.w.1' => ['Liga MX Femenil', 'Mexico'],
        'fifa.friendly.w' => ['International Women Friendlies', 'World'],
        'ned.1' => ['Eredivisie', 'Netherlands'],
        'por.1' => ['Primeira Liga', 'Portugal'],
        'bra.1' => ['Brasileirão Série A', 'Brazil'],
        'arg.1' => ['Liga Profesional', 'Argentina'],
        'usa.1' => ['MLS', 'USA'],
        'mex.1' => ['Liga MX', 'Mexico'],
        'jpn.1' => ['J1 League', 'Japan'],
        'kor.1' => ['K League 1', 'South Korea'],
        'vie.1' => ['V.League 1', 'Vietnam'],
        'sco.1' => ['Scottish Premiership', 'Scotland'],
        'tur.1' => ['Süper Lig', 'Turkey'],
        'bel.1' => ['Pro League', 'Belgium'],
        'aut.1' => ['Bundesliga', 'Austria'],
        'sui.1' => ['Super League', 'Switzerland'],
        'den.1' => ['Superliga', 'Denmark'],
        'swe.1' => ['Allsvenskan', 'Sweden'],
        'nor.1' => ['Eliteserien', 'Norway'],
        'gre.1' => ['Super League', 'Greece'],
        'sau.1' => ['Saudi Pro League', 'Saudi Arabia'],
        'chn.1' => ['Chinese Super League', 'China'],
        'aus.1' => ['A-League', 'Australia'],
    ];

    private string $baseUrl;
    private string $guideUrl;
    /** @var string[] */
    private array $leagues;
    private int $delayMs;

    /**
     * @param string[]|null $leagues
     */
    public function __construct(?string $baseUrl = null, ?array $leagues = null, ?int $delayMs = null, ?string $guideUrl = null)
    {
        $base = trim($baseUrl ?? Config::string('api', 'espn_base_url', 'https://site.api.espn.com/apis/site/v2/sports/soccer'));
        if ($base === '') {
            $base = 'https://site.api.espn.com/apis/site/v2/sports/soccer';
        }
        $this->baseUrl = rtrim($base, '/');

        $raw = $leagues ?? array_map('trim', explode(',', Config::string(
            'api',
            'espn_leagues',
            'eng.1,esp.1,ita.1,ger.1,fra.1,uefa.champions,uefa.wchampions,esp.copa_del_rey,mex.w.1,fifa.friendly.w'
        )));
        $this->leagues = array_values(array_filter(array_map('trim', $raw), static fn (string $s): bool => $s !== ''));
        $this->delayMs = max(0, $delayMs ?? Config::int('api', 'espn_request_delay_ms', 150));
        $this->guideUrl = trim($guideUrl ?? Config::string(
            'api',
            'espn_guide_url',
            'https://site.web.api.espn.com/apis/personalized/site/v2/guide/feed?limit=200'
        ));
        if ($this->guideUrl === '') {
            $this->guideUrl = 'https://site.web.api.espn.com/apis/personalized/site/v2/guide/feed?limit=200';
        }
    }

    public function name(): string
    {
        return self::NGUON;
    }

    public function fetchLive(): array
    {
        // Đầu ngày UTC: trận đá muộn hôm qua vẫn có thể đang diễn ra.
        $dates = [gmdate('Y-m-d')];
        if ((int) gmdate('G') < 6) {
            $dates[] = gmdate('Y-m-d', time() - 86400);
        }

        // Gộp guide feed và scoreboard các giải vào cùng một đợt tải song song.
        $requests = ['guide' => $this->guideUrl];
        foreach ($dates as $date) {
            $requests += $this->buildDateRequests($date);
        }
        $responses = $this->getMany($requests);

        $bundle = $this->rong();
        $part = $this->rong();
        $json = $responses['guide'] ?? [];

        foreach (($json['events'] ?? []) as $event) {
            if (!is_array($event)) {
                continue;
            }
            $sport = $event['sport'] ?? '';
            if (is_array($sport)) {
                $sport = (string) ($sport['slug'] ?? '');
            }
            if (strtolower((string) $sport) !== 'soccer') {
                continue;
            }
            $this->mapEvent($event, '', $part);
        }

        $this->gop($bundle, $part);

        // Bổ sung scoreboard từng giải để cập nhật cả các giải không nằm trong
        // guide feed (friendly nữ, Liga MX...) khi ESPN đổi trạng thái/tỉ số.
        foreach ($dates as $date) {
            $this->gop($bundle, $this->parseDateResponses($date, $responses));
        }

        $this->boSungLiveCoverage($bundle);

        return $bundle;
    }

    /**
     * Kiểm tra cờ liveAvailable của ESPN cho các trận đã quá giờ nhưng scoreboard
     * vẫn báo SAP_DIEN_RA. Summary cũng có thể trả trạng thái/tỉ số mới hơn.
     *
     * @param array<string, array<int, array<string, mixed>>> $bundle
     */
    private function boSungLiveCoverage(array &$bundle): void
    {
        $now = time();
        $ids = [];
        foreach ($bundle['matches'] as $m) {
            if (($m['trang_thai'] ?? '') !== 'SAP_DIEN_RA') {
                continue;
            }
            $batDau = strtotime((string) ($m['thoi_gian_utc'] ?? '') . ' UTC');
            if ($batDau === false || $batDau > $now - 300 || $batDau < $now - 4 * 3600) {
                continue;
            }
            $id = (string) ($m['ma'] ?? '');
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        $ids = array_map('strval', array_slice(array_keys($ids), 0, 10));
        if ($ids === []) {
            return;
        }

        $requests = [];
        foreach ($ids as $id) {
            $requests[$id] = $this->buildUrl('all/summary', ['event' => $id]);
        }
        $responses = $this->getMany($requests, 10, 8);

        foreach ($ids as $id) {
            $competition = $responses[$id]['header']['competitions'][0] ?? null;
            if (!is_array($competition)) {
                continue;
            }
            $coLive = isset($competition['liveAvailable']) ? (int) (bool) $competition['liveAvailable'] : null;
            $status = is_array($competition['status'] ?? null) ? $competition['status'] : [];
            $type = is_array($status['type'] ?? null) ? $status['type'] : [];
            $state = strtolower((string) ($type['state'] ?? ''));
            $typeName = strtoupper((string) ($type['name'] ?? ''));
            $moiTrangThai = $this->mapStatus($state, $typeName);

            $phut = 0;
            if ($state === 'in') {
                $clock = $status['clock'] ?? null;
                if (is_numeric($clock)) {
                    $phut = (int) floor(((int) $clock) / 60);
                } elseif (preg_match('/^(\d+)/', (string) ($status['displayClock'] ?? ''), $m) === 1) {
                    $phut = (int) $m[1];
                }
            }

            $tiSoNha = null;
            $tiSoKhach = null;
            foreach (($competition['competitors'] ?? []) as $competitor) {
                if (!is_array($competitor)) {
                    continue;
                }
                $side = (string) ($competitor['homeAway'] ?? '');
                if ($side === 'home') {
                    $tiSoNha = (int) ($competitor['score'] ?? 0);
                } elseif ($side === 'away') {
                    $tiSoKhach = (int) ($competitor['score'] ?? 0);
                }
            }

            foreach ($bundle['matches'] as &$m) {
                if ((string) ($m['ma'] ?? '') !== $id) {
                    continue;
                }
                if ($coLive !== null) {
                    $m['co_live'] = $coLive;
                }
                if ($state === 'in' || $state === 'post') {
                    $m['trang_thai'] = $moiTrangThai;
                    $m['phut'] = $phut;
                    if ($tiSoNha !== null && $tiSoKhach !== null) {
                        $m['ti_so_nha'] = $tiSoNha;
                        $m['ti_so_khach'] = $tiSoKhach;
                    }
                }
            }
            unset($m);
        }
    }

    public function fetchFixtures(?string $date = null): array
    {
        if ($date !== null && $date !== '') {
            return $this->layNgay($date);
        }

        $dates = $this->dates();
        $requests = [];
        foreach ($dates as $d) {
            $requests += $this->buildDateRequests($d);
        }
        $responses = $this->getMany($requests);
        $bundle = $this->rong();
        foreach ($dates as $d) {
            $this->gop($bundle, $this->parseDateResponses($d, $responses));
        }
        return $bundle;
    }

    public function fetchLeagues(): array
    {
        $bundle = $this->rong();
        $seenCountries = [];
        foreach ($this->leagues as $slug) {
            [$ten, $quocGia] = $this->tenGiai($slug);
            if ($quocGia !== '' && !isset($seenCountries[$quocGia])) {
                $seenCountries[$quocGia] = true;
                $bundle['countries'][] = ['ma' => $quocGia, 'ten' => $quocGia];
            }
            $bundle['leagues'][] = [
                'ma' => $slug,
                'ten' => $ten,
                'ma_quoc_gia' => $quocGia !== '' ? $quocGia : null,
                'mua_giai' => (string) gmdate('Y'),
            ];
        }
        return $bundle;
    }

    /**
     * Chi tiết trận từ ESPN summary: diễn biến chính, đội hình, thống kê, đối đầu.
     * @return array{events: array<int, array<string, mixed>>, lineups: array<int, array<string, mixed>>, statistics: array<int, array<string, mixed>>, h2h: array<int, array<string, mixed>>}
     */
    public function fetchMatchDetails(string $eventId): array
    {
        $empty = ['events' => [], 'lineups' => [], 'statistics' => [], 'h2h' => []];
        $eventId = trim($eventId);
        if ($eventId === '' || preg_match('/^\d+$/', $eventId) !== 1) {
            return $empty;
        }

        $url = $this->buildUrl('all/summary', ['event' => $eventId]);
        $responses = $this->getMany([$eventId => $url], 1, 8);
        $json = $responses[$eventId] ?? [];
        if (!is_array($json) || $json === []) {
            return $empty;
        }

        $out = $empty;

        foreach (($json['keyEvents'] ?? []) as $event) {
            if (!is_array($event)) {
                continue;
            }
            $type = is_array($event['type'] ?? null) ? $event['type'] : [];
            $typeText = trim((string) ($type['text'] ?? ''));
            $typeKey = mb_strtolower($typeText, 'UTF-8');
            $include = false;
            foreach (['goal', 'card', 'substitution', 'penalty'] as $needle) {
                if (str_contains($typeKey, $needle)) {
                    $include = true;
                    break;
                }
            }
            if (!$include) {
                continue;
            }

            $minute = 0;
            $displayClock = (string) ($event['clock']['displayValue'] ?? '');
            if (preg_match('/^(\d+)/', $displayClock, $m) === 1) {
                $minute = (int) $m[1];
            }
            if ($minute <= 0) {
                $clockValue = (float) ($event['clock']['value'] ?? 0);
                if ($clockValue > 0) {
                    $minute = max(1, (int) ceil($clockValue / 60));
                }
            }

            $player = '';
            foreach (($event['participants'] ?? []) as $participant) {
                $name = $participant['athlete']['displayName']
                    ?? $participant['athlete']['fullName']
                    ?? '';
                if ($name !== '') {
                    $player = (string) $name;
                    break;
                }
            }

            $out['events'][] = [
                'time' => ['elapsed' => $minute],
                'type' => $typeText,
                'detail' => trim((string) ($event['shortText'] ?? '')),
                'player' => ['name' => $player],
                'team' => ['name' => (string) ($event['team']['displayName'] ?? '')],
            ];
        }

        $seriesBlocks = is_array($json['seasonseries'] ?? null) ? $json['seasonseries'] : [];
        $seriesEvents = [];
        foreach ($seriesBlocks as $series) {
            if (is_array($series) && is_array($series['events'] ?? null)) {
                foreach ($series['events'] as $seriesEvent) {
                    $seriesEvents[] = $seriesEvent;
                }
            }
        }
        foreach ($seriesEvents as $event) {
            if (!is_array($event)) {
                continue;
            }
            $homeName = '';
            $awayName = '';
            $homeScore = '';
            $awayScore = '';
            foreach (($event['competitors'] ?? []) as $competitor) {
                $side = (string) ($competitor['homeAway'] ?? '');
                $teamName = (string) ($competitor['team']['displayName'] ?? '');
                $score = (string) ($competitor['score'] ?? '');
                if ($side === 'home') {
                    $homeName = $teamName;
                    $homeScore = $score;
                } elseif ($side === 'away') {
                    $awayName = $teamName;
                    $awayScore = $score;
                }
            }
            if ($homeName === '' && $awayName === '') {
                continue;
            }
            $out['h2h'][] = [
                'ngay' => (string) ($event['date'] ?? ''),
                'doi_nha' => $homeName,
                'doi_khach' => $awayName,
                'ti_so_nha' => $homeScore,
                'ti_so_khach' => $awayScore,
                'giai_ten' => (string) ($event['competitionName'] ?? ''),
                'trang_thai' => (string) ($event['status'] ?? ''),
            ];
            if (count($out['h2h']) >= 5) {
                break;
            }
        }

        $matchState = strtolower((string) ($json['header']['competitions'][0]['status']['type']['state'] ?? ''));
        $ordered = [];
        foreach ((in_array($matchState, ['in', 'post'], true) ? ($json['boxscore']['teams'] ?? []) : []) as $team) {
            if (!is_array($team)) {
                continue;
            }
            $stats = [];
            foreach (($team['statistics'] ?? []) as $stat) {
                $label = trim((string) ($stat['label'] ?? $stat['name'] ?? ''));
                $value = trim((string) ($stat['displayValue'] ?? ''));
                if ($label === '' || $value === '') {
                    continue;
                }
                $stats[] = ['type' => $label, 'value' => $value];
            }
            if ($stats === []) {
                continue;
            }
            $side = (string) ($team['homeAway'] ?? '');
            $ordered[$side] = [
                'team' => ['name' => (string) ($team['team']['displayName'] ?? '')],
                'statistics' => $stats,
            ];
        }
        if (isset($ordered['home'])) {
            $out['statistics'][] = $ordered['home'];
        }
        if (isset($ordered['away'])) {
            $out['statistics'][] = $ordered['away'];
        }

        foreach (($json['rosters'] ?? []) as $team) {
            if (!is_array($team)) {
                continue;
            }
            $starters = [];
            foreach (($team['roster'] ?? []) as $player) {
                if (empty($player['starter'])) {
                    continue;
                }
                $name = (string) ($player['athlete']['displayName']
                    ?? $player['athlete']['fullName']
                    ?? '');
                if ($name === '') {
                    continue;
                }
                $starters[] = [
                    'player' => [
                        'number' => (string) ($player['jersey'] ?? ''),
                        'name' => $name,
                    ],
                    'position' => (string) ($player['position']['displayName'] ?? ''),
                ];
            }
            if ($starters === []) {
                continue;
            }
            $out['lineups'][] = [
                'team' => ['name' => (string) ($team['team']['displayName'] ?? '')],
                'formation' => (string) ($team['formation'] ?? ''),
                'startXI' => $starters,
            ];
        }

        return $out;
    }

    private function layNgay(string $date): array
    {
        return $this->parseDateResponses($date, $this->getMany($this->buildDateRequests($date)));
    }

    /** @return array<string, string> */
    private function buildDateRequests(string $date): array
    {
        $ymd = str_replace('-', '', $date);
        $requests = [];
        foreach ($this->leagues as $slug) {
            $requests[$date . '|' . $slug] = $this->buildUrl($slug . '/scoreboard', ['dates' => $ymd]);
        }
        return $requests;
    }

    /**
     * @param array<string, array<string, mixed>> $responses
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function parseDateResponses(string $date, array $responses): array
    {
        $bundle = $this->rong();
        foreach ($this->leagues as $slug) {
            $json = $responses[$date . '|' . $slug] ?? [];
            $leagueName = '';
            if (is_array($json['leagues'][0] ?? null)) {
                $leagueName = (string) ($json['leagues'][0]['name'] ?? '');
            }
            $events = is_array($json['events'] ?? null) ? $json['events'] : [];
            $part = $this->rong();
            foreach ($events as $event) {
                if (is_array($event)) {
                    $this->mapEvent($event, $slug, $part, $leagueName);
                }
            }
            $this->gop($bundle, $part);
        }
        return $bundle;
    }

    /**
     * @param array<string, mixed> $event
     * @param array<string, array<int, array<string, mixed>>> $part
     */
    private function mapEvent(array $event, string $slug, array &$part, ?string $leagueName = null): void
    {
        $eventId = trim((string) ($event['id'] ?? ''));
        if ($eventId === '') {
            return;
        }

        $competition = is_array($event['competitions'][0] ?? null) ? $event['competitions'][0] : [];
        $competitors = is_array($event['competitors'] ?? null)
            ? $event['competitors']
            : (is_array($competition['competitors'] ?? null) ? $competition['competitors'] : []);
        $status = is_array($event['status'] ?? null)
            ? $event['status']
            : (is_array($competition['status'] ?? null) ? $competition['status'] : []);
        $type = is_array($status['type'] ?? null) ? $status['type'] : [];
        $state = strtolower((string) ($type['state'] ?? ''));
        $typeName = strtoupper((string) ($type['name'] ?? ''));

        $leagueNode = is_array($event['league'] ?? null) ? $event['league'] : [];
        $leagueSlug = trim((string) ($leagueNode['slug'] ?? $slug));
        if ($leagueSlug === '') {
            return;
        }
        [$tenGiai, $quocGia] = $this->tenGiai($leagueSlug);
        if ($leagueName !== null && $leagueName !== '') {
            $tenGiai = $leagueName;
        }
        $leagueName = trim((string) ($leagueNode['name'] ?? ''));
        if ($leagueName !== '') {
            $tenGiai = $leagueName;
        }
        $leagueCountry = trim((string) ($leagueNode['country'] ?? ''));
        if ($leagueCountry !== '') {
            $quocGia = $leagueCountry;
        }

        $seasonNode = is_array($event['season'] ?? null) ? $event['season'] : [];
        $season = (string) (($seasonNode['year'] ?? '') ?: gmdate('Y'));

        $part['leagues'][] = [
            'ma' => $leagueSlug,
            'ten' => $tenGiai,
            'ma_quoc_gia' => $quocGia !== '' ? $quocGia : null,
            'mua_giai' => $season,
        ];
        if ($quocGia !== '') {
            $part['countries'][] = ['ma' => $quocGia, 'ten' => $quocGia];
        }

        $home = null;
        $away = null;
        foreach ($competitors as $competitor) {
            if (!is_array($competitor)) {
                continue;
            }
            $team = is_array($competitor['team'] ?? null) ? $competitor['team'] : [];
            $teamId = trim((string) ($team['id'] ?? ''));
            if ($teamId === '') {
                continue;
            }
            $row = [
                'ma' => $teamId,
                'ten' => (string) ($team['displayName'] ?? $team['shortDisplayName'] ?? $team['name'] ?? ''),
                'ma_quoc_gia' => $quocGia !== '' ? $quocGia : null,
                'logo_url' => (string) ($team['logo'] ?? ''),
                'ti_so' => (string) ($competitor['score'] ?? '0'),
            ];
            $side = (string) ($competitor['homeAway'] ?? '');
            if ($side === 'home') {
                $home = $row;
            } elseif ($side === 'away') {
                $away = $row;
            }
        }
        if ($home === null || $away === null) {
            return;
        }

        $part['teams'][] = [
            'ma' => $home['ma'],
            'ten' => $home['ten'],
            'ma_quoc_gia' => $home['ma_quoc_gia'],
            'logo_url' => $home['logo_url'],
        ];
        $part['teams'][] = [
            'ma' => $away['ma'],
            'ten' => $away['ten'],
            'ma_quoc_gia' => $away['ma_quoc_gia'],
            'logo_url' => $away['logo_url'],
        ];

        $phut = 0;
        if ($state === 'in') {
            $clock = $status['clock'] ?? null;
            if (is_numeric($clock)) {
                $phut = (int) floor(((int) $clock) / 60);
            } else {
                $display = (string) ($status['displayClock'] ?? '');
                if (preg_match('/^(\d+)/', $display, $m) === 1) {
                    $phut = (int) $m[1];
                }
            }
        }

        $part['matches'][] = [
            'ma' => $eventId,
            'ma_giai' => $leagueSlug,
            'ma_doi_nha' => $home['ma'],
            'ma_doi_khach' => $away['ma'],
            'thoi_gian_utc' => $this->toUtc((string) ($event['date'] ?? '')),
            'trang_thai' => $this->mapStatus($state, $typeName),
            'phut' => $phut,
            'ti_so_nha' => (int) $home['ti_so'],
            'ti_so_khach' => (int) $away['ti_so'],
        ];
    }

    private function mapStatus(string $state, string $typeName): string
    {
        if ($state === 'post') {
            return 'KET_THUC';
        }
        if ($state === 'in') {
            return str_contains($typeName, 'HALFTIME') ? 'NGHI_GIUA_HIEP' : 'DANG_DA';
        }
        if (str_contains($typeName, 'POSTPONED') || str_contains($typeName, 'DELAYED')) {
            return 'HOAN';
        }
        if (str_contains($typeName, 'CANCELED') || str_contains($typeName, 'CANCELLED')) {
            return 'HUY';
        }
        return 'SAP_DIEN_RA';
    }

    /** @return string[] */
    private function dates(): array
    {
        $dates = [gmdate('Y-m-d')];
        $start = strtotime(gmdate('Y-m-d'));

        // 3 ngày gần nhất: lấy trận đã kết thúc (cuối tuần trước, giữa tuần...).
        for ($i = 1; $i <= 3; $i++) {
            $dates[] = gmdate('Y-m-d', $start - $i * 86400);
        }

        // 2 cuối tuần tới: lấy trận sắp diễn ra.
        for ($i = 1; $i <= 14; $i++) {
            $ts = $start + $i * 86400;
            $dow = (int) gmdate('w', $ts);
            if ($dow === 6 || $dow === 0) {
                $dates[] = gmdate('Y-m-d', $ts);
            }
        }

        return array_values(array_unique($dates));
    }

    /** @return array{0:string,1:string} */
    private function tenGiai(string $slug): array
    {
        return self::LEAGUE_MAP[$slug] ?? [$slug, ''];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function rong(): array
    {
        return ['countries' => [], 'leagues' => [], 'teams' => [], 'matches' => []];
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $bundle
     * @param array<string, array<int, array<string, mixed>>> $part
     */
    private function gop(array &$bundle, array $part): void
    {
        foreach (['countries', 'leagues', 'teams'] as $key) {
            $map = [];
            foreach (array_merge($bundle[$key], $part[$key]) as $row) {
                $ma = (string) ($row['ma'] ?? '');
                if ($ma === '') {
                    $map[] = $row;
                    continue;
                }
                $map[$ma] = $row;
            }
            $bundle[$key] = array_values($map);
        }
        $bundle['matches'] = array_merge($bundle['matches'], $part['matches']);
    }

    private function toUtc(string $iso): string
    {
        if ($iso === '') {
            return gmdate('Y-m-d H:i:s');
        }
        try {
            $date = new \DateTime($iso);
            $date->setTimezone(new \DateTimeZone('UTC'));
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return gmdate('Y-m-d H:i:s');
        }
    }

    /**
     * @param array<string, string|int> $params
     */
    private function buildUrl(string $path, array $params = []): string
    {
        $url = preg_match('#^https?://#i', $path) === 1
            ? $path
            : $this->baseUrl . '/' . ltrim($path, '/');
        if ($params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
        }
        return $url;
    }

    private function createHandle(string $url, int $timeout): \CurlHandle
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FastFootBalInfo/1.0)',
            CURLOPT_HTTPHEADER => ['Cache-Control: no-cache', 'Pragma: no-cache'],
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
        ]);
        return $ch;
    }

    /**
     * Gọi nhiều URL song song (giới hạn số kết nối đồng thời) và trả JSON theo khóa.
     * @param array<string, string> $urls
     * @return array<string, array<string, mixed>>
     */
    private function getMany(array $urls, int $concurrency = 10, int $timeout = 20): array
    {
        if ($urls === []) {
            return [];
        }

        $multi = curl_multi_init();
        if (defined('CURLMOPT_PIPELINING') && defined('CURLPIPE_MULTIPLEX')) {
            curl_multi_setopt($multi, CURLMOPT_PIPELINING, CURLPIPE_MULTIPLEX);
        }
        if (defined('CURLMOPT_MAX_HOST_CONNECTIONS')) {
            curl_multi_setopt($multi, CURLMOPT_MAX_HOST_CONNECTIONS, $concurrency);
        }
        $queue = $urls;
        $handles = [];
        $results = [];
        $active = 0;
        $started = 0;

        $startNext = function () use (&$queue, &$handles, &$active, &$started, $multi, $timeout, $concurrency): void {
            foreach ($queue as $key => $url) {
                unset($queue[$key]);
                $ch = $this->createHandle($url, $timeout);
                curl_multi_add_handle($multi, $ch);
                $handles[spl_object_id($ch)] = ['key' => $key, 'url' => $url, 'handle' => $ch];
                $active++;
                $started++;
                if ($this->delayMs > 0 && $started % $concurrency === 0) {
                    usleep($this->delayMs * 1000);
                }
                return;
            }
        };

        for ($i = 0; $i < $concurrency; $i++) {
            $startNext();
        }

        while ($active > 0) {
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
                $active--;

                if ($body === false || $http >= 400) {
                    error_log('ESPN lỗi: HTTP ' . $http . ' ' . $err . ' ' . $meta['url']);
                } else {
                    $data = json_decode((string) $body, true);
                    $results[$meta['key']] = is_array($data) ? $data : [];
                }
                $startNext();
            }
        }

        foreach ($handles as $meta) {
            curl_multi_remove_handle($multi, $meta['handle']);
            curl_close($meta['handle']);
        }
        curl_multi_close($multi);

        return $results;
    }
}
