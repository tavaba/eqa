<?php
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
$urlSamplePaper = JUri::root() . 'media/com_eqa/xlsx/sample_exam_report_paper.xlsx';
$urlSampleNonpaper = JUri::root() . 'media/com_eqa/xlsx/sample_exam_report_nonpaper.xlsx';
$urlEditWarning = JRoute::_('index.php?option=com_eqa&view=examrooms',false);
$formAction = JRoute::_('index.php?option=com_eqa');
HTMLHelper::_('behavior.formvalidator');
?>
<div>
	<b>Lưu ý:</b>
	<ol>
		<li>Đây là chức năng nhập biên bản coi thi, coi thi - chấm thi. Với <b>bài thi viết</b>,
			chức năng này chỉ ghi nhận số tờ giấy thi của các thí sinh để phục vụ cho việc làm phách.
			Đối với <b>bài thi khác</b>, chức năng này sẽ ghi nhận điểm thi, đồng thời tính toán điểm học phần
			(bao gồm việc trừ điểm nếu có xử lý kỷ luật; bao gồm việc giới hạn điểm thi lần 2 ở mức 6,9),
			cũng như đánh giá Đạt/Không đạt, xác định thí sinh đã hết lượt thi hay chưa.</li>
		<li>Đối với hình thức thi Trắc nghiệm, Vấn đáp, Thực hành, Đồ án/Tiểu luận thì điểm thi trong biển bản phải là
			điểm thi cuối cùng, sau khi đã trừ các điểm kỷ luật (nếu có).</li>
		<li>Tùy chọn <b>Nhập thông tin bất thường từ cột 'Ghi chú'</b> yêu cầu nhập cột 'Ghi chú' trong biên bản như sau
			(nếu nhập khác thì sẽ báo lỗi):
			<ul>
				<li><b>K25</b> hoặc <b>Khiển trách</b>: Bị kỷ luật 'Khiển trách', trừ 25% điểm bài thi</li>
				<li><b>K50</b> hoặc <b>Cảnh cáo</b>: Bị kỷ luật 'Cảnh cáo', trừ 50% điểm bài thi</li>
				<li><b>DC</b> hoặc <b>Đình chỉ thi</b>: Bị kỷ luật 'Đình chỉ thi', thí sinh phải nhận điểm 0 và phải học lại</li>
				<li><b>Vắng thi</b>: Thí sinh vắng thi không lý do</li>
				<li><b>Hoãn thi</b>: Thí sinh vắng thi có lý do chính đáng (Thực hiện thủ tục hoãn thi theo quy định hiện hành),
					được bảo lưu lượt thi</li>
				<li><b>Dừng thi</b>: Thí sinh có dự thi nhưng phải dừng thi vì lý do chính đáng (ốm đau, lỗi kỹ thuật...),
					được bảo lưu lượt thi </li>
			</ul>
			Nếu không bật tùy chọn này, để đảm bảo tính	chính xác của dữ liệu, cán bộ khảo thí cần
			<a href="<?php echo $urlEditWarning;?>">NHẬP THÔNG TIN BẤT THƯỜNG</a> của thí sinh
			<span style="font-weight: bold; color: red">TRƯỚC KHI</span> thực hiện chức năng này.	</li>
		<li>Mẫu biên bản <b>Coi thi viết</b>: <a href="<?php echo $urlSamplePaper;?>">download</a></li>
		<li>Mẫu biên bản <b>Coi thi - Chấm thi</b> thực hành, vấn đáp, đồ án:
			<a href="<?php echo $urlSampleNonpaper;?>">download</a></li>
		<li><span style="font-weight: bold; color: red">Đặc biệt lưu ý</span>: không được làm sai lệch số hiệu <b>mã phòng thi</b>
			trong biên bản (mã phòng thi là một số nguyên được gán tự động khi xuất thông tin phòng thi).
			Sai lệch mã phòng thi sẽ dẫn đến sai lệch số liệu nhập vào hệ thống.</li>
	</ol>
</div>
<form action="<?php echo $formAction;?>" method="POST" enctype="multipart/form-data" name="adminForm" id="adminForm" class="form-validate" >
	<input type="hidden" name="task" value=""/>
	<?php
	echo $this->form->renderFieldset('upload');
	echo JHtml::_('form.token');
	?>
</form>
<?php
