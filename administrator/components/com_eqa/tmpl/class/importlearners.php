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
echo 'Sĩ số hiện thời: ', $this->class->size,'<br/>';
echo 'Giảng viên: ';
if(is_numeric($this->class->lecturer_id))
	echo EmployeeHelper::getFullName($this->class->lecturer_id);
echo '<br/>';
echo '</div>';
?>
<div class="accordion">
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> Hướng dẫn nhập HVSV vào lớp học phần </button></h2>
        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
            <div class="accordion-body">
                File mẫu ở đây: <a href="<?php echo $sampleUrl;?>">sample_class.xls</a><br/>
                Một số nội dung cần biết về việc nhập HVSV vào lớp học phần:<br/>
                <ol>
                    <li>File có định dạng Microsoft Excel (.xls hoặc .xlsx) với các đặc điểm sau:
                        <ul>
                            <li>Chỉ chứa 1 sheet duy nhất</li>
                            <li>Mã HVSV ở cột B, bắt đầu từ dòng 15</li>
                            <li>Sau HVSV cuối cùng phải có ít nhất một dòng trống</li>
                        </ul>
                    </li>
                    <li>Ứng dụng sẽ chỉ đọc mã HVSV, không quan tâm đến các thông tin khác như Họ đệm, Tên, Lớp...</li>
                    <li>Nếu HVSV đã có trong lớp thì sẽ bỏ qua, không nhập lại</li>
                </ol>
            </div>
        </div>
    </div>
</div>
<div></div>
<hr/>
<?php
ViewHelper::printUploadForm($this->form);