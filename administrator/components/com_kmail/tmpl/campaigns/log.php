<?php
defined('_JEXEC') or die();

/** @var \Kma\Component\Kmail\Administrator\View\Campaigns\HtmlView $this */

$campaign = $this->layoutData->item;
$items    = $this->layoutData->items ?? [];

// Tính toán progress bar
$total     = (int) ($campaign->total_count  ?? 0);
$sent      = (int) ($campaign->sent_count   ?? 0);
$failed    = (int) ($campaign->failed_count ?? 0);
$pending   = max(0, $total - $sent - $failed);
$sentPct   = $total > 0 ? (int) round($sent   / $total * 100) : 0;
$failedPct = $total > 0 ? (int) round($failed / $total * 100) : 0;
?>

<!-- =========================================================================
     Header: thông tin tóm tắt campaign
     ========================================================================= -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Thông tin chiến dịch #<?= (int) $campaign->id ?></h6>
    </div>
    <div class="card-body">
        <div class="row g-3">

            <!-- Cột trái: template, ngữ cảnh, người tạo -->
            <div class="col-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <th class="text-muted fw-normal" style="width:120px;">Template</th>
                        <td><strong><?= htmlspecialchars($campaign->template_title ?? '—') ?></strong></td>
                    </tr>
                    <tr>
                        <th class="text-muted fw-normal">Ngữ cảnh</th>
                        <td><?= htmlspecialchars($campaign->context_label ?? ('ID: ' . $campaign->context_id)) ?></td>
                    </tr>
                    <tr>
                        <th class="text-muted fw-normal">Người tạo</th>
                        <td><?= htmlspecialchars($campaign->creator_name ?? '—') ?></td>
                    </tr>
                    <tr>
                        <th class="text-muted fw-normal">Thời gian</th>
                        <td><?= htmlspecialchars($campaign->created_at_local ?? ($campaign->created_at ?? '—')) ?></td>
                    </tr>
                </table>
            </div>

            <!-- Cột phải: tiến độ, trạng thái -->
            <div class="col-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <th class="text-muted fw-normal" style="width:120px;">Trạng thái</th>
                        <td>
                            <span class="badge <?= htmlspecialchars($campaign->status_badge ?? 'bg-secondary') ?>">
                                <?= htmlspecialchars($campaign->status_label ?? '?') ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted fw-normal">Tổng số</th>
                        <td><?= $total ?> email</td>
                    </tr>
                    <tr>
                        <th class="text-muted fw-normal">Tiến độ</th>
                        <td>
                            <?php if ($total > 0) : ?>
                                <div class="progress mb-1" style="height:14px; min-width:160px;">
                                    <div class="progress-bar bg-success" style="width:<?= $sentPct ?>%"
                                         title="Đã gửi: <?= $sent ?>"></div>
                                    <div class="progress-bar bg-danger"  style="width:<?= $failedPct ?>%"
                                         title="Thất bại: <?= $failed ?>"></div>
                                </div>
                                <small class="text-muted">
                                    <span class="text-success"><?= $sent ?> thành công</span>
                                    <?php if ($failed > 0) : ?>
                                        &nbsp;·&nbsp;<span class="text-danger"><?= $failed ?> thất bại</span>
                                    <?php endif; ?>
                                    <?php if ($pending > 0) : ?>
                                        &nbsp;·&nbsp;<span class="text-secondary"><?= $pending ?> chờ gửi</span>
                                    <?php endif; ?>
                                </small>
                            <?php else : ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

        </div>
    </div>
</div>

<!-- =========================================================================
     Bảng delivery log
     ========================================================================= -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            Chi tiết gửi
            <span class="badge bg-secondary ms-1"><?= count($items) ?></span>
        </h6>
        <?php if ($total > count($items)) : ?>
            <small class="text-muted">
                Hiển thị <?= count($items) ?> / <?= $total ?> bản ghi
            </small>
        <?php endif; ?>
    </div>

    <?php if (empty($items)) : ?>
        <div class="card-body">
            <p class="text-muted text-center mb-0">Chưa có dữ liệu gửi.</p>
        </div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px;" class="text-center">#</th>
                        <th>Email người nhận</th>
                        <th class="text-center">Trạng thái</th>
                        <th class="text-center">Số lần thử</th>
                        <th class="text-center text-nowrap">Lần thử gần nhất</th>
                        <th class="text-center text-nowrap">Thời gian gửi</th>
                        <th>Lỗi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $item) : ?>
                        <tr>
                            <td class="text-center text-muted small">
                                <?= $index + 1 ?>
                            </td>
                            <td class="font-monospace small">
                                <?= htmlspecialchars($item->recipient_email ?? '—') ?>
                            </td>
                            <td class="text-center">
                                <?= $item->status_html ?>
                            </td>
                            <td class="text-center">
                                <?= (int) ($item->attempts ?? 0) ?>
                            </td>
                            <td class="text-center small text-nowrap">
                                <?= htmlspecialchars($item->last_attempt_at_local) ?>
                            </td>
                            <td class="text-center small text-nowrap">
                                <?= htmlspecialchars($item->sent_at_local) ?>
                            </td>
                            <td class="small">
                                <?= $item->error_html ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
