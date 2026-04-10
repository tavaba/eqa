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
 * QR được sinh client-side bằng qrcode.min.js (load qua Web Asset Manager ở View).
 *
 * @since 2.2.0
 */

defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Site\View\Learnerretake\HtmlView;

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
$learnerCode     = $this->learnerCode; // raw, dùng cho addInfo

// Định dạng deadline
$deadlineDisplay = null;
if ($this->deadlineLocal !== null) {
    try {
        $deadlineDisplay = (new DateTime($this->deadlineLocal))->format('H:i, d/m/Y');
    } catch (Exception) {
        $deadlineDisplay = htmlspecialchars($this->deadlineLocal);
    }
}

// Định dạng last_statement_update
$lastStatementDisplay = null;
if ($this->lastStatementUpdateLocal !== null) {
    try {
        $lastStatementDisplay = (new DateTime($this->lastStatementUpdateLocal))->format('H:i, d/m/Y');
    } catch (Exception) {
        $lastStatementDisplay = htmlspecialchars($this->lastStatementUpdateLocal);
    }
}

// Tiền tính toán dữ liệu từng item
$itemsData = [];
foreach ($this->items as $item) {
    $hasFee = (float) $item->payment_amount > 0;
    if (!$hasFee || $item->payment_completed) {
        $cellType = 'done';
    } elseif ($this->isDeadlinePassed) {
        $cellType = 'overdue';
    } elseif ($this->isBeforeOpeningTime) {
        $cellType = 'not_started';
    } elseif (!$this->paymentGateOpen) {
        $cellType = 'suspended';
    } else {
        $cellType = 'pay';
    }
    $itemsData[] = [
        'item'     => $item,
        'hasFee'   => $hasFee,
        'cellType' => $cellType,
        'modalId'  => 'qr-modal-' . (int) $item->id,
    ];
}

/**
 * Closure dùng chung để sinh đoạn <script> lazy-init QR cho một modal.
 * Dùng json_encode() với JSON_HEX_* để tránh XSS khi nhúng vào JS inline.
 */
