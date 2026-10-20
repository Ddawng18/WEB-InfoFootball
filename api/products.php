<?php
declare(strict_types=1);

/** GET api/products.php?action=prices - cập nhật giá sản phẩm bóng đá cho AJAX. */

require dirname(__DIR__) . '/app/bootstrap.php';

(new App\Controllers\ApiController())->productPrices();
