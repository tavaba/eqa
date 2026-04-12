<?php

/**
 * Sub-template: Lịch sử chiến dịch email cho môn thi
 *
 * File: tmpl/examexaminees/default_campaign_history.php
 *
 * Được kích hoạt bởi lệnh gọi ở cuối tmpl/examexaminees/default.php:
 *   echo $this->loadTemplate('campaign_history');
 *
 * Trong Joomla, khi loadTemplate() được gọi, $this trong sub-template
 * chính là instance của HtmlView — do đó truy cập được tất cả property
 * đã được gán trong View, bao gồm:
 *   $this->campaignHistory  → mảng campaign (đã preprocessing)
 *   $this->exam             → thông tin môn thi hiện tại
 *
 * @package Kma\Component\Eqa\Administrator\View\ExamExaminees
 * @since   2.0.9
 */

defined('_JEXEC') or die();

use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Enum\MailContextType;

// Lấy dữ liệu từ HtmlView instance ($this)
$campaigns = $this->campaignHistory;                // gán bởi loadCampaignHistory() trong View
$examId    = (int) ($this->exam->id ?? 0);

// URL xem toàn bộ campaign của môn thi này trong view mailcampaigns
$allCampaignsUrl = Route::_(
    'index.php?option=com_eqa&view=mailcampaigns'
    . '&filter[context_type]=' . MailContextType::Exam->value
    . '&filter[context_id]='   . $examId,
    false
);
?>

<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <span class="icon-envelope me-1" aria-hidden="true"></span>
            Lịch sử thông báo email
            <?php if (!empty($campaigns)) : ?>
                <span class="badge bg-secondary ms-1"><?= count($campaigns) ?></span>
            <?php endif; ?>
        </h6>
        <a href="<?= $allCampaignsUrl ?>" class="btn btn-sm btn-outline-secondary">
            Xem tất cả
        </a>
    </div>

    <div class="card-body p-0">
        <?php if (empty($campaigns)) : ?>
            <p class="text-muted text-center py-3 mb-0 small">
                Chưa có chiến dịch email nào được gửi cho môn thi này.
            </p>
        <?php else : ?>
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Thời gian gửi</th>
                        <th>Template đã dùng</th>
                        <th class="text-center" style="min-width:130px;">Tiến độ</th>
                        <th class="text-center">Trạng thái</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $campaign) : ?>
                        <?php
                        $total   = (int) $campaign->total_count;
                        $sent    = (int) $campaign->sent_count;
                        $failed  = (int) $campaign->failed_count;
                        $sentPct = $total > 0 ? (int) round($sent   / $total * 100) : 0;
                        $failPct = $total > 0 ? (int) round($failed / $total * 100) : 0;

                        // URL xem delivery log chi tiết của campaign này
                        $logUrl = Route::_(
                            'index.php?option=com_eqa&view=mailcampaigns'
                            . '&layout=log&campaign_id=' . (int) $campaign->id,
                            false
                        );
                        ?>
                        <tr>
                            <td class="small text-nowrap">
                                <?= htmlspecialchars($campaign->created_at_local) ?>
                                <?php if (!empty($campaign->creator_name)) : ?>
                                    <br>
                                    <span class="text-muted">
                                        <?= htmlspecialchars($campaign->creator_name) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?= htmlspecialchars($campaign->template_title ?? '—') ?>
                            </td>
                            <td class="text-center">
                                <?php if ($total > 0) : ?>
                                    <div class="progress mb-1" style="height:10px;">
                                        <div class="progress-bar bg-success"
                                             style="width:<?= $sentPct ?>%"
                                             title="Đã gửi: <?= $sent ?>">
                                        </div>
                                        <div class="progress-bar bg-danger"
                                             style="width:<?= $failPct ?>%"
                                             title="Thất bại: <?= $failed ?>">
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <?= ($sent + $failed) ?>/<?= $total ?>
                                    </small>
                                <?php else : ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= htmlspecialchars($campaign->status_badge) ?>">
                                    <?= htmlspecialchars($campaign->status_label) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="<?= $logUrl ?>"
                                   class="btn btn-sm btn-outline-secondary">
                                    Xem log
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
