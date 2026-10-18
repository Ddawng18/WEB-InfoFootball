<?php
/**
 * Layout chung (shell) cho mọi trang HTML.
 * Thứ tự: <head> → navbar → <main> → footer → script.
 * Biến: $content (bắt buộc), $title, $user (\App\Models\User|null)
 */
$user = $user ?? null;
$tieuDe = ($title ?? '') !== '' ? $title . ' · FastFootBalInfo' : 'FastFootBalInfo';
?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="FastFootBalInfo - lịch thi đấu, kết quả, giải đấu, đội bóng và tin tức bóng đá.">
    <meta name="csrf-token" content="<?= h(App\Core\Csrf::token()) ?>">
    <title><?= h($tieuDe) ?></title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
    <link href="<?= h(asset('style.css')) ?>" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100 bg-body-tertiary">

<a class="as-skip" href="#main">Bỏ qua tới nội dung</a>

<?php require APP_ROOT . '/views/partials/navbar.php'; ?>

<main class="flex-grow-1" id="main">
    <div class="container as-wrap">
        <?= $content ?>
    </div>
</main>

<?php require APP_ROOT . '/views/partials/footer.php'; ?>

<a href="#" class="as-totop" aria-label="Lên đầu trang"><i class="bi bi-arrow-up"></i></a>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.FFBI = {
        base: "<?= h(App\Core\Url::base()) ?>",
        loggedIn: <?= $user ? 'true' : 'false' ?>,
        apiMatches: "<?= h(api('matches.php')) ?>",
        apiMatch: "<?= h(api('match.php')) ?>",
        apiFavorite: "<?= h(api('favorite.php')) ?>",
        apiSync: "<?= h(api('admin/sync.php')) ?>",
        csrf: "<?= h(App\Core\Csrf::token()) ?>",
        chuKyGiay: 30
    };
</script>
<script src="<?= h(asset('app.js')) ?>"></script>
</body>
</html>
