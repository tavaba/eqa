<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;

//Preprocessing
$samplePath = 'media/com_eqa/xlsx/sample_subjects.xlsx';
$sampleUrl = JUri::root().$samplePath;
?>
    <div class="accordion">
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> Hướng dẫn nhập thông tin môn học </button></h2>
            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                <div class="accordion-body">
                    File mẫu ở đây: <a href="<?php echo $sampleUrl;?>">sample_subjects.xls</a><br/>
                    Một số nội dung cần biết về việc nhập thông tin môn học:<br/>
                    <ol>
                        <li>Nếu chọn chế độ "Ghi đè" và mã môn học đã tồn tại thì thông tin về môn học
                            tương ứng sẽ được cập nhật từ file được tải lên</li>
                        <li>File có định dạng Microsoft Excel (.xls hoặc .xlsx) với các đặc điểm sau:
                            <ul>
                                <li>Có một sheet duy nhất (hoặc thông tin môn học phải ở sheet đầu tiên)</li>
                                <li>Dòng đầu tiên là tiêu đề. Mỗi dòng tiếp theo là thông tin một môn học</li>
                                <li>Cột A: Mã của khoa phụ trách</li>
                                <li>Cột B: Mã môn học</li>
                                <li>Cột C: Tên môn học</li>
                                <li>Cột D: Ký hiệu bậc học: ĐH, CH, TS</li>
                                <li>Cột E: Số tín chỉ</li>
                                <li>Cột F: Hình thức thi: Có thể để trống (không xác định), ghi đầy đủ hoặc viết tắt
                                    nhưng phải chính xác đến từng ký tự như sau:
                                    <ul>
                                        <?php
                                        $testTypes = ExamHelper::getTestTypes();
                                        foreach ($testTypes as $code => $text)
                                        {
                                            $abbr = ExamHelper::getTestTypeAbbr($code);
                                            echo '<li>'.$abbr.': '.$text.'</li>';
                                        }
                                        ?>
                                    </ul>
                                </li>
                                <li>Cột G: Năm xây dựng ngân hàng câu hỏi
                                    <ul>
                                        <li>Để trống: Chưa có ngân hàng</li>
                                        <li>Năm: Có ngân hàng, xây dựng vào năm này</li>
                                        <li>JSON string: Trường hợp có nhiều ngân hàng (hiện chưa hỗ trợ)</li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

<div>
</div>
<hr/>
<?php
ViewHelper::printUploadForm($this->form);