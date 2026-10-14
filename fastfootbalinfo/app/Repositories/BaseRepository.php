<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Lớp nền của tầng truy cập dữ liệu. Mỗi phương thức con = một routine.
 * Chỉ dùng tên routine lấy từ hằng số RoutineName.
 */
abstract class BaseRepository
{
    protected Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** @param array<int, scalar|null> $params @return array<int, array<string, mixed>> */
    protected function call(string $proc, array $params = []): array
    {
        return $this->db->callProc($proc, $params);
    }

    /** @param array<int, scalar|null> $params */
    protected function callMa(string $proc, array $params = []): string
    {
        return $this->db->callProcMa($proc, $params);
    }
}
