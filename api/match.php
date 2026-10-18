<?php
declare(strict_types=1);

/** GET api/match.php?id= - chi tiết một trận + sự kiện. */

require dirname(__DIR__) . '/app/bootstrap.php';

(new App\Controllers\ApiController())->match();
