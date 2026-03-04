<?php

/**
 * Template mặc định cho view SecondAttempts.
 *
 * Hiển thị:
 *   1. Bảng thống kê tổng hợp (statistics)
 *   2. Danh sách thí sinh thi lần hai (qua ViewHelper::printItemsDefaultLayout)
 */

defined('_JEXEC') or die();

use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Helper\ViewHelper;

/** @var \Kma\Component\Eqa\Administrator\View\SecondAttempts\HtmlView $this */
$stats = $this->statistics;

/**
 * Định dạng số tiền VNĐ, ví dụ: 1.234.000 đ
 *
 * @param  float  $amount
 * @return string
 */
$formatMoney = static fn(float $amount): string =>
    number_format($amount, 0, ',', '.') . ' đ';
?>

<!-- =========================================================
     Thống kê tổng hợp
     ========================================================= -->
<div class="row g-2 mb-3">

    <!-- Card "Môn thi" — nhấn để chuyển sang view SecondAttemptSubjects -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?php echo Route::_('index.php?option=com_eqa&view=secondattemptsubjects', false); ?>"
           class="text-decoration-none" title="Xem danh sách theo môn thi">
            <div class="card text-center border-secondary h-100 eqa-card-link">
                <div class="card-body py-2 px-1">
                    <div class="fs-3 fw-bold text-secondary"><?php echo $stats->totalExams; ?></div>
                    <div class="small text-muted">Môn thi</div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-secondary h-100">
            <div class="card-body py-2 px-1">
                <div class="fs-3 fw-bold text-secondary"><?php echo $stats->totalLearners; ?></div>
                <div class="small text-muted">Người học</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-primary h-100">
            <div class="card-body py-2 px-1">
                <div class="fs-3 fw-bold text-primary"><?php echo $stats->totalAttempts; ?></div>
                <div class="small text-muted">Tổng số lượt</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-success h-100">
            <div class="card-body py-2 px-1">
                <div class="fs-3 fw-bold text-success"><?php echo $stats->totalFree; ?></div>
                <div class="small text-muted">Không cần nộp phí</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-warning h-100">
            <div class="card-body py-2 px-1">
                <div class="fs-3 fw-bold text-warning"><?php echo $stats->totalRequired; ?></div>
                <div class="small text-muted">Phải nộp phí</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-danger h-100">
            <div class="card-body py-2 px-1">
                <div class="fs-3 fw-bold text-danger"><?php echo $stats->totalRequired - $stats->totalPaid; ?></div>
                <div class="small text-muted">Chưa nộp phí</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-info h-100">
            <div class="card-body py-2 px-1">
                <div class="fs-3 fw-bold text-info"><?php echo $stats->totalPaid; ?></div>
                <div class="small text-muted">Đã nộp phí</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-warning h-100">
            <div class="card-body py-2 px-1">
                <div class="fs-5 fw-bold text-warning"><?php echo $formatMoney($stats->totalFeeAmount); ?></div>
                <div class="small text-muted">Tổng phí</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-success h-100">
            <div class="card-body py-2 px-1">
                <div class="fs-5 fw-bold text-success"><?php echo $formatMoney($stats->totalCollectedAmount); ?></div>
                <div class="small text-muted">Đã thu</div>
            </div>
        </div>
    </div>

</div>

<!-- =========================================================
     Danh sách
     ========================================================= -->
<?php
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
