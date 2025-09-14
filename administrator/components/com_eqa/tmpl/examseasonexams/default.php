<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
$examseason = $this->item;
if(!empty($examseason)){
    echo 'Kỳ thi: <b>'. htmlentities($examseason->name) . '</b><br/>';
    echo '(Học kỳ '.$examseason->term.'. Năm học '. DatabaseHelper::getAcademicyearCode($examseason->academicyear_id).')';
}
?>
<div class="accordion">
	<div class="accordion-item">
		<h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> Hướng dẫn sử dụng </button></h2>
		<div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
			<div class="accordion-body">
				<ol>
					<li><b>Thêm thủ công</b>: Sử dụng form để thêm môn thi vào bất kỳ kỳ thi nào</li>
					<li><b>Thêm theo môn học</b>: Căn cứ thông tin về <i>học kỳ</i> của kỳ thi, phần mềm
						sẽ tự động lấy danh sách các <i>môn học</i> có trong học kỳ để người dùng chọn. Sau đó,
						phần mềm sẽ tự động tạo một <i>môn thi</i> cho mỗi môn học đã chọn, đồng thời thêm
						tất cả HVSV ở tất cả các <i>lớp học phần</i> của môn học đó (trong học kỳ đã định)
						vào môn thi vừa tạo.</li>
                    <li><b>Thêm theo lớp học phần</b>: Phần mềm sẽ hiển thị một danh sách các lớp học phần
                        trong học kỳ của kỳ thi để người dùng chọn. Phần mềm tự động tạo một môn thi cho mỗi
                        lớp học phần được chọn và thêm tất cả HVSV trong lớp học phần này vào môn thi
                        vừa tạo. Đồng thời sẽ cập nhật luôn tình trạng <b>nợ phí</b>, các hình thức <b>khuyến khích điểm</b>
                        của thí sinh (nếu có). Chỉ những HVSV còn quyền dự thi mới được thêm vào môn thi.<br/>
                        Cần lưu ý: (1) kể cả khi lớp không có HVSV nào thì môn thi vẫn được tạo; (2) nếu một
                        lớp học phần được chọn nhiều lần thì sẽ có nhiều môn thi được tạo ra.
                    </li>
                    <li><b>Thêm môn thi lại</b>: Phần mềm sẽ tìm kiếm trong toàn bộ CSDL để xác định những HVSV còn quyền
                        dự thi (bao gồm cả các trường hợp bảo lưu), sắp xếp theo các môn học, từ đó tạo các môn thi tương ứng.<br/>
                        <span style="font-weight: bold; color: red">Lưu ý quan trọng TRƯỚC KHI thực hiện:</span>
                        <ul>
                            <li>Nên cập nhật thông tin nợ phí</li>
                            <li>Nên cập nhật thông tin khuyến khích điểm</li>
                            <li><b>Bắt buộc</b> phải xuất danh sách thí sinh thi lại, bảo lưu trước khi
                                bấm nút <b>Tạo môn thi lại</b>. Bởi khi tạo môn thi, các trường hợp được miễn thi
                                sẽ được tự động ghi nhận kết quả, và các thí sinh đó sẽ không còn xuất hiện trong
                                danh sách thi lại, bảo lưu nếu xuất danh sách sau khi tạo môn thi.</li>
                            <li><b>Bắt buộc</b> phải kiểm tra và đảm bảo rằng bạn đang chọn đúng kỳ thi.
                                Bởi nếu chọn nhầm thì việc xóa các môn thi mới tạo sẽ rất vất vả.</li>
                        </ul>
                    </li>
                    <li><b>Danh sách thi</b>: Tải về tập tin Excel chứa thông tin thí sinh các môn thi được chọn.
                        Danh sách này được cung cấp cho thí sinh để biết thời gian, địa điểm thi. Thường thực hiện
                        sau khi đã chia phòng thi và đánh số báo danh. Khi đó danh sách sẽ có thông tin về
                        phòng thi, giờ thi của mỗi thí sinh.</li>
                    <li><b>Bảng điểm SV</b>: Tải về tập tin Excel chứa bảng điểm các môn thi được chọn.
                        Trước đây, bảng điểm này là cơ sở để xây dựng báo cáo tổng hợp điểm. Hiện tại,
                        thông tin chỉ để tham khảo vì đã có chức năng xuất báo cáo tổng hợp hoàn chỉnh
                        ra dạng tập tin Micrsoft Word.</li>
                    <li><b>Bảng điểm ĐT (Lần 1)</b>: Tải về tập tin Excel chứa kết quả các môn thi được chọn
                        để nhập lên Hệ thống Quản lý đào tạo (theo đúng mẫu cần thiết). Áp dụng cho các môn
                        thi lần 1 (thi đi).</li>
                    <li><b>Bảng điểm ĐT (Lần 2)</b>: Tải về tập tin Excel chứa kết quả các môn thi được chọn
                        để nhập lên Hệ thống Quản lý đào tạo (theo đúng mẫu cần thiết). Áp dụng cho các môn
                        thi lần 2 trở lên (thi lại), bao gồm cả những trường hợp bảo lưu thi lần 1
                        nhưng thi chung đợt thi lần 2.</li>
                </ol>
			</div>
		</div>
	</div>
</div>
<?php
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
