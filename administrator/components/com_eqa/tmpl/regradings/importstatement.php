<?php
/**
 * Layout nhập sao kê ngân hàng để đối soát phí phúc khảo.
 * Template: administrator/components/com_eqa/tmpl/regradings/importstatement.php
 *
 * @since 2.0.7
 */
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Helper\FormHelper;
use Kma\Library\Kma\Helper\ViewHelper;

HTMLHelper::_('bootstrap.framework');

/** @var \Kma\Component\Eqa\Administrator\View\Regradings\HtmlView $this */

$form         = $this->uploadStatementForm;
$examseason   = $this->examseason??null;
$examseasonId = $examseason? $examseason->id : 0;
?>

<div class="alert alert-info">
    <span class="icon-info me-1" aria-hidden="true"></span>
    Tải lên file sao kê Excel (.xlsx) từ ngân hàng. Hệ thống sẽ đối chiếu <strong>mã nộp tiền</strong>
    và <strong>số tiền</strong> trong sao kê với các yêu cầu phúc khảo của kỳ thi này.
    Các yêu cầu khớp sẽ được ghi nhận là <em>đã nộp phí</em> và tự động chuyển sang
    trạng thái <strong>"Đã được chấp nhận"</strong> (nếu chưa được chấp nhận trước đó).
</div>
<?php
    ViewHelper::printUploadForm($form,'regradings.importStatement');
?>
