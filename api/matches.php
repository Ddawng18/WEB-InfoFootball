<?php
declare(strict_types=1);

/** GET api/matches.php - danh sách trận (trang chủ hoặc lịch thi đấu có lọc). */

require dirname(__DIR__) . '/app/bootstrap.php';

(new App\Controllers\ApiController())->matches();
