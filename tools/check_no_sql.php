<?php
declare(strict_types=1);

/**
 * Kiểm chứng quy tắc bất biến: không có SQL nghiệp vụ trong file .php.
 * Chỉ cho phép câu CALL routine (do Database::callProc phát sinh) và
 * lệnh thiết lập phiên SET time_zone.
 *
 * Chạy: php tools/check_no_sql.php
 * Kết quả mong đợi: "0 vi phạm".
 */

$root = dirname(__DIR__);

$thuMucQuet = ['app', 'api', 'views'];
$fileLe = ['index.php'];

$mauSql = [
    '/\bSELECT\b[^;]{0,160}?\bFROM\b/i' => 'SELECT ... FROM',
    '/\bINSERT\s+INTO\b/i' => 'INSERT INTO',
    '/\bUPDATE\b[^;]{0,160}?\bSET\b/i' => 'UPDATE ... SET',
    '/\bDELETE\s+FROM\b/i' => 'DELETE FROM',
    '/\bDROP\s+(TABLE|DATABASE|PROCEDURE|FUNCTION)\b/i' => 'DROP ...',
    '/\bTRUNCATE\s+TABLE\b/i' => 'TRUNCATE TABLE',
    '/\bCREATE\s+(TABLE|PROCEDURE|FUNCTION|DATABASE)\b/i' => 'CREATE ...',
    '/\bALTER\s+TABLE\b/i' => 'ALTER TABLE',
];

$files = [];
foreach ($fileLe as $f) {
    $files[] = $root . '/' . $f;
}
foreach ($thuMucQuet as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

$viPham = 0;
$soCall = 0;

foreach ($files as $file) {
    $noiDung = file_get_contents($file);
    if ($noiDung === false) {
        continue;
    }
    $soCall += preg_match_all('/\bCALL\b/', $noiDung);
    $lines = preg_split('/\r\n|\r|\n/', $noiDung);
    foreach ($lines as $i => $line) {
        foreach ($mauSql as $regex => $nhan) {
            if (preg_match($regex, $line)) {
                $viPham++;
                printf("VI PHẠM [%s] %s:%d -> %s\n", $nhan, str_replace($root . '/', '', $file), $i + 1, trim($line));
            }
        }
    }
}

printf("Đã quét %d file .php. Số câu CALL (hợp lệ): %d. Số vi phạm SQL nghiệp vụ: %d\n",
    count($files), $soCall, $viPham);

exit($viPham === 0 ? 0 : 1);
