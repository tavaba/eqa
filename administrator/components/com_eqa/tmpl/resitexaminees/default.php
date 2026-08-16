<?php

/**
 * Template mặc định cho view ResitExaminees.
 *
 * Hiển thị:
 *   1. Bảng thống kê tổng hợp (statistics)
 *   2. Danh sách thí sinh thi lần hai (qua ViewHelper::printItemsDefaultLayout)
 */

defined('_JEXEC') or die();

use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Helper\ViewHelper;

/** @var \Kma\Component\Eqa\Administrator\View\ResitExaminees\HtmlView $this */
$stats  = $this->statistics;
$resitId = (int) $this->resitId;
$resit  = $this->resit;

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
     Danh sách thi lần 2 đang mở
     ========================================================= -->
<div class="alert <?php echo $this->isModifiableResit ? 'alert-success' : 'alert-secondary'; ?> py-2">
    <span class="icon-list me-1" aria-hidden="true"></span>
    Danh sách: <strong><?php echo htmlspecialchars($resit->name ?? ''); ?></strong>

    <?php if (!empty($resit->active)): ?>
        <span class="badge bg-success ms-2">Đang kích hoạt</span>
    <?php else: ?>
        <span class="badge bg-secondary ms-2">Không kích hoạt</span>
    <?php endif; ?>

    <?php if ((int) ($resit->state ?? 0) !== 1): ?>
        <span class="badge bg-warning text-dark ms-1">Tạm ngừng</span>
    <?php endif; ?>

    <?php if (!$this->isModifiableResit): ?>
        <span class="ms-2 small">
            Danh sách chỉ có thể xem và xuất dữ liệu; mọi chức năng làm thay đổi danh sách thí sinh
            đã được ẩn đi. Chỉ danh sách vừa <strong>đang kích hoạt</strong> vừa <strong>đang
            dùng</strong> mới được chỉnh sửa — hãy xử lý ở màn hình <em>Danh sách thi lần 2</em>.
        </span>
    <?php endif; ?>
</div>

<!-- =========================================================
     Thống kê tổng hợp
     ========================================================= -->
<div class="row g-2 mb-3">

    <!-- Card "Môn thi" — nhấn để chuyển sang view ResitSubjects -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?php echo Route::_('index.php?option=com_eqa&view=ResitSubjects&resit_id=' . $resitId, false); ?>"
           class="text-decoration-none" title="Xem danh sách theo môn thi">
            <div class="card text-center border-secondary h-100 eqa-card-link">
                <div class="card-body py-2 px-1">
                    <div class="fs-3 fw-bold text-secondary"><?php echo $stats->totalExams; ?></div>
                    <div class="small text-muted">Môn thi</div>
                </div>
            </div>
        </a>
    </div>

    <!-- Card "Người học" — nhấn để chuyển sang view ResitLearners -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?php echo Route::_('index.php?option=com_eqa&view=ResitLearners&resit_id=' . $resitId, false); ?>"
           class="text-decoration-none" title="Xem danh sách theo HVSV">
            <div class="card text-center border-secondary h-100 eqa-card-link">
                <div class="card-body py-2 px-1">
                    <div class="fs-3 fw-bold text-secondary"><?php echo $stats->totalLearners; ?></div>
                    <div class="small text-muted">HVSV</div>
                </div>
            </div>
        </a>
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
