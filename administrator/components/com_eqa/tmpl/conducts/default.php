<?php
defined('_JEXEC') or die();
use Kma\Library\Kma\Helper\ViewHelper;
?>
<div class="accordion">
	<div class="accordion-item">
		<h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> Hướng dẫn sử dụng </button></h2>
		<div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
			<div class="accordion-body">
				<ol>
					<li><b>Nhập kết quả rèn luyện</b>: Nhập kết quả rèn luyện (và có thể bao gồm
						kết quả học tập) từ tập tin Excel. Cụ thể, xem tiếp hướng dẫn ở trang tương ứng.</li>
					<li><b>Tính kết quả học tập</b>: Đối với HVSV đã có kết quả rèn luyện, chức năng này
						sẽ tìm thông tin về kết quả thi trong học kỳ tương ứng để tính ra tổng số tín chỉ
						đã học, điểm trung bình học kỳ (không bao gồm môn điều kiện: GDTC, GDQPAN), số môn học lại,
						số môn thi lại.</li>
					<li><b>Tính trung bình năm</b>: Căn cứ vào kết quả học tập, rèn luyện của các học kỳ
                        trong năm học tương ứng để tính ra kết quả trung bình năm. Điểm rèn luyện cả năm
                        là trung bình các học kỳ. Điểm thi cả năm là trung bình các học kỳ có trọng số
                        là số tín chỉ của mỗi kỳ. Các chỉ số: nghỉ học, học lại, thi lại, khen thưởng,
                        kỷ luật là tổng của các kỳ trong năm học.</li>
				</ol>
			</div>
		</div>
	</div>
</div>
<div></div>
<hr/>
<?php
ViewHelper::printItemsDefaultLayout($this->getLayoutData(), $this->getListLayoutItemFields());
