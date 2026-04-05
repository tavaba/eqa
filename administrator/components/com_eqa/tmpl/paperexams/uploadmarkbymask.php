<?php
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Helper\ViewHelper;

HTMLHelper::_('behavior.formvalidator');
$urlExamrooms = Route::_('index.php?option=com_eqa&view=examrooms',false);
$urlSample = JUri::root().'media/com_eqa/xlsx/sample_markingsheet.xlsx';
?>
<div>
	<b>Lưu ý</b>:
	<ol>
		<li>Khi nhập điểm thi KTHP, hệ thống sẽ tự động tính toán điểm học phần (bao gồm việc trừ
			điểm xử lý kỷ luật (nếu có) và việc giới hạn điểm thi lần 2), tự động kết luận
			về việc đạt hay không đạt, còn được thi tiếp hay hết lượt thi.</li>
		<li>Cần <a href="<?php echo $urlExamrooms;?>">nhập thông tin bất thường phòng thi</a>
			<span style="color: red; font-weight: bold;">TRƯỚC KHI</span> nhập điểm. Nếu không,
			kết quả chung có thể sẽ bị sai lệch.
		</li>
		<li>Sử dụng file '<a href="<?php echo $urlSample;?>">Phiếu chấm thi viết</a>' (theo số phách)
			để nhập điểm! Không được thay đổi bất kỳ thông tin nào trong phiếu chấm, ngoại trừ việc
			điền kết quả chấm thi vào ô điểm (bằng số). Nếu không, kết quả chấm có thể bị
			sai lệch khi nhập vào hệ thống.
		</li>
	</ol>
	<hr/>
</div>
<?php
ViewHelper::printUploadForm($this->form);