<?php
declare(strict_types=1);

namespace App\Services;

final class NewsImageService
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /**
     * @param array<string, mixed> $file
     * @return array{ok:bool,url:string,error:string}
     */
    public function save(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'url' => '', 'error' => 'NO_FILE'];
        }
        if ($error !== UPLOAD_ERR_OK) {
            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                return ['ok' => false, 'url' => '', 'error' => 'TOO_LARGE'];
            }
            return ['ok' => false, 'url' => '', 'error' => 'UPLOAD_ERROR'];
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'url' => '', 'error' => 'TOO_LARGE'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'url' => '', 'error' => 'UPLOAD_ERROR'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        if (!is_string($mime) || !isset(self::MIME_EXT[$mime])) {
            return ['ok' => false, 'url' => '', 'error' => 'INVALID_TYPE'];
        }

        $dir = APP_ROOT . '/public/uploads/news';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'url' => '', 'error' => 'UPLOAD_ERROR'];
        }

        $name = 'news_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . self::MIME_EXT[$mime];
        if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
            return ['ok' => false, 'url' => '', 'error' => 'UPLOAD_ERROR'];
        }
        @chmod($dir . '/' . $name, 0644);

        return ['ok' => true, 'url' => asset('uploads/news/' . $name), 'error' => ''];
    }

    public function delete(?string $url): void
    {
        $value = trim((string) $url);
        if ($value === '') {
            return;
        }
        $path = parse_url($value, PHP_URL_PATH);
        $path = is_string($path) ? $path : $value;
        $needle = 'public/uploads/news/';
        $pos = strpos($path, $needle);
        if ($pos === false) {
            return;
        }
        $name = basename(substr($path, $pos + strlen($needle)));
        if (preg_match('/^news_[A-Za-z0-9_.-]+$/', $name) !== 1) {
            return;
        }
        $file = APP_ROOT . '/public/uploads/news/' . $name;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
