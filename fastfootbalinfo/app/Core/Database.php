<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Điểm truy cập CSDL duy nhất. Chỉ có đúng một nơi gửi câu CALL trong toàn bộ ứng dụng.
 * Mọi Repository phải đi qua đây.
 */
final class Database
{
    private static ?Database $instance = null;
    private \mysqli $mysqli;

    private function __construct()
    {
        $host = Config::string('db', 'host', '127.0.0.1');
        $port = Config::int('db', 'port', 3306);
        $name = Config::string('db', 'name', 'fastfootbalinfo');
        $user = Config::string('db', 'user', 'root');
        $pass = Config::string('db', 'pass', '');

        mysqli_report(MYSQLI_REPORT_OFF);
        $this->mysqli = @new \mysqli($host, $user, $pass, $name, $port);
        if ($this->mysqli->connect_errno) {
            throw new \RuntimeException('Không kết nối được CSDL: ' . $this->mysqli->connect_error);
        }
        $this->mysqli->set_charset('utf8mb4');
        // Quy ước: DB lưu giờ UTC. Ép timezone phiên về UTC để NOW() đồng bộ với PHP.
        $this->mysqli->query("SET time_zone = '+00:00'");
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function raw(): \mysqli
    {
        return $this->mysqli;
    }

    /**
     * Gọi một routine. $proc PHẢI là hằng số trong RoutineName (không nhận từ request).
     * Prepared statement (?) chỉ dùng để truyền tham số an toàn cho CALL;
     * toàn bộ nghiệp vụ SQL vẫn nằm trong routine.
     *
     * @param array<int, scalar|null> $params
     * @return array<int, array<string, mixed>> tập kết quả đầu tiên
     */
    public function callProc(string $proc, array $params = []): array
    {
        $marks = $params === [] ? '' : implode(',', array_fill(0, count($params), '?'));
        $stmt = $this->mysqli->prepare('CALL ' . $proc . '(' . $marks . ')');
        if ($stmt === false) {
            throw new \RuntimeException('Không chuẩn bị được CALL ' . $proc . ': ' . $this->mysqli->error);
        }

        if ($params !== []) {
            $types = str_repeat('s', count($params));
            $values = array_values($params);
            $stmt->bind_param($types, ...$values);
        }

        $stmt->execute();
        $rows = [];
        $result = $stmt->get_result();
        if ($result instanceof \mysqli_result) {
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        }
        $stmt->close();

        // Dọn sạch mọi result set còn lại để tránh lỗi "Commands out of sync"
        while ($this->mysqli->more_results() && $this->mysqli->next_result()) {
            $extra = $this->mysqli->store_result();
            if ($extra instanceof \mysqli_result) {
                $extra->free();
            }
        }

        return $rows;
    }

    /** Gọi routine ghi và trả về mã `ma` (mặc định 'LOI_HE_THONG'). */
    public function callProcMa(string $proc, array $params = []): string
    {
        $rows = $this->callProc($proc, $params);
        $ma = $rows[0]['ma'] ?? null;
        return is_string($ma) && $ma !== '' ? $ma : 'LOI_HE_THONG';
    }
}
