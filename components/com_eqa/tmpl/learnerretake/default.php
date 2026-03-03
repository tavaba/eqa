<?php

/**
 * Template mặc định cho view Learnerretake (front-end).
 *
 * NGUYÊN TẮC QUAN TRỌNG VỀ MODAL:
 *   Tất cả <div class="modal"> phải được render SAU thẻ đóng </table>,
 *   TUYỆT ĐỐI KHÔNG đặt bên trong <td>. Đặt <div> trong <td> là HTML
 *   không hợp lệ — trình duyệt tự đẩy chúng ra ngoài <table> khi parse,
 *   làm sai lệch id trong DOM và Bootstrap không tìm được target khi click.
 *
 * @since 2.2.0
 */

defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Site\View\Learnerretake\HtmlView;

// Đảm bảo Bootstrap JS được load (cần thiết cho modal data-bs-toggle)
HTMLHelper::_('bootstrap.framework');

/** @var HtmlView $this */

// ─── Trường hợp 1: Không phải người học ───────────────────────────────────────
if ($this->learnerCode === null): ?>
    <div class="alert alert-warning">
        <span class="icon-warning me-2" aria-hidden="true"></span>
        Chức năng này chỉ dành cho sinh viên. Vui lòng đăng nhập bằng tài khoản sinh viên.
    </div>
<?php return; endif;

// ─── Trường hợp 2: Lỗi kỹ thuật ──────────────────────────────────────────────
if (!empty($this->errorMessage)): ?>
    <div class="alert alert-danger">
        <span class="icon-warning me-2" aria-hidden="true"></span>
        <?php echo htmlspecialchars($this->errorMessage); ?>
    </div>
<?php return; endif;

// ─── Trường hợp 3: Không có môn thi lại ───────────────────────────────────────
if (empty($this->items)): ?>
    <div class="alert alert-success">
        <span class="icon-check me-2" aria-hidden="true"></span>
        Bạn không có môn học nào cần thi lại.
    </div>
<?php return; endif;

// ─── Trường hợp 4: Có môn thi lại ─────────────────────────────────────────────
$learnerFullname = htmlspecialchars(trim($this->learner->lastname . ' ' . $this->learner->firstname));
$learnerCode     = htmlspecialchars($this->learnerCode);
$bankNapasCode   = htmlspecialchars($this->bankNapasCode);
$bankAccount     = htmlspecialchars($this->bankAccount);

// Tính toán dữ liệu QR trước — dùng chung cho cả bảng lẫn vòng lặp modal
$itemsData = [];
foreach ($this->items as $index => $item) {
    $feeAmount          = (int) round($item->feeAmount);
    $paymentDescription = rawurlencode($item->payment_code . '-' . $this->learnerCode);
    $qrUrl = sprintf(
        'https://img.vietqr.io/image/%s-%s-compact2.png?amount=%d&addInfo=%s',
        $bankNapasCode,
        $bankAccount,
        $feeAmount,
        $paymentDescription
    );
    $itemsData[] = [
        'item'    => $item,
        'modalId' => 'qr-modal-' . (int) $item->id,
        'qrUrl'   => $qrUrl,
    ];
}

// Định dạng deadline để hiển thị (dd/mm/yyyy hh:mm)
$deadlineDisplay = null;
if ($this->deadlineLocal !== null) {
    try {
        $dt              = new DateTime($this->deadlineLocal);
        $deadlineDisplay = $dt->format('H:i, d/m/Y');
    } catch (Exception $e) {
        $deadlineDisplay = htmlspecialchars($this->deadlineLocal);
    }
}
?>

<!-- Tiêu đề -->
<h4 class="mb-3">
    Danh sách môn thi lại của <strong><?php echo $learnerFullname; ?></strong>
    (<?php echo $learnerCode; ?>)
</h4>

<!-- Lưu ý quan trọng về nghĩa vụ học phí — luôn hiển thị -->
<div class="alert alert-warning mb-3">
    <span class="icon-warning me-2" aria-hidden="true"></span>
    <strong>Lưu ý quan trọng:</strong>
    HVSV phải hoàn thành nghĩa vụ học phí của môn học trước khi nộp phí thi lại.
    Nếu còn nợ học phí môn học thì HVSV sẽ không được thi lại.
</div>

<!-- ─── Thông báo về deadline ─────────────────────────────────────────────────
     Hiển thị khi menu item có cấu hình deadline.
     - Chưa quá hạn: alert-info, nhắc nhở thời hạn còn lại.
     - Đã quá hạn  : alert-danger, cảnh báo rõ ràng kèm thời điểm hết hạn.
     ─────────────────────────────────────────────────────────────────────────── -->
