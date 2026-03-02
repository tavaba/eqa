<?php

/**
 * Template mặc định cho view Learnerretake (front-end).
 *
 * NGUYÊN TẮC QUAN TRỌNG VỀ MODAL:
 *   Tất cả <div class="modal"> phải được render SAU thẻ đóng </table>,
 *   TUYỆT ĐỐI KHÔNG đặt bên trong <td>. Đặt <div> trong <td> là HTML
 *   không hợp lệ — trình duyệt tự đẩy chúng ra ngoài <table> khi parse,
 *   làm sai lệch id trong DOM và Bootstrap không tìm được target khi click.
 */

defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;

// Đảm bảo Bootstrap JS được load (cần thiết cho modal data-bs-toggle)
HTMLHelper::_('bootstrap.framework');

/** @var \Kma\Component\Eqa\Site\View\Learnerretake\HtmlView $this */

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
?>

<!-- Tiêu đề -->
<h4 class="mb-3">
    Danh sách môn thi lại của <strong><?php echo $learnerFullname; ?></strong>
    (<?php echo $learnerCode; ?>)
</h4>

<!-- Ghi chú nộp phí -->
<div class="alert alert-info mb-3">
    <span class="icon-info me-1" aria-hidden="true"></span>
    HVSV nộp phí thi lại theo từng môn. Để nộp phí thi lại, HVSV nhấn chuột vào nút
    <strong>"Nộp phí"</strong> ở môn tương ứng. Hiện nay, kết quả nộp phí chưa được ghi
    nhận tự động theo thời gian thực, mà cán bộ Học viện sẽ duyệt thủ công. HVSV vui
    lòng kiểm tra lại trạng thái nộp phí sau <strong>1–2 ngày</strong>.
</div>

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
            $item      = $data['item'];
            $modalId   = $data['modalId'];
            $hasFee    = (float) $item->payment_amount > 0;
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

                <!-- Cột Nộp phí: chỉ chứa nút hoặc icon, KHÔNG chứa modal -->
                <td class="text-center">
                    <?php if (!$hasFee || $item->payment_completed): ?>
                        <?php echo HTMLHelper::_('jgrid.published', 1, 0, '', false); ?>
                    <?php else: ?>
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
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php foreach ($itemsData as $data):
    $item    = $data['item'];
    $modalId = $data['modalId'];
    $qrUrl   = $data['qrUrl'];

    // Chỉ render modal cho trường hợp có phí mà chưa nộp
    if ((float) $item->payment_amount <= 0 || $item->payment_completed) {
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
