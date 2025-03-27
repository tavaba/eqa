<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
$samplePath = 'media/com_eqa/xlsx/sample_students.xlsx';
$sampleUrl = JUri::root().$samplePath;
?>
<div class="accordion">
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> Hướng dẫn sử dụng </button></h2>
        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
            <div class="accordion-body">
                File mẫu ở đây: <a href="<?php echo $sampleUrl;?>">sample_students.xlsx</a><br/>
                Cấu trúc thông tin như sau:<br/>
                <ul>
                    <li>File phải có định dạng Microsoft Excel (.xls hoặc .xlsx).</li>
                    <li>Mỗi file có thể gồm nhiều sheet. Mỗi sheet ứng với một lớp. Tên sheet chính là tên lớp.</li>
                    <li>Mỗi sheet có 4 cột theo thứ tự: STT, Mã HVSV, Họ đệm, Tên</li>
                    <li>Dòng đầu tiên mỗi sheet là tiêu đề. Mỗi dòng tiếp theo là thông tin của 1 HVSV</li>
                </ul>
                Trước khi thực hiện import thông tin HVSV cần đảm bảo rằng thông tin về các lớp học đã được thiết lập xong. Ví dụ, nếu trong CSDL về các lớp hành chính chưa có lớp AT20A thì không thể import sinh viên lớp AT20A. Đồng thời, khi đặt tên cho sheet trong file cần đặt chính xác như tên lớp.
            </div>
        </div>
    </div>
</div>
<hr/>
<?php
ViewHelper::printItemsUploadForm($this);