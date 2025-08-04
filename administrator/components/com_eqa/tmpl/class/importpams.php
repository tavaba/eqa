<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;

//Preprocessing
$samplePath = 'media/com_eqa/xlsx/sample_class.xls';
$sampleUrl = JUri::root().$samplePath;
if(empty($this->class))
    return;
echo '<div>';
echo 'Lớp học phần: <b>', htmlspecialchars($this->class->name),'</b><br/>';
echo 'Sĩ số: ', $this->class->size,'<br/>';
echo 'Giảng viên: ';
if(is_numeric($this->class->lecturer_id))
	echo EmployeeHelper::getFullName($this->class->lecturer_id);
echo '<br/>';
echo '</div>';
?>
<div class="accordion">
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> Hướng dẫn nhập ĐQT cho lớp học phần </button></h2>
        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
            <div class="accordion-body">
                File mẫu ở đây: <a href="<?php echo $sampleUrl;?>">sample_class.xls</a><br/>
                Một số nội dung cần biết về việc nhập ĐQT cho lớp học phần:<br/>
                <ol>
                    <li>File có định dạng Microsoft Excel (.xls hoặc .xlsx) với các đặc điểm sau:
                        <ul>
                            <li>Chỉ chứa 1 sheet duy nhất. Vai trò các cột trong sheet như sau:
                                <ul>
                                    <li>Cột B: Mã HVSV</li>
                                    <li>Cột I: Điểm TP1</li>
                                    <li>Cột J: Điểm TP2</li>
                                    <li>Cột K: Điểm QT</li>
                                    <li>Cột M: Ghi chú</li>
                                </ul>
                            </li>
                            <li>Dữ liệu bắt đầu từ dòng 15. Sau HVSV cuối cùng phải có ít nhất một dòng trống.</li>
                        </ul>
                    </li>
                    <li>Phải hoàn tất việc xác định các thành phần ĐQT trước khi nhập</li>
                    <li>Chấp nhận các ghi chú đặc biệt như: N25, N100, TKĐ,...</li>
                    <li>Nếu sau khi nhập mà 100% HVSV của lớp có ĐQT thì phần mềm sẽ ghi nhận hôm nay
                        là ngày bàn giao ĐQT.</li>
                </ol>
            </div>
        </div>
    </div>
</div>
<div></div>
<hr/>
<?php
ViewHelper::printUploadForm($this->form);