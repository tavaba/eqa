<?php

/**
 * Template layout "setpayment" cho view SecondAttempts.
 *
 * Hiển thị thông tin thí sinh (read-only) và form cập nhật trạng thái
 * nộp phí thi lần hai, sử dụng ViewHelper::printForm() theo chuẩn pattern.
 *
 * Luồng: POST 1 (setPaymentStatus) → REDIRECT → layout này → POST 2 (savePaymentStatus)
 */

defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;

/** @var \Kma\Component\Eqa\Administrator\View\SecondAttempts\HtmlView $this */

$item = $this->item;

// Định dạng số tiền lệ phí để hiển thị
$feeLabel = (float) $item->payment_amount > 0
    ? number_format((float) $item->payment_amount, 0, ',', '.') . ' đ'
    : 'Miễn phí';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-8">

        <!-- Thông tin thí sinh (read-only) -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <span class="icon-info-circle me-1" aria-hidden="true"></span>
                    Thông tin thí sinh
                </h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0">

                    <dt class="col-5 col-sm-4 text-muted fw-normal">Mã HVSV</dt>
                    <dd class="col-7 col-sm-8 fw-semibold font-monospace">
                        <?php echo htmlspecialchars($item->learner_code ?? '—'); ?>
                    </dd>

                    <dt class="col-5 col-sm-4 text-muted fw-normal">Họ tên</dt>
                    <dd class="col-7 col-sm-8">
                        <?php echo htmlspecialchars(
                            trim(($item->learner_lastname ?? '') . ' ' . ($item->learner_firstname ?? ''))
                            ?: '—'
                        ); ?>
                    </dd>

                    <dt class="col-5 col-sm-4 text-muted fw-normal">Môn thi</dt>
                    <dd class="col-7 col-sm-8">
                        <?php if (!empty($item->subject_code)): ?>
                            <span class="font-monospace me-1">
                                <?php echo htmlspecialchars($item->subject_code); ?>
                            </span>
                            — <?php echo htmlspecialchars($item->subject_name ?? ''); ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd>

                    <dt class="col-5 col-sm-4 text-muted fw-normal">Lệ phí</dt>
                    <dd class="col-7 col-sm-8">
                        <span class="badge <?php echo (float) $item->payment_amount > 0 ? 'bg-warning text-dark' : 'bg-secondary'; ?>">
                            <?php echo $feeLabel; ?>
                        </span>
                    </dd>

                    <dt class="col-5 col-sm-4 text-muted fw-normal mb-0">Trạng thái hiện tại</dt>
                    <dd class="col-7 col-sm-8 mb-0">
                        <?php if ((float) $item->payment_amount <= 0): ?>
                            <span class="text-muted fst-italic">Không áp dụng</span>
                        <?php elseif ($item->payment_completed): ?>
                            <span class="badge bg-success">Đã nộp phí</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Chưa nộp phí</span>
                        <?php endif; ?>
                    </dd>

                </dl>
            </div>
        </div>

        <!-- Form cập nhật — dùng ViewHelper::printForm() theo chuẩn pattern -->
        <?php
        ViewHelper::printForm(
            $this->form,
            'setpaymentstatus',
            ['task' => 'secondattempts.savePaymentStatus']
        );
        ?>

    </div>
</div>