$renderQrScript = static function (
    string $modalId,
    string $napasCode,
    string $accountNo,
    int    $amount,
    string $addInfo
): void {
    $f = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
       | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    $jsModal   = json_encode($modalId,          $f);
    $jsQrDiv   = json_encode($modalId . '-qr',  $f);
    $jsNapas   = json_encode($napasCode,         $f);
    $jsAccount = json_encode($accountNo,         $f);
    $jsAmount  = (int) $amount;
    $jsAddInfo = json_encode($addInfo,           $f);
    echo <<<JS
<script>
(function () {
    var modalEl = document.getElementById({$jsModal});
    var qrEl    = document.getElementById({$jsQrDiv});
    if (!modalEl || !qrEl) return;
    modalEl.addEventListener('shown.bs.modal', function () {
        if (qrEl.innerHTML !== '') return;
        if (typeof QRCode === 'undefined') {
            qrEl.innerHTML = '<span class="text-danger small">Không tải được thư viện QR.</span>';
            return;
        }
        new QRCode(qrEl, {
            napasCode: {$jsNapas}, accountNumber: {$jsAccount},
            amount: {$jsAmount}, addInfo: {$jsAddInfo},
            width: 240, height: 240,
            colorDark: '#000000', colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
    });
}());
</script>
JS;
};
?>

<!-- Tiêu đề -->
<h4 class="mb-3">
    Danh sách môn thi lại của <strong><?php echo $learnerFullname; ?></strong>
    (<?php echo htmlspecialchars($learnerCode); ?>)
</h4>

<!-- Lưu ý quan trọng về nghĩa vụ học phí — luôn hiển thị -->
<div class="alert alert-warning mb-3">
    <span class="icon-warning me-2" aria-hidden="true"></span>
    <strong>Lưu ý quan trọng:</strong>
    HVSV phải hoàn thành nghĩa vụ học phí của môn học trước khi nộp phí thi lại.
    Nếu còn nợ học phí môn học thì HVSV sẽ không được thi lại.
</div>

<!-- Thông báo về deadline -->
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

<!-- Ghi chú nộp phí — chỉ hiển thị khi còn trong hạn -->
<?php if (!$this->isDeadlinePassed): ?>
<div class="alert alert-info mb-3">
    <span class="icon-info me-1" aria-hidden="true"></span>
    HVSV nộp phí thi lại <strong>theo từng môn</strong>. Để nộp phí, nhấn nút
    <strong>"Nộp phí"</strong> ở môn tương ứng rồi quét mã QR để chuyển khoản.
    Mỗi thí sinh ở mỗi môn thi có một mã chuyển khoản riêng — nhập sai nội dung
    sẽ không được nhận diện tự động và HVSV phải <strong>tự chịu trách nhiệm</strong>.<br>
    Trạng thái nộp phí được cán bộ Học viện duyệt thủ công sau <strong>1–2 ngày</strong>.
    <?php if ($lastStatementDisplay !== null): ?>
        <br><span class="icon-info-circle me-1" aria-hidden="true"></span>
        Sao kê được cập nhật lần gần nhất lúc <strong><?php echo $lastStatementDisplay; ?></strong>.
    <?php endif; ?>
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
                <th class="text-center" style="width:10%">Phí thi lại</th>
                <th class="text-center" style="width:10%">Nộp phí</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($itemsData as $i => $data):
            $item     = $data['item'];
            $hasFee   = $data['hasFee'];
            $cellType = $data['cellType'];
            $modalId  = $data['modalId'];
        ?>
            <tr>
                <td class="text-center"><?php echo $i + 1; ?></td>

                <td class="text-center font-monospace">
                    <?php echo htmlspecialchars($item->subject_code ?? ''); ?>
                </td>

                <td><?php echo htmlspecialchars($item->subject_name ?? ''); ?></td>

                <td class="text-center"><?php echo $item->credits     ?? '—'; ?></td>
                <td class="text-center"><?php echo $item->pam1        !== null ? $item->pam1        : '—'; ?></td>
                <td class="text-center"><?php echo $item->pam2        !== null ? $item->pam2        : '—'; ?></td>
                <td class="text-center"><?php echo $item->pam         !== null ? $item->pam         : '—'; ?></td>
                <td class="text-center"><?php echo $item->mark_orig   !== null ? $item->mark_orig   : '—'; ?></td>
                <td class="text-center"><?php echo $item->module_mark !== null ? $item->module_mark : '—'; ?></td>

                <td class="text-center">
                    <?php echo htmlspecialchars($item->conclusionLabel ?? '—'); ?>
                </td>

                <!-- Cột Lệ phí -->
                <td class="text-center">
                    <?php if (!$hasFee): ?>
                        <span class="text-success fw-semibold">Miễn phí</span>
                    <?php else: ?>
                        <span class="text-danger fw-semibold">
                            <?php echo number_format((int) $item->payment_amount, 0, ',', '.'); ?>&nbsp;đ
                        </span>
                    <?php endif; ?>
                </td>

                <!-- Cột Nộp phí: chỉ chứa nút/badge, KHÔNG chứa modal -->
                <td class="text-center">
                    <?php if ($cellType === 'done'): ?>
                        <?php echo HTMLHelper::_('jgrid.published', 1, 0, '', false); ?>

                    <?php elseif ($cellType === 'overdue'): ?>
                        <span class="badge bg-danger px-2 py-1">
                            <span class="icon-times-circle me-1" aria-hidden="true"></span>Quá hạn
                        </span>

                    <?php elseif ($cellType === 'not_started'): ?>
                        <span class="badge bg-secondary px-2 py-1">
                            <span class="icon-clock me-1" aria-hidden="true"></span>Chưa bắt đầu
                        </span>

                    <?php elseif ($cellType === 'suspended'): ?>
                        <span class="badge bg-warning text-dark px-2 py-1">
                            <span class="icon-pause me-1" aria-hidden="true"></span>Tạm dừng thu
                        </span>

                    <?php else: /* pay */ ?>
                        <button
                            type="button"
                            class="btn btn-sm btn-warning"
                            data-bs-toggle="modal"
                            data-bs-target="#<?php echo $modalId; ?>"
                        >
                            <span class="icon-credit-card me-1" aria-hidden="true"></span>Nộp phí
                        </button>
                    <?php endif; ?>
                </td>

            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     CÁC MODAL QR + SCRIPT — đặt NGOÀI table.
     Chỉ render modal khi: có phí + chưa nộp + cổng thu phí đang mở.
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php foreach ($itemsData as $data):
    if ($data['cellType'] !== 'pay') continue;

    $item      = $data['item'];
    $modalId   = $data['modalId'];
    $feeAmount = (int) round($item->payment_amount);
    $addInfo   = $item->payment_code . '-' . $learnerCode;
?>
<div class="modal fade" id="<?php echo $modalId; ?>"
     tabindex="-1" aria-labelledby="<?php echo $modalId; ?>-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="<?php echo $modalId; ?>-label">
                    Nộp phí thi lại — <?php echo htmlspecialchars($item->subject_name ?? ''); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>

            <div class="modal-body text-center">
                <p class="mb-1">Quét mã QR bên dưới để chuyển khoản nộp phí thi lại.</p>
                <p class="mb-3 text-muted small">
                    Nội dung chuyển khoản:
                    <strong class="font-monospace"><?php echo htmlspecialchars($addInfo); ?></strong>
                </p>

                <div id="<?php echo $modalId; ?>-qr"
                     style="display:inline-block;padding:8px;background:#fff;border-radius:6px;"></div>

                <div class="mt-3 text-muted small">
                    <div>Số tiền:
                        <strong class="text-danger">
                            <?php echo number_format($feeAmount, 0, ',', '.'); ?>&nbsp;đ
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>

        </div>
    </div>
</div>
<?php $renderQrScript($modalId, $this->bankNapasCode, $this->bankAccount, $feeAmount, $addInfo); ?>
<?php endforeach; ?>
