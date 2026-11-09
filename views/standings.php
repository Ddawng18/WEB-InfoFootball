<?php
/** @var array<int, array<string, mixed>> $rows @var int $giai_id */
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= h(route('giai')) ?>">Giải đấu</a></li>
        <li class="breadcrumb-item active" aria-current="page">Bảng xếp hạng</li>
    </ol>
</nav>

<h1 class="h3 mb-3"><i class="bi bi-list-ol text-primary"></i> Bảng xếp hạng</h1>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
            <tr>
                <th class="text-center">#</th>
                <th>Đội bóng</th>
                <th class="text-center">Trận</th>
                <th class="text-center">T</th>
                <th class="text-center">H</th>
                <th class="text-center">B</th>
                <th class="text-center">BT</th>
                <th class="text-center">BB</th>
                <th class="text-center">Điểm</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="text-center fw-semibold"><?= (int) ($row['hang'] ?? 0) ?></td>
                    <td>
                        <a class="text-decoration-none fw-semibold"
                           href="<?= h(route('doi-chitiet', ['id' => (int) ($row['doi_bong_id'] ?? 0)])) ?>">
                            <?= h((string) ($row['doi_ten'] ?? '')) ?>
                        </a>
                    </td>
                    <td class="text-center"><?= (int) ($row['so_tran'] ?? 0) ?></td>
                    <td class="text-center text-success"><?= (int) ($row['thang'] ?? 0) ?></td>
                    <td class="text-center text-muted"><?= (int) ($row['hoa'] ?? 0) ?></td>
                    <td class="text-center text-danger"><?= (int) ($row['thua'] ?? 0) ?></td>
                    <td class="text-center"><?= (int) ($row['ban_thang'] ?? 0) ?></td>
                    <td class="text-center"><?= (int) ($row['ban_thua'] ?? 0) ?></td>
                    <td class="text-center fw-bold"><?= (int) ($row['diem'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
