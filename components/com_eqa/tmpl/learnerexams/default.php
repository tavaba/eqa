<?php

use Kma\Component\Eqa\Administrator\Interface\LearnerInfo;
use Kma\Component\Eqa\Site\Helper\ViewHelper;

defined('_JEXEC') or die();

if(empty($this->learner))
{
	echo 'Cần đăng nhập bằng tài khoản HVSV để xem nội dung trang này';
	return;
}
?>
<div class="accordion">
	<div class="accordion-item">
		<h2 class="accordion-header" id="headingOne"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> HVSV lưu ý: </button></h2>
		<div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
			<div class="accordion-body">
				<ul>
					<li>
						Trước hết, cần phân biệt rõ "<b>Phúc khảo</b>" và "<b>Đính chính</b>". Trong đó:
						<ul>
							<li>"<b>Phúc khảo</b>" chấm lại bài thi. HVSV chọn yêu cầu phúc khảo khi thấy điểm thi
								được công bố không được như kỳ vọng. Có thể yêu cầu Phúc khảo đồng thời nhiều môn.
								HVSV cần đóng lệ phí để được chấp nhận phúc khảo.</li>
							<li>"<b>Đính chính</b>" là đính chính các sai sót trong quá trình xử lý điểm. HVSV chọn
								yêu cầu đính chính khi phát hiện ra SAI SÓT đối với điểm thi của mình (điểm được công
								bố bởi Phòng KT&ĐBCLĐT khác với điểm mà giảng viên đã công bố trước đó. Khi gửi yêu cầu
								"Đính chính", HVSV cần mô tả cụ thể, chi tiết sai sót cần đính chính. Ví dụ: "Em dự thi môn X
								vào ngày 12/3/2024. Sau buổi thi, thầy Nguyễn Văn A công bố điểm thi của em là 10. Tuy nhiên,
								Phòng KT&ĐBCLĐT công bố điểm của em là 1,0". Chỉ có thể yêu cầu Đính chính từng môn một.
								HVSV không phải đóng phí khi yêu cầu Đính chính.</li>
						</ul>
					</li>
					<li>Quy trình phúc khảo, đính chính điểm như sau
						<ol>
							<li>Thí sinh tạo yêu cầu (ở website này) trong thời hạn gửi yêu cầu theo thông báo của Học viện</li>
							<li>Kết thúc hạn gửi yêu cầu, thí sinh đóng phí (nếu có phúc khảo) qua Hệ QLHV&SV trong thời hạn
							đóng phí đã được thông báo</li>
							<li>Tiến hành chấm phúc khảo, rà soát lại điểm</li>
							<li>Công bố kết quả</li>
						</ol>
					</li>
					<li>Hãy sử dụng bộ lọc để giới hạn hiển thị các môn thi ở một kỳ thi nhất định</li>
				</ul>
			</div>
		</div>
	</div>
</div>

<?php
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
