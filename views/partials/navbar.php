<?php
/**
 * Header chung: thương hiệu + menu + tài khoản.
 * Biến: $user (\App\Models\User|null)
 */
$user = $user ?? null;
$current = isset($_GET['r']) && is_string($_GET['r']) ? $_GET['r'] : 'home';
$active = static fn (string ...$names): string => in_array($current, $names, true) ? ' active' : '';
?>
<header class="as-header">
    <div class="container">
        <div class="as-bar">
            <a class="as-brand" href="<?= h(route('home')) ?>">
                <span class="as-brand-mark" aria-hidden="true"><i class="bi bi-shield-fill"></i><b>F</b></span>
                <span>FastFoot<span class="dot">Ball</span>Info</span>
            </a>

            <nav class="as-nav">
                <a class="<?= trim($active('home')) ?>" href="<?= h(route('home')) ?>">Trang chủ</a>
                <a class="<?= trim($active('lich')) ?>" href="<?= h(route('lich')) ?>">Lịch thi đấu</a>
                <a class="<?= trim($active('giai')) ?>" href="<?= h(route('giai')) ?>">Giải đấu</a>
                <a class="<?= trim($active('doi', 'doi-chitiet')) ?>" href="<?= h(route('doi')) ?>">Đội bóng</a>
                <a class="<?= trim($active('tintuc', 'tintuc-chitiet')) ?>" href="<?= h(route('tintuc')) ?>">Tin tức</a>
                <a class="<?= trim($active('san-pham')) ?> as-nav-shop" href="<?= h(route('san-pham')) ?>" <?= $current === 'san-pham' ? 'aria-current="page"' : '' ?>>Sản phẩm</a>
            </nav>

            <div class="as-spacer"></div>

            <div class="as-actions">
                <?php if ($user): ?>
                    <div class="dropdown as-notify">
                        <a class="as-icon as-notify-toggle" href="#" role="button" data-bs-toggle="dropdown"
                           data-notify-toggle aria-label="Thông báo" aria-expanded="false">
                            <i class="bi bi-bell"></i><span class="as-notify-badge" data-notify-count hidden>0</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end as-notify-menu" data-notify-list>
                            <li><span class="dropdown-item-text text-muted small">Chưa có thông báo.</span></li>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <a class="as-user dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <span class="d-none d-lg-inline"><?= h($user->getTenDangNhap()) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text small text-muted"><?= h($user->getHoTen()) ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= h(route('yeuthich')) ?>"><i class="bi bi-heart"></i> Đội yêu thích</a></li>
                            <li><a class="dropdown-item" href="<?= h(route('lich-ca-nhan')) ?>"><i class="bi bi-calendar2-week"></i> Lịch của tôi</a></li>
                            <?php if ($user->isAdmin()): ?>
                                <li><a class="dropdown-item" href="<?= h(route('admin')) ?>"><i class="bi bi-speedometer2"></i> Quản trị</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="<?= h(route('dangxuat')) ?>" class="m-0">
                                    <?= App\Core\Csrf::field() ?>
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right"></i> Đăng xuất
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a class="as-ghost" href="<?= h(route('dangnhap')) ?>"><i class="bi bi-box-arrow-in-right"></i> Đăng nhập</a>
                    <a class="as-ghost d-none d-lg-inline-flex" href="<?= h(route('dangky')) ?>">Đăng ký</a>
                <?php endif; ?>
                <button class="as-icon as-burger" type="button" data-bs-toggle="collapse" data-bs-target="#asNav"
                        aria-controls="asNav" aria-expanded="false" aria-label="Mở menu">
                    <i class="bi bi-list"></i>
                </button>
            </div>
        </div>

        <div class="collapse d-lg-none" id="asNav">
            <div class="pb-2">
                <a class="as-mobile-link" href="<?= h(route('home')) ?>">Trang chủ</a>
                <a class="as-mobile-link" href="<?= h(route('lich')) ?>">Lịch thi đấu</a>
                <a class="as-mobile-link" href="<?= h(route('giai')) ?>">Giải đấu</a>
                <a class="as-mobile-link" href="<?= h(route('doi')) ?>">Đội bóng</a>
                <a class="as-mobile-link" href="<?= h(route('tintuc')) ?>">Tin tức</a>
                <a class="as-mobile-link<?= $current === 'san-pham' ? ' active' : '' ?>" href="<?= h(route('san-pham')) ?>" <?= $current === 'san-pham' ? 'aria-current="page"' : '' ?>>Sản phẩm</a>
                <?php if ($user): ?>
                    <a class="as-mobile-link" href="<?= h(route('yeuthich')) ?>">Đội yêu thích</a>
                    <a class="as-mobile-link" href="<?= h(route('lich-ca-nhan')) ?>">Lịch của tôi</a>
                <?php endif; ?>
                <?php if ($user && $user->isAdmin()): ?>
                    <a class="as-mobile-link" href="<?= h(route('admin')) ?>">Quản trị</a>
                <?php endif; ?>
                <?php if ($user): ?>
                    <form method="post" action="<?= h(route('dangxuat')) ?>" class="m-0">
                        <?= App\Core\Csrf::field() ?>
                        <button type="submit" class="as-mobile-link border-0 bg-transparent w-100 text-start">Đăng xuất</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
