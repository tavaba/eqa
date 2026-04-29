<?php
defined('_JEXEC') or die();

/**
 * Layout: selecttemplate — Chọn mẫu email thông báo
 *
 * Thuộc view 'notify' của com_kmail.
 * URL: index.php?option=com_kmail&view=notify&layout=selecttemplate
 *      &context_type=X&context_id=Y&notify_url={base64}&return={base64}
 *
 * Hiển thị khi MailService::notify() trả về MailCampaignResult::NeedSelectTemplate
 * — tức là có nhiều hơn 1 template phù hợp với context_type.
 *
 * Người dùng chọn một template rồi submit form POST về:
 *   com_kmail&task=notify.create
 * NotifyController::create() sẽ redirect về notify_url của component gốc kèm template_id.
 *
 * Dữ liệu từ HtmlView (qua $this->layoutData):
 *   ->templates        object[]  Danh sách template phù hợp
 *   ->contextType      int       Giá trị MailContextType enum
 *   ->contextId        int       ID đối tượng ngữ cảnh
 *   ->contextTypeLabel string    Nhãn ngữ cảnh (vd: 'Môn thi')
 *   ->createUrlB64     string    URL base64 của task create tại component gốc
 *   ->returnB64        string    URL base64 để redirect về sau khi hoàn tất
 *
 * @package Kma\Component\Kmail\Administrator\View\Notify
 * @since   1.0.0
 */


use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

/** @var \Kma\Component\Kmail\Administrator\View\Notify\HtmlView $this */

$data             = $this->layoutData;
$templates        = $data->templates;
$contextType      = (int) $data->contextType;
$contextId        = (int) $data->contextId;
$contextTypeLabel = htmlspecialchars($data->contextTypeLabel);
// Giữ nguyên base64 string — không htmlspecialchars() ở đây
// vì giá trị được render vào HTML attribute qua htmlspecialchars() trực tiếp bên dưới
$notifyUrlB64     = $data->notifyUrlB64;
$returnB64        = $data->returnB64;

// URL hủy bỏ — decode return URL nếu có, fallback về com_kmail
$cancelUrl = Route::_('index.php?option=com_kmail&view=campaigns', false);
if ($data->returnB64 !== '') {
    $decoded = base64_decode($data->returnB64);
    if ($decoded !== false && str_starts_with($decoded, 'index.php')) {
        $cancelUrl = Route::_($decoded, false);
    }
}

// Form action: task trong URL đảm bảo Joomla route đúng đến NotifyController::create()
// dù hidden field task có bị strip hay không
$formAction = Route::_('index.php?option=com_kmail&task=notify.create', false);
?>

<div class="container-fluid p-3">

    <!-- Header -->
    <div class="alert alert-info d-flex align-items-center gap-2">
        <span class="icon-envelope fs-5" aria-hidden="true"></span>
        <div>
            Có nhiều mẫu thông báo phù hợp với ngữ cảnh
            <strong><?= $contextTypeLabel ?></strong>.
            Vui lòng chọn một mẫu để gửi thông báo.
        </div>
    </div>

    <!-- Form chọn template -->
    <form action="<?= $formAction ?>" method="post" id="notify-select-form">

        <?= HTMLHelper::_('form.token') ?>
        <input type="hidden" name="context_type" value="<?= $contextType ?>">
        <input type="hidden" name="context_id"   value="<?= $contextId ?>">
        <input type="hidden" name="notify_url"   value="<?= htmlspecialchars($notifyUrlB64) ?>">
        <input type="hidden" name="return"        value="<?= htmlspecialchars($returnB64) ?>">

        <!-- Danh sách template -->
        <div class="row g-3 mb-4">
            <?php foreach ($templates as $i => $template) : ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 template-card"
                         style="cursor: pointer;"
                         onclick="selectTemplate(<?= (int) $template->id ?>)">

                        <div class="card-body">
                            <!-- Radio button ẩn -->
                            <div class="form-check mb-2">
                                <input
                                    class="form-check-input template-radio"
                                    type="radio"
                                    name="template_id"
                                    id="template_<?= (int) $template->id ?>"
                                    value="<?= (int) $template->id ?>"
                                    <?= $i === 0 ? 'checked' : '' ?>
                                    required
                                >
                                <label
                                    class="form-check-label fw-semibold"
                                    for="template_<?= (int) $template->id ?>">
                                    <?= htmlspecialchars($template->title) ?>
                                </label>
                            </div>

                            <!-- Tiêu đề email -->
                            <p class="text-muted small mb-2">
                                <span class="icon-envelope-open me-1" aria-hidden="true"></span>
                                <?= htmlspecialchars($template->subject) ?>
                            </p>

                            <!-- Nội dung preview (tối đa 3 dòng) -->
                            <?php if (!empty($template->body)) : ?>
                                <div class="small text-secondary border-top pt-2"
                                     style="max-height: 4.5em; overflow: hidden;">
                                    <?= strip_tags($template->body) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Buttons -->
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success">
                <span class="icon-envelope me-1" aria-hidden="true"></span>
                Gửi thông báo
            </button>
            <a href="<?= $cancelUrl ?>" class="btn btn-outline-secondary">
                Hủy bỏ
            </a>
        </div>

    </form>

</div>

<script>
/**
 * Chọn template bằng cách click vào card.
 * Highlight card được chọn và check radio button tương ứng.
 */
function selectTemplate(templateId) {
    // Bỏ highlight tất cả card
    document.querySelectorAll('.template-card').forEach(function (card) {
        card.classList.remove('border-success', 'border-2');
        card.classList.add('border');
    });

    // Highlight card được chọn
    const radio = document.getElementById('template_' + templateId);
    if (radio) {
        radio.checked = true;
        radio.closest('.template-card').classList.remove('border');
        radio.closest('.template-card').classList.add('border-success', 'border-2');
    }
}

// Highlight card được chọn mặc định khi load trang
document.addEventListener('DOMContentLoaded', function () {
    const checked = document.querySelector('.template-radio:checked');
    if (checked) {
        checked.closest('.template-card').classList.remove('border');
        checked.closest('.template-card').classList.add('border-success', 'border-2');
    }
});
</script>
