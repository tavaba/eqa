<?php

use Joomla\CMS\Toolbar\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Interface\ExamInfo;
use Kma\Component\Eqa\Administrator\Interface\LearnerInfo;
use Kma\Component\Eqa\Site\Helper\ViewHelper;

defined('_JEXEC') or die();
ToolbarHelper::title('Yêu cầu đính chính điểm');
if(empty($this->examInfo) || empty($this->learnerInfo))
	return;
?>
<div class="accordion">
	<div class="accordion-item">
		<h2 class="accordion-header" id="headingOne"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> HVSV cần lưu ý: </button></h2>
		<div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
			<div class="accordion-body">
				Trước khi gửi yêu cầu, thí sinh cần hiểu đúng về "<b>Đính chính</b>", không nhầm với "<b>Phúc khảo</b>".
				Nếu chưa rõ thì cần liên hệ GVCN hoặc cán bộ của cơ quan khảo thí để làm rõ. Nếu bản chất vấn đề là Phúc khảo
				nhưng thí sinh lại gửi yêu cầu Đính chính thì yêu cầu đó sẽ bị từ chối, không được xem xét. "Đính chính" là
				đính chính các sai sót trong quá trình xử lý điểm. HVSV chọn yêu cầu đính chính khi phát hiện ra SAI SÓT đối
				với điểm thi của mình (điểm được công bố bởi Phòng KT&ĐBCLĐT khác với điểm mà giảng viên đã công
				bố trước đó. Khi gửi yêu cầu "Đính chính", HVSV cần mô tả cụ thể, chi tiết sai sót cần đính chính.
				Ví dụ: "Em dự thi môn X vào ngày 12/3/2024. Sau buổi thi, thầy Nguyễn Văn A công bố điểm thi của em là 10.
				Tuy nhiên, Phòng KT&ĐBCLĐT công bố điểm của em là 1,0". HVSV không phải đóng phí khi yêu cầu Đính chính.
			</div>
		</div>
	</div>
</div>

<?php
$examInfo = ExamInfo::cast($this->examInfo);
$learnerInfo = LearnerInfo::cast($this->learnerInfo);
$action = 'index.php?option=com_eqa';
$hiddenFields = [
	'task' => 'learnerexam.RequestCorrection',
	'exam_id' => $examInfo->id
];
ViewHelper::printForm($this->form, 'requestcorrection', $action, $hiddenFields, true);
