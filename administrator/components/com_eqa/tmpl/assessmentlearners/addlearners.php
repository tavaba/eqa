<?php

/**
 * Template: Thêm thí sinh vào kỳ sát hạch (nhập thủ công)
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

$assessmentId = (int) ($this->assessment->id ?? 0);
$actionUrl    = Route::_('index.php?option=com_eqa', false);
?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">

        <div class="alert alert-info py-2 small mb-3">
            <span class="icon-info me-1" aria-hidden="true"></span>
            Nhập các mã HVSV cần thêm vào danh sách thi. Phân tách bằng dấu cách, xuống dòng, dấu phẩy hoặc dấu chấm phẩy.
            Mỗi mã HVSV sẽ được sinh ngẫu nhiên một <strong>mã nộp tiền</strong> riêng (nếu kỳ sát hạch có phí).
        </div>

        <form action="<?php echo $actionUrl; ?>" method="POST" name="adminForm" id="adminForm">
            <input type="hidden" name="task"          value="assessmentlearners.addLearners">
            <input type="hidden" name="assessment_id" value="<?php echo $assessmentId; ?>">
            <?php echo HTMLHelper::_('form.token'); ?>

            <div class="mb-3">
                <label for="learner_codes" class="form-label fw-semibold">
                    Danh sách mã HVSV
                    <span class="text-danger">*</span>
                </label>
                <textarea id="learner_codes"
                          name="learner_codes"
                          class="form-control font-monospace"
                          rows="8"
                          placeholder="Ví dụ:&#10;CH2201001&#10;CH2201002, CH2201003&#10;CH2201004; CH2201005"
                          required
                ></textarea>
                <div class="form-text text-muted">
                    Các ký tự phân tách hợp lệ: dấu cách, xuống dòng, dấu phẩy (<code>,</code>), dấu chấm phẩy (<code>;</code>).
                </div>
            </div>
        </form>

    </div>
</div>
