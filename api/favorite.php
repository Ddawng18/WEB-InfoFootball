<?php
declare(strict_types=1);

/** POST api/favorite.php - bật/tắt đội yêu thích (AJAX nút trái tim). */

require dirname(__DIR__) . '/app/bootstrap.php';

(new App\Controllers\ApiController())->favorite();
