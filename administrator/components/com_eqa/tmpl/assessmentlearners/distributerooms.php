<?php

/**
 * Template: Chia phòng thi cho kỳ sát hạch
 *
 * Luồng 2 phase:
 *   Phase 1 (showform): Controller lưu selectedIds vào session, redirect về layout này.
 *   Phase 2 (getdata):  Form này POST lên task=assessmentlearners.distributeRooms.
 *
 * @package     Com_Eqa
 * @subpackage  tmpl/assessmentlearners
 * @since       2.1.0
 */

defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

/**
 * @var Kma\Component\Eqa\Administrator\View\AssessmentLearners\HtmlView $this
 */

HTMLHelper::_('behavior.formvalidator');

$assessment  = $this->assessment;
$stats       = $this->distributionStats;
$selectedIds = $this->selectedIds;   // [] = toàn bộ; [id,...] = được chọn

$actionUrl = Route::_('index.php?option=com_eqa', false);
?>

<?php /* =================================================================
   Accordion hướng dẫn
   ================================================================= */ ?>
<div class="accordion mb-3">
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#collapseGuide"
                    aria-expanded="false" aria-controls="collapseGuide">
                Hướng dẫn sử dụng
            </button>
        </h2>
        <div id="collapseGuide" class="accordion-collapse collapse">
            <div class="accordion-body small">
                Để chia phòng thi và đánh số báo danh, hãy thực hiện các bước sau:<br>
                1. Chọn tùy chọn <b>Chỉ chia thí sinh đã đóng phí</b>.<br>
                2. Nhấn dấu cộng để thêm ca thi (có thể chia trong 1 hoặc nhiều ca thi).<br>
                3. Trong mỗi ca thi, nhấn dấu cộng để thêm phòng thi và nhập số thí sinh cho mỗi phòng.<br>
                Lưu ý:
                <ul class="mb-0">
                    <li>Thí sinh được phân <b>ngẫu nhiên</b> về các phòng; trong mỗi phòng được sắp xếp
                        theo <b>Tên</b> rồi <b>Họ đệm</b> trước khi đánh SBD.</li>
                    <li>Thí sinh <b>miễn phí</b> (<i>phí = 0</i>) luôn được coi là đủ điều kiện.</li>
                    <li>Tổng số thí sinh nhập vào các phòng phải <b>bằng đúng</b> số thí sinh đủ điều kiện
                        (hiển thị ở ô <b>Số thí sinh sẽ được chia</b> tính tự động).</li>
                    <li>SBD được đánh <b>liên tục</b> qua tất cả các phòng và phải <b>duy nhất</b>
                        trong toàn bộ kỳ sát hạch.</li>
                    <li>Không chọn trùng phòng vật lý trong cùng một ca thi.</li>
                    <li>Khi chạy lại chức năng này, kết quả chia phòng cũ của các thí sinh trong
                        phạm vi hiện tại sẽ bị <b>xóa và ghi đè</b>.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php /* =================================================================
   Thông tin kỳ sát hạch + thống kê
   ================================================================= */ ?>
<div class="card mb-3 border-primary">
    <div class="card-body py-2">
        <h6 class="mb-1 text-primary">
            <span class="icon-passport me-1" aria-hidden="true"></span>
            <?php echo htmlspecialchars($assessment->title ?? ''); ?>
        </h6>
        <div class="small text-muted mb-2">
            <?php echo htmlspecialchars($assessment->start_date ?? ''); ?>
            <?php if (!empty($assessment->end_date) && $assessment->end_date !== $assessment->start_date): ?>
                &nbsp;—&nbsp;<?php echo htmlspecialchars($assessment->end_date); ?>
            <?php endif; ?>
        </div>
        <div class="row g-2">
            <div class="col-auto">
                <span class="badge bg-secondary fs-6"><?php echo (int) $stats->active; ?></span>
                <span class="small text-muted ms-1">Thí sinh</span>
                <?php if ((int) $stats->cancelled > 0): ?>
                <span class="badge bg-danger ms-2"><?php echo (int) $stats->cancelled; ?> đã hủy</span>
                <?php endif; ?>
            </div>
            <?php if ((int) $stats->hasFee > 0): ?>
            <div class="col-auto">
                <span class="badge bg-success fs-6"><?php echo (int) $stats->paid; ?></span>
                <span class="small text-muted ms-1">Đã đóng phí</span>
            </div>
            <div class="col-auto">
                <span class="badge bg-secondary fs-6"><?php echo (int) $stats->free; ?></span>
                <span class="small text-muted ms-1">Miễn phí</span>
            </div>
            <div class="col-auto">
                <span class="badge bg-warning text-dark fs-6"><?php echo (int) $stats->unpaid; ?></span>
                <span class="small text-muted ms-1">Chưa đóng phí</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php /* =================================================================
   Thông báo phạm vi
   ================================================================= */ ?>
<?php if (!empty($selectedIds)): ?>
<div class="alert alert-info py-2 small mb-3">
    <span class="icon-info-circle me-1" aria-hidden="true"></span>
    Đang áp dụng cho <strong><?php echo count($selectedIds); ?> thí sinh được chọn</strong>
    (trong số <?php echo (int) $stats->active; ?> thí sinh đang hoạt động của kỳ sát hạch).
    <?php if ((int) $stats->cancelled > 0): ?>
    <br><span class="text-warning">
        <span class="icon-warning me-1" aria-hidden="true"></span>
        Lưu ý: <?php echo (int) $stats->cancelled; ?> thí sinh đã hủy đăng ký sẽ bị loại khỏi danh sách chia phòng dù có được chọn.
    </span>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="alert alert-warning py-2 small mb-3">
    <span class="icon-info-circle me-1" aria-hidden="true"></span>
    Đang áp dụng cho <strong>toàn bộ <?php echo (int) $stats->active; ?> thí sinh đang hoạt động</strong>
    của kỳ sát hạch.
    <?php if ((int) $stats->cancelled > 0): ?>
    <br><span class="text-muted">(Không bao gồm <?php echo (int) $stats->cancelled; ?> thí sinh đã hủy đăng ký.)</span>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php /* =================================================================
   Form chia phòng
   ================================================================= */ ?>
<form action="<?php echo $actionUrl; ?>" method="post"
      name="adminForm" id="adminForm" class="form-validate">

    <input type="hidden" name="task"          value="assessmentlearners.distributeRooms">
    <input type="hidden" name="phase"         value="getdata">
    <input type="hidden" name="assessment_id" value="<?php echo (int) $assessment->id; ?>">

    <?php echo HTMLHelper::_('form.token'); ?>
    <?php echo $this->form->renderFieldset('examrooms'); ?>

</form>
