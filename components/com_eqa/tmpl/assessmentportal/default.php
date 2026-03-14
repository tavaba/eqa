<?php

/**
 * Template: Trang Thi sát hạch (front-end)
 *
 * Hiển thị cho người học đang đăng nhập:
 *   - Nhóm "Đang/Sắp diễn ra": đăng ký, nộp phí (QR), hủy đăng ký.
 *   - Nhóm "Đã tham gia": kết quả.
 *
 * @package     Com_Eqa
 * @subpackage  tmpl/assessmentportal
 * @since       2.0.5
 */

defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\DataObject\LearnerInfo;
use Kma\Component\Eqa\Administrator\Enum\AssessmentResultLevel;
use Kma\Component\Eqa\Administrator\Enum\AssessmentResultType;
use Kma\Component\Eqa\Site\Model\AssessmentPortalModel;
use Kma\Library\Kma\Helper\DatetimeHelper;

HTMLHelper::_('bootstrap.framework');

/**
 * @var Kma\Component\Eqa\Site\View\AssessmentPortal\HtmlView $this
 */

// ─── Chưa đăng nhập / không phải HVSV ──────────────────────────────────────
if ($this->learnerCode === null): ?>
    <div class="alert alert-warning">
        <span class="icon-lock me-1" aria-hidden="true"></span>
        Bạn cần <strong>đăng nhập bằng tài khoản HVSV</strong> để xem nội dung trang này.
    </div>
	<?php return; endif;

// ─── Lỗi nghiệp vụ ──────────────────────────────────────────────────────────
if ($this->errorMessage !== null): ?>
    <div class="alert alert-danger">
		<?php echo htmlspecialchars($this->errorMessage); ?>
    </div>
	<?php return; endif;

// ─── Helpers ────────────────────────────────────────────────────────────────
/**
 * Render nhãn kết quả cho kỳ sát hạch đã tham gia.
 */
$renderResult = static function (object $a): string {
	$resultTypeEnum = AssessmentResultType::tryFrom((int) $a->result_type);
	$reg            = $a->registrationRecord;

	if ($reg === null || $reg->passed === null) {
		return '<span class="badge bg-secondary">Chưa có kết quả</span>';
	}

	$parts = [];

	if (in_array($resultTypeEnum, [AssessmentResultType::Score, AssessmentResultType::ScoreAndLevel], true)
		&& $reg->score !== null) {
		$parts[] = 'Điểm: <strong>' . number_format((float) $reg->score, 2) . '</strong>';
	}

	if (in_array($resultTypeEnum, [AssessmentResultType::Level, AssessmentResultType::ScoreAndLevel], true)
		&& $reg->level !== null) {
		$levelEnum = AssessmentResultLevel::tryFrom((int) $reg->level);
		$parts[]   = 'Bậc: <strong>' . ($levelEnum?->getLabel() ?? '—') . '</strong>';
	}

	$passedBadge = $reg->passed
		? '<span class="badge bg-success">Đạt</span>'
		: '<span class="badge bg-danger">Không đạt</span>';

	return ($parts ? implode(' &nbsp; ', $parts) . ' &nbsp; ' : '') . $passedBadge;
};

/**
 * Format ngày giờ hiển thị cho người học.
 */
$fmtDt = static fn(?string $utcDt): string =>
$utcDt ? DatetimeHelper::fromUtc($utcDt, null, 'd/m/Y H:i') : '—';
$fmtD  = static fn(?string $d): string  =>
$d ? date('d/m/Y', strtotime($d)) : '—';

$portalUrl = Route::_('index.php?option=com_eqa&view=assessmentportal', false);

/** @var LearnerInfo $learner */
$learner = $this->learner;
?>

    <!-- ═══════════════════════════════════════════════════════
		 Header: thông tin người học
		 ═══════════════════════════════════════════════════════ -->
    <div class="alert alert-light border mb-4 py-2">
        <span class="icon-user me-1" aria-hidden="true"></span>
        Xin chào <strong><?php echo htmlspecialchars($learner->getFullName() ?? ''); ?></strong>
        &nbsp;·&nbsp; Mã HVSV: <code><?php echo htmlspecialchars($this->learnerCode); ?></code>
    </div>

