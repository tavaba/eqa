<?php

/**
 * Template mặc định cho view Learnerregradings (front-end).
 *
 * Sinh mã QR bằng thư viện qrcodejs (client-side) thay vì gọi img.vietqr.io.
 * Lý do: VietQR image API filter/normalize ký tự trong addInfo, làm mất ký tự
 * phân cách giữa payment_code và learner_code. Với qrcodejs, toàn bộ VietQR
 * quick-link URL được nhúng làm TEXT CONTENT của QR — app ngân hàng đọc URL
 * rồi parse addInfo trực tiếp, không qua server trung gian nào.
 *
 * NGUYÊN TẮC VỀ MODAL:
 *   Tất cả <div class="modal"> phải đặt SAU thẻ đóng </table>. Đặt trong <td>
 *   là HTML không hợp lệ — trình duyệt tự đẩy ra ngoài, Bootstrap mất target.
 *
 * @since 2.0.7
 */

defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Kma\Library\Kma\Helper\ViewHelper;

HTMLHelper::_('bootstrap.framework');

/**
 * @var \Kma\Component\Eqa\Site\View\Learnerregradings\HtmlView $this
 */

// ─── Lỗi kỹ thuật ────────────────────────────────────────────────────────────
if ($this->errorMessage): ?>
    <div class="alert alert-danger">
        <span class="icon-warning me-2" aria-hidden="true"></span>
        <?php echo $this->errorMessage; ?>
    </div>
<?php return; endif;

$learner    = $this->learner;
$examseason = $this->examseason;
$learnerCode = $learner->code ?? '';
?>

<!-- Tiêu đề -->
<div class="mb-3">
    <div>Thí sinh: <strong><?php echo htmlspecialchars($learnerCode); ?></strong>. <?php echo htmlspecialchars($learner->getFullName()); ?></div>
    <div>Kỳ thi: <?php echo $examseason ? htmlspecialchars($examseason->name) : 'Tất cả'; ?></div>
</div>

<?php
// Kiểm tra có item nào có phí chưa nộp không (để hiển thị hướng dẫn)
$hasUnpaidFee = false;
if (!empty($this->layoutData) && !empty($this->layoutData->items)) {
    foreach ($this->layoutData->items as $item) {
        if ((int) ($item->_paymentAmount ?? 0) > 0 && !($item->_paymentCompleted ?? false)) {
            $hasUnpaidFee = true;
            break;
        }
    }
}
?>

<?php if ($hasUnpaidFee): ?>
<div class="alert alert-info mb-3">
    <span class="icon-info me-1" aria-hidden="true"></span>
    HVSV nộp phí phúc khảo <strong>theo từng môn</strong>. Để nộp phí, nhấn nút
    <strong>"Nộp phí"</strong> ở môn tương ứng rồi quét mã QR để chuyển khoản.
    Mỗi thí sinh ở mỗi môn thi có một mã chuyển khoản riêng — nhập sai nội dung
    sẽ không được nhận diện tự động và HVSV phải <strong>tự chịu trách nhiệm</strong>.<br>
    Trạng thái nộp phí được cán bộ Học viện duyệt thủ công sau
    <strong>1–2 ngày làm việc</strong>.
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     BẢNG — không chứa modal bên trong
     ═══════════════════════════════════════ -->
