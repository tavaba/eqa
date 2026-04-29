<?php
defined('_JEXEC') or die();

/**
 * Layout: selecttemplate — Chọn mẫu email thông báo (Luồng B)
 *
 * Được hiển thị khi task mailcampaigns.notify() phát hiện có nhiều template
 * phù hợp với context_type. Người dùng chọn một template rồi submit form
 * POST về task mailcampaigns.create để tạo campaign.
 *
 * Biến từ HtmlView (qua $this->layoutData):
 *   $this->layoutData->templates        array    — Danh sách template phù hợp
 *   $this->layoutData->contextType      int      — Giá trị context type enum
 *   $this->layoutData->contextId        int      — ID đối tượng ngữ cảnh
 *   $this->layoutData->returnB64        string   — Return URL (base64)
 *   $this->layoutData->contextTypeLabel string   — Nhãn ngữ cảnh (vd: 'Môn thi')
 */


use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Helper\ComponentHelper;

$templates        = $this->layoutDataX->templates;
$contextType      = (int) $this->layoutDataX->contextType;
$contextId        = (int) $this->layoutDataX->contextId;
$returnB64        = $this->layoutDataX->returnB64 ?? '';
$contextTypeLabel = htmlspecialchars($this->layoutDataX->contextTypeLabel ?? '');

// URL hủy bỏ — decode return URL hoặc fallback về list
$cancelUrl = Route::_('index.php?option=' . ComponentHelper::getName() . '&view=campaigns', false);
if ($returnB64 !== '') {
    $decoded = base64_decode($returnB64);
    if ($decoded !== false && str_starts_with($decoded, 'index.php')) {
        $cancelUrl = Route::_($decoded, false);
    }
}

// URL POST form
$formAction = Route::_('index.php?option=' . ComponentHelper::getName(), false);
?>

<div class="container-fluid p-3">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">

            <!-- Tiêu đề -->
            <div class="mb-4">
                <p class="text-muted mb-0">
                    Có <strong><?= count($templates) ?></strong> mẫu email phù hợp với ngữ cảnh
                    <span class="badge bg-info text-dark"><?= $contextTypeLabel ?></span>.
                    Hãy chọn một mẫu để gửi thông báo.
                </p>
            </div>

            <!-- Form chọn template -->
            <form action="<?= $formAction ?>" method="post" id="form-select-template">

                <!-- Hidden fields: context + return + token -->
                <input type="hidden" name="task"         value="mailcampaigns.create">
                <input type="hidden" name="context_type" value="<?= $contextType ?>">
                <input type="hidden" name="context_id"   value="<?= $contextId ?>">
                <input type="hidden" name="return"       value="<?= htmlspecialchars($returnB64) ?>">
                <?= HTMLHelper::_('form.token') ?>

                <!-- Danh sách template dạng radio card -->
                <div class="row g-3 mb-4">
                    <?php foreach ($templates as $index => $template) : ?>
                        <?php
                        $templateId   = (int) $template->id;
                        $radioId      = 'template_' . $templateId;
                        $isFirst      = ($index === 0);
                        ?>
                        <div class="col-12">
                            <div class="card h-100 template-card <?= $isFirst ? 'border-primary' : '' ?>"
                                 style="cursor:pointer;"
                                 onclick="document.getElementById('<?= $radioId ?>').click();">

                                <div class="card-body">
                                    <div class="d-flex align-items-start gap-3">

                                        <!-- Radio button -->
                                        <div class="pt-1 flex-shrink-0">
                                            <input
                                                type="radio"
                                                class="form-check-input"
                                                name="template_id"
                                                id="<?= $radioId ?>"
                                                value="<?= $templateId ?>"
                                                <?= $isFirst ? 'checked' : '' ?>
                                                onchange="updateCardHighlight()"
                                                style="width:1.1em; height:1.1em;"
                                            >
                                        </div>

                                        <!-- Nội dung template -->
                                        <div class="flex-grow-1">
                                            <label for="<?= $radioId ?>"
                                                   class="form-check-label fw-semibold mb-1"
                                                   style="cursor:pointer; font-size:1rem;">
                                                <?= htmlspecialchars($template->title) ?>
                                            </label>

                                            <!-- Subject -->
                                            <div class="text-muted small mb-2">
                                                <span class="me-1">Tiêu đề:</span>
                                                <em><?= htmlspecialchars($template->subject) ?></em>
                                            </div>

                                            <!-- Preview nội dung (truncate 200 ký tự, strip tags) -->
                                            <?php
                                            $bodyPreview = strip_tags($template->body ?? '');
                                            $bodyPreview = preg_replace('/\s+/', ' ', $bodyPreview);
                                            $bodyPreview = mb_substr(trim($bodyPreview), 0, 200);
                                            if (mb_strlen(trim($template->body ?? '')) > 200) {
                                                $bodyPreview .= '…';
                                            }
                                            ?>
                                            <?php if ($bodyPreview !== '') : ?>
                                                <div class="text-muted small"
                                                     style="font-size:0.8rem; line-height:1.4;">
                                                    <?= htmlspecialchars($bodyPreview) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Nút hành động -->
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
    </div>
</div>

<script>
/**
 * Highlight card đang được chọn bằng border-primary Bootstrap.
 */
function updateCardHighlight() {
    document.querySelectorAll('.template-card').forEach(function(card) {
        const radio = card.querySelector('input[type="radio"]');
        if (radio && radio.checked) {
            card.classList.add('border-primary');
        } else {
            card.classList.remove('border-primary');
        }
    });
}

// Khởi tạo khi trang load
document.addEventListener('DOMContentLoaded', updateCardHighlight);
</script>
