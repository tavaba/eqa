<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
$examsession = $this->examsession;
if(empty($examsession))
	return;
?>
<div class="accordion">
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> Hướng dẫn sử dụng </button></h2>
        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
            <div class="accordion-body">
	            <ol>
		            <li>Đưa chuột lên tên phòng thi để có thêm thông tin về phòng thi</li>
		            <li>Hãy đảm bảo mỗi người chỉ làm việc tại 01 phòng thi trong mỗi ca thi.</li>
		            <li>Không được phép phân công CBCT2 mà không phân công CBCT1 (và tương tự...)</li>
		            <li>Nên nhấn nút "Lưu" (không đóng) sau khi phân công CBCT, CBCTChT cho
		                mỗi phòng thi. Điều này đảm bảo nếu nhập sai dữ liệu thì chỉ nhập lại
		                từng phần nhỏ.</li>
	            </ol>
            </div>
        </div>
    </div>
</div>
<div>
	Ca thi: <?php echo "<b>$examsession->name</b> (",
		DatetimeHelper::getDayOfWeek($examsession->start), ', ',
		DatetimeHelper::getFullDate($examsession->start), ', ',
		DatetimeHelper::getHourAndMinute($examsession->start), ').';?><br/>
	Tổng số phòng thi: <b><?php echo $examsession->countExamroom; ?></b>
	&nbsp;&nbsp;&nbsp;&nbsp; Tổng số thí sinh: <b><?php echo $examsession->countExaminee;?></b><br/>
	Môn thi:<br/>
	<ol>
		<?php
		$examNames = DatabaseHelper::getExamNames($examsession->examIds);
		foreach ($examNames as $examName){
			echo "<li>$examName</li>";
		}
		?>
	</ol>
</div>
<?php
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