<?php ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields); ?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     THƯ VIỆN QR — load một lần, dùng chung cho tất cả modal trên trang.
     Nguồn: media/com_eqa/js/qrcode.min.js (bundled — không phụ thuộc CDN ngoài).
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php
// Kiểm tra có modal nào cần render không trước khi load thư viện
$hasQrModal = false;
if (!empty($this->layoutData) && !empty($this->layoutData->items)) {
    foreach ($this->layoutData->items as $item) {
        if (($item->_paymentAmount ?? 0) > 0
            && !($item->_paymentCompleted ?? false)
            && ($item->_hasBankInfo ?? false)
            && !empty($item->_paymentCode)
        ) {
            $hasQrModal = true;
            break;
        }
    }
}
?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     CÁC MODAL QR — đặt NGOÀI table.
     Mỗi modal tự sinh QR khi Bootstrap phát sự kiện 'shown.bs.modal' (lazy).
     QR được sinh một lần duy nhất (kiểm tra innerHTML) để tránh sinh lại.
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php if (!empty($this->layoutData) && !empty($this->layoutData->items)):
    foreach ($this->layoutData->items as $item):

        // Điều kiện render modal: có phí + chưa nộp + có bank info + có payment_code
        if (($item->_paymentAmount ?? 0) <= 0
            || ($item->_paymentCompleted ?? false)
            || !($item->_hasBankInfo ?? false)
            || empty($item->_paymentCode)
        ) {
            continue;
        }

        $modalId    = $item->_qrModalId;
        $qrDivId    = $modalId . '-qr';
        $feeAmount  = (int) $item->_paymentAmount;
        $examName   = htmlspecialchars($item->examName ?? '');

        // Nội dung chuyển khoản: payment_code + ký tự phân cách + mã HVSV
        // Dùng dấu gạch dưới thay gạch ngang vì ít bị filter hơn,
        // nhưng với cách sinh QR client-side thì ký tự nào cũng được.
        $transferContent = $item->_paymentCode . '-' . $learnerCode;

        // VietQR quick-link URL — đây là TEXT CONTENT của QR, không phải src ảnh.
        // qrcodejs nhúng URL này vào ma trận QR. App ngân hàng quét → đọc URL
        // → parse amount và addInfo trực tiếp từ query string.
        // JSON-encode an toàn để nhúng vào JS inline (tránh XSS)
        $jsModalId   = json_encode($modalId,                         JSON_HEX_TAG);
        $jsQrDivId   = json_encode($qrDivId,                         JSON_HEX_TAG);
        $jsNapas     = json_encode($item->bankNapasCode  ?? '',       JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsAccount   = json_encode($item->bankAccountNumber ?? '',    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsAmount    = (int) $feeAmount;
        $jsAddInfo   = json_encode($transferContent,                  JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
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
                    Nộp phí phúc khảo — <?php echo $examName; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>

            <div class="modal-body text-center">

                <p class="mb-1">Quét mã QR bên dưới để chuyển khoản nộp phí phúc khảo.</p>
                <p class="mb-3 text-muted small">
                    Nội dung chuyển khoản:
                    <strong class="font-monospace"><?php echo htmlspecialchars($transferContent); ?></strong>
                </p>

                <!-- Container QR — qrcodejs sinh <canvas> hoặc <img> vào đây -->
                <div
                    id="<?php echo $qrDivId; ?>"
                    style="display:inline-block; padding:8px; background:#fff; border-radius:6px;"
                ></div>

                <div class="mt-3 text-muted small">
                    <div>
                        Số tiền:
                        <strong class="text-danger">
                            <?php echo htmlspecialchars(number_format($feeAmount, 0, ',', '.')); ?>&nbsp;đ
                        </strong>
                    </div>
                    <?php if (!empty($item->bankAccountOwner)): ?>
                    <div class="mt-1">
                        Tài khoản:
                        <strong><?php echo htmlspecialchars($item->bankAccountOwner); ?></strong>
                        — <?php echo htmlspecialchars($item->bankAccountNumber); ?>
                    </div>
                    <?php endif; ?>
                    <div class="mt-2">
                        Sau khi chuyển khoản, vui lòng kiểm tra lại trạng thái nộp phí
                        sau <strong>1–2 ngày làm việc</strong>.
                    </div>
                </div>

            </div><!-- /.modal-body -->

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<script>
(function () {
    var modalEl      = document.getElementById(<?php echo $jsModalId; ?>);
    var qrEl         = document.getElementById(<?php echo $jsQrDivId; ?>);
    var napasCode    = <?php echo $jsNapas; ?>;
    var accountNumber= <?php echo $jsAccount; ?>;
    var amount       = <?php echo $jsAmount; ?>;
    var addInfo      = <?php echo $jsAddInfo; ?>;

    if (!modalEl || !qrEl) return;

    modalEl.addEventListener('shown.bs.modal', function () {
        // Sinh QR một lần duy nhất (lazy); lần sau bỏ qua
        if (qrEl.innerHTML !== '') return;

        if (typeof QRCode === 'undefined') {
            qrEl.innerHTML = '<span class="text-danger small">Không tải được thư viện QR.</span>';
            return;
        }

        new QRCode(qrEl, {
            napasCode:     napasCode,
            accountNumber: accountNumber,
            amount:        amount,
            addInfo:       addInfo,
            width:         240,
            height:        240,
            colorDark:     '#000000',
            colorLight:    '#ffffff',
            correctLevel:  QRCode.CorrectLevel.M
        });
    });
}());
</script>

<?php
    endforeach;
endif;
?>
