<?php
declare(strict_types=1);

/** GET/POST api/notifications.php - danh sách thông báo trong web và đánh dấu đã đọc. */

require dirname(__DIR__) . '/app/bootstrap.php';

(new App\Controllers\ApiController())->notifications();