<?php
// ─── Flash messages từ Controller ───────────────────────────────────────────
$app = \Joomla\CMS\Factory::getApplication();
foreach ($app->getMessageQueue(true) as $msg): ?>
    <div class="alert alert-<?php echo $msg['type'] === 'error' ? 'danger' : htmlspecialchars($msg['type']); ?> alert-dismissible fade show" role="alert">
		<?php echo $msg['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>

<?php /* ═══════════════════════════════════════════════════════
   Nhóm: ĐANG / SẮP DIỄN RA
   ═══════════════════════════════════════════════════════ */ ?>

    <h5 class="mb-3">
        <span class="icon-calendar me-1 text-primary" aria-hidden="true"></span>
        Đang / Sắp diễn ra
    </h5>

<?php if (empty($this->activeAssessments)): ?>
    <p class="text-muted fst-italic mb-4">Không có kỳ sát hạch nào đang diễn ra hoặc sắp diễn ra.</p>
<?php else: ?>

	<?php // Lưu ý chung về cập nhật phí — chỉ hiện khi có ít nhất 1 kỳ cần nộp phí
	$hasFeePending = false;
	foreach ($this->activeAssessments as $a) {
		if (in_array($a->registrationStatus, [
				AssessmentPortalModel::STATUS_REGISTERED,
				AssessmentPortalModel::STATUS_PAID,
			], true) && (int) $a->fee > 0) {
			$hasFeePending = true;
			break;
		}
	}
	if ($hasFeePending): ?>
        <div class="alert alert-info mb-3 py-2 small">
            <span class="icon-info me-1" aria-hidden="true"></span>
            <strong>Lưu ý:</strong> Trạng thái nộp phí được cán bộ tổ chức thi cập nhật thủ công sau khi đối chiếu bản sao kê.
            Sau khi chuyển khoản, vui lòng kiểm tra lại trạng thái sau <strong>1–2 ngày làm việc</strong>.
        </div>
	<?php endif; ?>

    <div class="row g-3 mb-4">
		<?php foreach ($this->activeAssessments as $a):
			$status = $a->registrationStatus;
			$reg    = $a->registrationRecord;
			$isFree = ((int) $a->fee === 0);
			$feeLabel = $isFree
				? '<span class="badge bg-secondary">Miễn phí</span>'
				: '<span class="badge bg-warning text-dark">' . number_format((int) $a->fee, 0, ',', '.') . ' đ</span>';
			?>
            <div class="col-12 col-md-6">
                <div class="card h-100 <?php echo $status === AssessmentPortalModel::STATUS_PAID ? 'border-success' : ''; ?>">
                    <div class="card-body">

                        <!-- Tên kỳ sát hạch -->
                        <h6 class="card-title mb-2"><?php echo htmlspecialchars($a->title); ?></h6>

                        <!-- Thông tin ngày tháng -->
                        <dl class="row small mb-2 g-0">
                            <dt class="col-5 text-muted fw-normal">Ngày thi:</dt>
                            <dd class="col-7 mb-1">
								<?php
								echo $fmtD($a->start_date);
								if ($a->end_date !== $a->start_date) {
									echo ' — ' . $fmtD($a->end_date);
								}
								?>
                            </dd>
							<?php if (!empty($a->registration_start)): ?>
                                <dt class="col-5 text-muted fw-normal">Bắt đầu ĐK:</dt>
                                <dd class="col-7 mb-1"><?php echo $fmtDt($a->registration_start); ?></dd>
							<?php endif; ?>
							<?php if (!empty($a->registration_end)): ?>
                                <dt class="col-5 text-muted fw-normal">Kết thúc ĐK:</dt>
                                <dd class="col-7 mb-1"><?php echo $fmtDt($a->registration_end); ?></dd>
							<?php endif; ?>
                        </dl>

                        <!-- Phí & Slot -->
                        <div class="mb-2">
							<?php echo $feeLabel; ?>
							<?php if ($a->availableSlots !== null): ?>
                                &nbsp;
								<?php if ($a->availableSlots > 0): ?>
                                    <span class="badge bg-info text-dark">Còn <?php echo $a->availableSlots; ?> chỗ</span>
								<?php else: ?>
                                    <span class="badge bg-danger">Hết chỗ</span>
								<?php endif; ?>
							<?php endif; ?>
                        </div>

                        <!-- Thời điểm cập nhật phí gần nhất -->
						<?php if (!$isFree && !empty($a->lastPaymentUpdate)): ?>
                            <div class="text-muted small mb-2">
                                <span class="icon-clock me-1" aria-hidden="true"></span>
                                Cập nhật phí lần cuối: <?php echo $fmtDt($a->lastPaymentUpdate); ?>
                            </div>
						<?php endif; ?>

                        <!-- Thời điểm đăng ký (nếu đã đăng ký) -->
						<?php if ($reg !== null && !(bool) $reg->cancelled && !empty($reg->created_at)): ?>
                            <div class="text-muted small mb-2">
                                <span class="icon-calendar me-1" aria-hidden="true"></span>
                                Đã đăng ký lúc: <?php echo $fmtDt($reg->created_at); ?>
                            </div>
						<?php endif; ?>

                        <!-- ── Trạng thái & Nút tương tác ── -->
                        <div class="mt-3">

							<?php if ($status === AssessmentPortalModel::STATUS_PAID): ?>
                                <span class="badge bg-success fs-6">
                            <span class="icon-check me-1" aria-hidden="true"></span> Đã nộp phí
                        </span>

							<?php elseif ($status === AssessmentPortalModel::STATUS_REGISTERED): ?>
                                <!-- Đã đăng ký, chưa nộp phí, còn slot và còn trong hạn -->
                                <span class="badge bg-warning text-dark mb-2">Đã đăng ký – chờ nộp phí</span>
                                <div class="d-flex gap-2 flex-wrap">
                                    <!-- Nút Nộp phí → mở modal QR -->
                                    <button type="button"
                                            class="btn btn-sm btn-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalQR_<?php echo $a->id; ?>">
                                        <span class="icon-qrcode me-1" aria-hidden="true"></span> Nộp phí
                                    </button>
                                    <!-- Nút Hủy đăng ký → mở modal xác nhận -->
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalCancel_<?php echo $a->id; ?>">
                                        <span class="icon-times me-1" aria-hidden="true"></span> Hủy đăng ký
                                    </button>
                                </div>

							<?php elseif ($status === AssessmentPortalModel::STATUS_PENDING): ?>
                                <!-- Đã đăng ký, chưa nộp phí, nhưng hết hạn hoặc tắt đăng ký -->
                                <span class="badge bg-secondary">Đã đăng ký – chờ xác nhận</span>

							<?php elseif ($status === AssessmentPortalModel::STATUS_OPEN): ?>
                                <!-- Chưa đăng ký, đang trong hạn, còn slot -->
                                <button type="button"
                                        class="btn btn-sm btn-success"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalRegister_<?php echo $a->id; ?>">
                                    <span class="icon-plus me-1" aria-hidden="true"></span> Đăng ký
                                </button>

							<?php elseif ($status === AssessmentPortalModel::STATUS_NOT_YET): ?>
                                <!-- Chưa đến thời hạn đăng ký -->
                                <span class="badge bg-info">
                            <span class="icon-clock me-1" aria-hidden="true"></span>
                            Chưa đến hạn đăng ký
                            <?php if (!empty($a->registrationStartsIn)): ?>
                                (còn <?php echo htmlspecialchars($a->registrationStartsIn); ?>)
                            <?php endif; ?>
                        </span>

							<?php elseif ($status === AssessmentPortalModel::STATUS_SUSPENDED): ?>
                                <!-- Đang trong hạn nhưng chế độ đăng ký bị tắt -->
                                <span class="badge bg-warning">
                            <span class="icon-pause me-1" aria-hidden="true"></span>
                            Việc đăng ký đang bị tạm dừng, vui lòng trở lại sau
                        </span>

							<?php elseif ($status === AssessmentPortalModel::STATUS_EXPIRED): ?>
                                <!-- Đã quá thời hạn đăng ký -->
                                <span class="badge bg-secondary">
                            Đã quá thời hạn đăng ký
                        </span>

							<?php elseif ($status === AssessmentPortalModel::STATUS_FULL): ?>
                                <!-- Hết slot -->
                                <span class="badge bg-danger">
                            Đã hết chỗ
                        </span>

							<?php endif; ?>

                        </div><!-- /nút tương tác -->
                    </div><!-- /card-body -->
                </div><!-- /card -->
            </div><!-- /col -->

			<?php /* ── Modal: Xác nhận đăng ký ── */ ?>
			<?php if ($status === AssessmentPortalModel::STATUS_OPEN): ?>
            <div class="modal fade" id="modalRegister_<?php echo $a->id; ?>"
                 tabindex="-1" aria-labelledby="modalRegisterLabel_<?php echo $a->id; ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalRegisterLabel_<?php echo $a->id; ?>">Xác nhận đăng ký</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Bạn muốn đăng ký tham dự kỳ sát hạch:</p>
                            <p class="fw-bold"><?php echo htmlspecialchars($a->title); ?></p>
							<?php if (!$isFree): ?>
                                <p>Phí tham dự: <strong><?php echo number_format((int) $a->fee, 0, ',', '.'); ?> đ</strong></p>
                                <p class="text-muted small">Sau khi đăng ký, bạn cần chuyển khoản phí theo hướng dẫn. Phí sẽ <strong>không được hoàn lại</strong> trong bất kỳ trường hợp nào.</p>
							<?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                            <form method="post" action="<?php echo $portalUrl; ?>">
                                <input type="hidden" name="task"          value="assessmentportal.register">
                                <input type="hidden" name="assessment_id" value="<?php echo (int) $a->id; ?>">
								<?php echo HTMLHelper::_('form.token'); ?>
                                <button type="submit" class="btn btn-success">Xác nhận đăng ký</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
		<?php endif; ?>

			<?php /* ── Modal: QR nộp phí ── */ ?>
			<?php if ($status === AssessmentPortalModel::STATUS_REGISTERED && !$isFree
			&& !empty($a->bank_napas_code) && !empty($a->bank_account_number)
			&& $reg !== null && !empty($reg->payment_code)): ?>
			<?php
			$transferNote = $reg->payment_code . '-' . $this->learnerCode;
			$qrUrl = sprintf(
				'https://img.vietqr.io/image/%s-%s-compact2.png?amount=%d&addInfo=%s',
				urlencode($a->bank_napas_code),
				urlencode($a->bank_account_number),
				(int) $a->fee,
				urlencode($transferNote)
			);
			?>
            <div class="modal fade" id="modalQR_<?php echo $a->id; ?>"
                 tabindex="-1" aria-labelledby="modalQRLabel_<?php echo $a->id; ?>" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalQRLabel_<?php echo $a->id; ?>">Nộp phí sát hạch</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <p class="mb-1 fw-semibold"><?php echo htmlspecialchars($a->title); ?></p>
                            <img src="<?php echo htmlspecialchars($qrUrl); ?>"
                                 alt="Mã QR nộp phí sát hạch"
                                 class="img-fluid mb-3"
                                 style="max-width: 260px;"
                            />
                            <div class="alert alert-warning py-2 small text-start">
                                <strong>Nội dung chuyển khoản:</strong>
                                <code class="d-block mt-1 fs-6"><?php echo htmlspecialchars($transferNote); ?></code>
                                <span class="text-danger">Cần giữ nguyên nội dung này, không thêm/bớt bất kỳ nội dung nào khác.</span>
                            </div>
                            <p class="text-muted small mb-0">
                                Trạng thái sẽ được cập nhật sau <strong>1–2 ngày làm việc</strong> kể từ khi chuyển khoản thành công.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                        </div>
                    </div>
                </div>
            </div>
		<?php endif; ?>

			<?php /* ── Modal: Xác nhận hủy đăng ký ── */ ?>
			<?php if ($status === AssessmentPortalModel::STATUS_REGISTERED): ?>
            <div class="modal fade" id="modalCancel_<?php echo $a->id; ?>"
                 tabindex="-1" aria-labelledby="modalCancelLabel_<?php echo $a->id; ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content border-danger">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="modalCancelLabel_<?php echo $a->id; ?>">
                                <span class="icon-warning me-1" aria-hidden="true"></span> Xác nhận hủy đăng ký
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Bạn muốn hủy đăng ký kỳ sát hạch:</p>
                            <p class="fw-bold"><?php echo htmlspecialchars($a->title); ?></p>
							<?php if (!$isFree): ?>
                                <div class="alert alert-danger py-2">
                                    <span class="icon-exclamation me-1" aria-hidden="true"></span>
                                    <strong>Cảnh báo quan trọng:</strong> Nếu bạn đã chuyển khoản phí,
                                    <strong>KHÔNG được hủy đăng ký</strong>, vì số tiền đã nộp sẽ bị mất và không được hoàn lại.
                                    Chỉ hủy nếu bạn <u>chắc chắn chưa chuyển khoản</u>.
                                </div>
							<?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Không hủy</button>
                            <form method="post" action="<?php echo $portalUrl; ?>">
                                <input type="hidden" name="task"          value="assessmentportal.cancel">
                                <input type="hidden" name="assessment_id" value="<?php echo (int) $a->id; ?>">
								<?php echo HTMLHelper::_('form.token'); ?>
                                <button type="submit" class="btn btn-danger">Xác nhận hủy đăng ký</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
		<?php endif; ?>

		<?php endforeach; ?>
    </div><!-- /row active assessments -->
<?php endif; ?>


<?php /* ═══════════════════════════════════════════════════════
   Nhóm: ĐÃ THAM GIA
   ═══════════════════════════════════════════════════════ */ ?>

    <h5 class="mb-3 mt-2">
        <span class="icon-history me-1 text-secondary" aria-hidden="true"></span>
        Đã tham gia
    </h5>

<?php if (empty($this->pastAssessments)): ?>
    <p class="text-muted fst-italic">Bạn chưa tham gia kỳ sát hạch nào.</p>
<?php else: ?>
    <div class="row g-3">
		<?php foreach ($this->pastAssessments as $a):
			$reg = $a->registrationRecord;
			?>
            <div class="col-12 col-md-6">
                <div class="card h-100 bg-light">
                    <div class="card-body">
                        <h6 class="card-title mb-2"><?php echo htmlspecialchars($a->title); ?></h6>
                        <dl class="row small mb-2 g-0">
                            <dt class="col-5 text-muted fw-normal">Ngày thi:</dt>
                            <dd class="col-7 mb-0">
								<?php
								echo $fmtD($a->start_date);
								if ($a->end_date !== $a->start_date) {
									echo ' — ' . $fmtD($a->end_date);
								}
								?>
                            </dd>
                        </dl>
                        <div><?php echo $renderResult($a); ?></div>
						<?php if ($reg !== null && !empty($reg->note)): ?>
                            <p class="text-muted small mt-2 mb-0">
                                <em><?php echo htmlspecialchars($reg->note); ?></em>
                            </p>
						<?php endif; ?>
                    </div>
                </div>
            </div>
		<?php endforeach; ?>
    </div>
<?php endif; ?>