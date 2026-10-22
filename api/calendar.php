<?php
declare(strict_types=1);

/** POST api/calendar.php - thêm/bỏ lịch và bật/tắt nhắc trận (AJAX). */

require dirname(__DIR__) . '/app/bootstrap.php';

(new App\Controllers\ApiController())->calendar();