<?php if ($deadlineDisplay !== null): ?>
    <?php if ($this->isDeadlinePassed): ?>
        <div class="alert alert-danger mb-3">
            <span class="icon-warning me-2" aria-hidden="true"></span>
            <strong>Đã hết hạn nộp phí.</strong>
            Thời hạn nộp phí thi lại đã kết thúc lúc
            <strong><?php echo $deadlineDisplay; ?></strong>.
            Vui lòng liên hệ Phòng KT&amp;ĐBCLĐT để được hỗ trợ.
        </div>
    <?php else: ?>
        <div class="alert alert-warning mb-3">
            <span class="icon-clock me-2" aria-hidden="true"></span>
            <strong>Lưu ý thời hạn nộp phí:</strong>
            Hạn chót nộp phí thi lại là <strong><?php echo $deadlineDisplay; ?></strong>.
            Sau thời hạn này, bạn sẽ không thể nộp phí qua hệ thống.
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Ghi chú nộp phí — chỉ hiển thị khi còn trong hạn (hoặc không có deadline) -->
<?php if (!$this->isDeadlinePassed): ?>
<div class="alert alert-info mb-3">
    <span class="icon-info me-1" aria-hidden="true"></span>
    HVSV nộp phí thi lại theo từng môn. Để nộp phí thi lại, HVSV nhấn chuột vào nút
    <strong>"Nộp phí"</strong> ở môn tương ứng. Hiện nay, kết quả nộp phí chưa được ghi
    nhận tự động theo thời gian thực, mà cán bộ Học viện sẽ duyệt thủ công. HVSV vui
    lòng kiểm tra lại trạng thái nộp phí sau <strong>1–2 ngày</strong>.
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     BẢNG — không chứa modal bên trong
     ═══════════════════════════════════════ -->
<div class="table-responsive">
    <table class="table table-bordered table-hover table-sm align-middle">
        <thead class="table-dark">
            <tr>
                <th class="text-center" style="width:3%">#</th>
                <th class="text-center" style="width:8%">Mã môn</th>
                <th>Tên môn</th>
                <th class="text-center" style="width:5%">Số TC</th>
                <th class="text-center" style="width:5%">TP1</th>
                <th class="text-center" style="width:5%">TP2</th>
                <th class="text-center" style="width:5%">ĐQT</th>
                <th class="text-center" style="width:7%">Điểm thi</th>
                <th class="text-center" style="width:7%">Điểm HP</th>
                <th class="text-center" style="width:8%">Kết luận</th>
                <th class="text-center" style="width:10%">Lệ phí</th>
                <th class="text-center" style="width:10%">Nộp phí</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($itemsData as $i => $data):
            $item   = $data['item'];
            $modalId = $data['modalId'];
            $hasFee  = (float) $item->payment_amount > 0;

	        // Xác định trạng thái cột "Nộp phí" cho dòng này.
            // Thứ tự ưu tiên kiểm tra:
            //   1. Miễn phí hoặc đã nộp               → done
            //   2. Đã quá deadline                     → overdue
            //   3. Chưa đến thời điểm bắt đầu         → not_started
            //   4. Cổng thu phí đang tạm dừng         → suspended
            //   5. Tất cả điều kiện thỏa mãn          → pay
	        if (!$hasFee || $item->payment_completed) {
		        $paymentCellType = 'done';
	        } elseif ($this->isDeadlinePassed) {
		        $paymentCellType = 'overdue';
	        } elseif ($this->isBeforeOpenFrom) {
		        $paymentCellType = 'not_started';
	        } elseif (!$this->paymentGateOpen) {
		        $paymentCellType = 'suspended';
	        } else {
		        $paymentCellType = 'pay';
	        }
        ?>
            <tr>
                <td class="text-center"><?php echo $i + 1; ?></td>

                <td class="text-center font-monospace">
                    <?php echo htmlspecialchars($item->subject_code ?? ''); ?>
                </td>

                <td><?php echo htmlspecialchars($item->subject_name ?? ''); ?></td>

                <td class="text-center"><?php echo $item->credits ?? '—'; ?></td>

                <td class="text-center">
                    <?php echo $item->pam1 !== null ? $item->pam1 : '—'; ?>
                </td>

                <td class="text-center">
                    <?php echo $item->pam2 !== null ? $item->pam2 : '—'; ?>
                </td>

                <td class="text-center">
                    <?php echo $item->pam !== null ? $item->pam : '—'; ?>
                </td>

                <td class="text-center">
                    <?php echo $item->mark_orig !== null ? $item->mark_orig : '—'; ?>
                </td>

                <td class="text-center">
                    <?php echo $item->module_mark !== null ? $item->module_mark : '—'; ?>
                </td>

                <td class="text-center">
                    <?php echo htmlspecialchars($item->conclusionLabel ?? '—'); ?>
                </td>

                <!-- Cột Lệ phí -->
                <td class="text-center">
                    <?php if (!$hasFee): ?>
                        <span class="text-success fw-semibold">Miễn phí</span>
                    <?php else: ?>
                        <span class="text-danger fw-semibold">
                            <?php echo htmlspecialchars($item->feeLabel); ?>
                        </span>
                    <?php endif; ?>
                </td>

                <!-- Cột Nộp phí: chỉ chứa nút/badge, KHÔNG chứa modal -->
                <td class="text-center">
		            <?php if ($paymentCellType === 'done'): ?>
			            <?php echo HTMLHelper::_('jgrid.published', 1, 0, '', false); ?>

		            <?php elseif ($paymentCellType === 'overdue'): ?>
                        <span class="badge bg-danger px-2 py-1">
                            <span class="icon-times-circle me-1" aria-hidden="true"></span>
                            Quá hạn
                        </span>

		            <?php elseif ($paymentCellType === 'not_started'): ?>
                        <span class="badge bg-secondary px-2 py-1">
                            <span class="icon-clock me-1" aria-hidden="true"></span>
                            Chưa bắt đầu
                        </span>

		            <?php elseif ($paymentCellType === 'suspended'): ?>
                        <span class="badge bg-warning text-dark px-2 py-1">
                            <span class="icon-pause me-1" aria-hidden="true"></span>
                            Tạm dừng thu
                        </span>

		            <?php else: /* pay */ ?>
                        <button
                                type="button"
                                class="btn btn-sm btn-warning"
                                data-bs-toggle="modal"
                                data-bs-target="#<?php echo $modalId; ?>"
                        >
                            <span class="icon-credit-card me-1" aria-hidden="true"></span>
                            Nộp phí
                        </button>
		            <?php endif; ?>
                </td>

            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<!-- Kết thúc bảng -->

