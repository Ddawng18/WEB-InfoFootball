<?php
declare(strict_types=1);

/** POST api/admin/sync.php - quản trị bấm nút đồng bộ ngay. */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

(new App\Controllers\ApiController())->adminSync();
