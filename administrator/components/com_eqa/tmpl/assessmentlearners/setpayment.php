<?php

/**
 * Template: Cập nhật thông tin thanh toán thí sinh sát hạch
 *
 * Luồng: POST 1 (setPaymentInfo) → REDIRECT → layout này → POST 2 (savePaymentInfo)
 *
 * @package     Com_Eqa
 * @subpackage  tmpl/assessmentlearners
 * @since       2.0.5
 */

defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;

/**
 * @var Kma\Component\Eqa\Administrator\View\AssessmentLearners\HtmlView $this
 */

$item     = $this->item;
$feeLabel = (int) $item->payment_amount > 0
    ? number_format((int) $item->payment_amount, 0, ',', '.') . ' đ'
    : 'Miễn phí';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8 col-xl-6">

        <!-- Thông tin thí sinh (read-only) -->
        <div class="card mb-4">
            <div class="card-header">
                <span class="icon-info-circle me-1" aria-hidden="true"></span>
                Thông tin thí sinh
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Mã HVSV</dt>
                    <dd class="col-7 fw-semibold font-monospace">
                        <?php echo htmlspecialchars($item->learner_code ?? '—'); ?>
                    </dd>

                    <dt class="col-5 text-muted fw-normal">Họ tên</dt>
                    <dd class="col-7">
                        <?php echo htmlspecialchars(
                            trim(($item->learner_lastname ?? '') . ' ' . ($item->learner_firstname ?? '')) ?: '—'
                        ); ?>
                    </dd>

                    <dt class="col-5 text-muted fw-normal">Mã nộp tiền</dt>
                    <dd class="col-7 font-monospace">
                        <?php echo htmlspecialchars($item->payment_code ?? '—'); ?>
                    </dd>

                    <dt class="col-5 text-muted fw-normal">Trạng thái hiện tại</dt>
                    <dd class="col-7">
                        <?php if ($item->payment_completed): ?>
                            <span class="badge bg-success">Đã nộp phí</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Chưa nộp phí</span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>

        <!-- Form cập nhật -->
        <?php
        ViewHelper::printForm(
            $this->form,
            'setassessmentpayment',
            [
                'task'          => 'assessmentlearners.savePaymentInfo',
                'assessment_id' => (int) $item->assessment_id,
            ]
        );
        ?>

    </div>
</div>