<!-- ═══════════════════════════════════════════════════════════════════════════
     CÁC MODAL — đặt NGOÀI table, sau thẻ đóng </div> của table-responsive.
     Đây là điều kiện bắt buộc để Bootstrap tìm được đúng element theo id.
     Chỉ render modal cho các môn có phí, chưa nộp, và còn trong hạn.
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php foreach ($itemsData as $data):
    $item    = $data['item'];
    $modalId = $data['modalId'];
    $qrUrl   = $data['qrUrl'];

    // Chỉ render modal khi: có phí + chưa nộp + chưa quá deadline
	if ((float) $item->payment_amount <= 0
		|| $item->payment_completed
		|| $this->isDeadlinePassed
		|| $this->isBeforeOpenFrom
		|| !$this->paymentGateOpen
	)
    {
        continue;
    }
?>
<div
    class="modal fade"
    id="<?php echo $modalId; ?>"
    tabindex="-1"
    aria-labelledby="<?php echo $modalId; ?>-label"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="<?php echo $modalId; ?>-label">
                    Nộp phí thi lại — <?php echo htmlspecialchars($item->subject_name ?? ''); ?>
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Đóng"
                ></button>
            </div>

            <div class="modal-body text-center">
                <p class="mb-1">Quét mã QR bên dưới để chuyển khoản nộp phí thi lại.</p>
                <p class="mb-3 text-muted small">
                    Nội dung chuyển khoản:
                    <strong class="font-monospace">
                        <?php echo htmlspecialchars($item->payment_code); ?>-<?php echo $learnerCode; ?>
                    </strong>
                </p>

                <img
                    src="<?php echo $qrUrl; ?>"
                    alt="QR code nộp phí"
                    class="img-fluid"
                    style="max-width: 280px;"
                    loading="lazy"
                />

                <div class="mt-3 text-muted small">
                    <div>
                        Số tiền:
                        <strong class="text-danger">
                            <?php echo htmlspecialchars($item->feeLabel); ?>
                        </strong>
                    </div>
                    <?php if ($deadlineDisplay !== null): ?>
                    <div class="mt-1 text-warning">
                        <span class="icon-clock me-1" aria-hidden="true"></span>
                        Hạn chót nộp phí: <strong><?php echo $deadlineDisplay; ?></strong>
                    </div>
                    <?php endif; ?>
                    <div class="mt-1">
                        Sau khi chuyển khoản, vui lòng kiểm tra lại trạng thái nộp phí
                        sau <strong>1–2 ngày làm việc</strong>.
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Đóng
                </button>
            </div>

        </div>
    </div>
</div>
<?php endforeach; ?>
