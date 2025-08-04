<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;

//Preprocessing
$form = $this->uploadForm;
$form->setFieldAttribute('term','default',2);

$samplePath = 'media/com_eqa/xlsx/sample_classes.xls';
$sampleUrl = JUri::root().$samplePath;
?>
<div class="accordion">
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> Hướng dẫn upload lớp học phần </button></h2>
        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
            <div class="accordion-body">
                File mẫu ở đây: <a href="<?php echo $sampleUrl;?>">sample_classes.xls</a><br/>
                Một số nội dung cần biết về việc upload thông tin lớp học phần:<br/>
                <ol>
                    <li>File có định dạng Microsoft Excel (.xls hoặc .xlsx) được xuất từ Hệ thống Quản lý đào tạo với các đặc điểm sau:
                        <ul>
                            <li>Có thể có nhiều sheet, mỗi sheet là một lớp học phần</li>
                            <li>M6 chứa mã môn học</li>
                            <li>C7 chứa tên lớp học phần</li>
                            <li>D8 chứa tên giảng viên (nếu có)</li>
                            <li>B15 chứa mã HVSV đầu tiên</li>
                            <li>Sau HVSV cuối cùng phải có ít nhất một dòng trống</li>
                        </ul>
                    </li>
                    <li>Ứng dụng sẽ chỉ đọc mã HVSV, không quan tâm đến các thông tin khác như Họ đệm, Tên, Lớp...</li>
                </ol>
            </div>
        </div>
    </div>
</div>
<div></div>
<hr/>
<?php
ViewHelper::printItemsUploadForm($this);