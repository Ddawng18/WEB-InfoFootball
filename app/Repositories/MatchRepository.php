<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\RoutineName;
use App\Models\FootballMatch;

/** Truy cập dữ liệu trận đấu (F1, F2, F3, F5). */
final class MatchRepository extends BaseRepository
{
    /**
     * @param array{tu_utc?:?string, den_utc?:?string, giai_id?:?int, doi_id?:?int,
     *              trang_thai?:?string, tu_khoa?:?string, gioi_han?:int, bo_qua?:int,
     *              nhom?:?string} $f
     * @return array{items: FootballMatch[], tong: int}
     */
    public function getList(array $f): array
    {
        $rows = $this->call(RoutineName::DS_TRAN_DAU, [
            $f['tu_utc'] ?? null,
            $f['den_utc'] ?? null,
            $f['giai_id'] ?? null,
            $f['doi_id'] ?? null,
            $f['trang_thai'] ?? null,
            $f['tu_khoa'] ?? null,
            $f['gioi_han'] ?? 50,
            $f['bo_qua'] ?? 0,
            $f['nhom'] ?? null,
            $f['sap_xep'] ?? null,
        ]);
        $items = [];
        foreach ($rows as $row) {
            $items[] = FootballMatch::fromRow($row);
        }
        $tong = $rows === [] ? 0 : (int) ($rows[0]['tong'] ?? count($rows));
        return ['items' => $items, 'tong' => $tong];
    }

    public function getById(int $id): ?FootballMatch
    {
        $rows = $this->call(RoutineName::CHI_TIET_TRAN_DAU, [$id]);
        if ($rows === [] || ($rows[0]['ma'] ?? '') !== 'OK') {
            return null;
        }
        return FootballMatch::fromRow($rows[0]);
    }

    /** @return array<int, array<string, mixed>> sự kiện của trận (Should) */
    public function getEvents(int $tranId): array
    {
        return $this->call(RoutineName::DS_SU_KIEN_TRAN, [$tranId]);
    }

    /** @return array<int, array<string, mixed>> bảng xếp hạng (Should) */
    public function getStandings(int $giaiId): array
    {
        return $this->call(RoutineName::DS_BANG_XEP_HANG, [$giaiId]);
    }
}
