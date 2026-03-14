<?php

/**
 * Template: Danh sách thí sinh của một kỳ sát hạch
 *
 * @package     Com_Eqa
 * @subpackage  tmpl/assessmentlearners
 * @since       2.0.5
 */

defined('_JEXEC') or die();

use Joomla\CMS\Layout\LayoutHelper;
use Kma\Library\Kma\Helper\ViewHelper;

/**
 * @var Kma\Component\Eqa\Administrator\View\AssessmentLearners\HtmlView $this
 */

$assessment = $this->assessment;
$stats      = $this->statistics;

// Helper: format số tiền VNĐ
$fmt = static fn(int $amount): string => number_format($amount, 0, ',', '.') . ' đ';

?>

<?php /* =====================================================================
   Header: thông tin kỳ sát hạch
   ===================================================================== */ ?>
<div class="card mb-3 border-primary">
    <div class="card-body py-2">
        <div class="row align-items-center">
            <div class="col">
                <h6 class="mb-0 text-primary">
                    <span class="icon-passport me-1" aria-hidden="true"></span>
                    <?php echo htmlspecialchars($assessment->title ?? ''); ?>
                </h6>
                <small class="text-muted">
                    <?php
                    echo htmlspecialchars($assessment->start_date ?? '');
                    if (!empty($assessment->end_date) && $assessment->end_date !== $assessment->start_date) {
                        echo ' — ' . htmlspecialchars($assessment->end_date);
                    }
                    ?>
                </small>
            </div>
        </div>
    </div>
</div>

<?php /* =====================================================================
   Thống kê tổng hợp
   ===================================================================== */ ?>
<?php if (!empty($stats)): ?>
<div class="row g-2 mb-3">

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-secondary h-100">
            <div class="card-body py-2 px-1">
                <div class="fs-4 fw-bold text-secondary"><?php echo (int) $stats->total; ?></div>
                <div class="small text-muted">Tổng thí sinh</div>
            </div>
        </div>
    </div>

    <?php if ((int) $stats->hasFee > 0): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-warning h-100">
            <div class="card-body py-2 px-1">
                <div class="fs-4 fw-bold text-warning"><?php echo (int) $stats->unpaid; ?></div>
                <div class="small text-muted">Chưa nộp phí</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-success h-100">
            <div class="card-body py-2 px-1">
                <div class="fs-4 fw-bold text-success"><?php echo (int) $stats->paid; ?></div>
                <div class="small text-muted">Đã nộp phí</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-info h-100">
            <div class="card-body py-2 px-1">
                <div class="fs-5 fw-bold text-info"><?php echo $fmt((int) $stats->collectedAmount); ?></div>
                <div class="small text-muted">Đã thu / <?php echo $fmt((int) $stats->totalFeeAmount); ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ((int) ($stats->passed + $stats->failed) > 0): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-success h-100">
            <div class="card-body py-2 px-1">
                <div class="fs-4 fw-bold text-success"><?php echo (int) $stats->passed; ?></div>
                <div class="small text-muted">Đạt</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-danger h-100">
            <div class="card-body py-2 px-1">
                <div class="fs-4 fw-bold text-danger"><?php echo (int) $stats->failed; ?></div>
                <div class="small text-muted">Không đạt</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ((int) $stats->notYet > 0): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-secondary h-100">
            <div class="card-body py-2 px-1">
                <div class="fs-4 fw-bold text-secondary"><?php echo (int) $stats->notYet; ?></div>
                <div class="small text-muted">Chưa có kết quả</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
<?php endif; ?>


<?php /* =====================================================================
   Bảng danh sách thí sinh
   ===================================================================== */ ?>
<?php ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields); ?>
