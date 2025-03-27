<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Interface\ExamInfo;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');

$exam = ExamInfo::cast($this->exam);
$form = $this->form;
?>
<div class="accordion">
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> Hướng dẫn sử dụng </button></h2>
        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
            <div class="accordion-body">
                Để chia phòng thi và đánh số báo danh cho thí sinh, hãy thực hiện các bước sau:<br/>
                1. Nhấn dấu cộng để thêm ca thi (có thể chia trong 1 hoặc nhiều ca thi).<br/>
                2. Trong mỗi ca thi, nhấn dấu cộng để thêm phòng thi.<br/>
                Lưu ý:
                <ul>
                    <li>Việc đánh số báo danh được thực hiện đồng thời với việc chia phòng.</li>
                    <li>Tuy có thông tin về dung lượng phòng thi nhưng phần mềm sẽ không kiểm tra
                        số lượng thí sinh được xếp vào mỗi phòng.</li>
                    <li>Tổng số thí sinh được chia phòng phải ĐÚNG bằng tổng số thí sinh của môn thi
                        (có tính đến các tùy chọn "<b>Chỉ chia phòng cho HVSV đủ điều kiện dự thi</b>"
                        và "<b>Chia phòng cho HVSV nợ học phí</b>"). Lưu ý rằng "đủ điều kiện dự thi"
                        chỉ xét theo kết quả học tập, độc lập với "nợ học phí".</li>
                    <li>Cần đảm bảo trong mỗi ca thi không chọn trùng phòng thi</li>
                    <li>Thông thường, cần kích hoạt tùy chọn "<b>Tạo phòng thi mới</b>". Khi đó, nếu phòng thi
                        được chỉ định đã có (đã được sử dụng trước đó) trong cùng ca thi thì hệ thống sẽ báo lỗi.
                        Chỉ khi cần ghép nhiều môn thi vào một phòng thi thì mới tắt tùy chọn này. Trong trường
                        hợp này, nếu phòng thi đã có trong ca thi thì HVSV sẽ được phân vào phòng thi đã có.</li>
                    <li>Khi các điều kiện được thỏa mãn, HVSV sẽ được phân ngẫu nhiên về các phòng thi.</li>
                </ul>
                <br/>
            </div>
        </div>
    </div>
</div>
<div>
    Môn thi: <b><?php echo htmlentities($exam->name); ?></b>&nbsp;&nbsp;&nbsp;&nbsp; Hình thức thi: <?php echo ExamHelper::getTestType($exam->testtype);?><br/>
    Kỳ thi: <?php echo $exam->examseason;?> <br/>
    (Học kỳ <?php echo $exam->term;?>. Năm học <?php echo $exam->academicyear;?>)<br/>
    Tổng số thí sinh: <?php echo $exam->countTotal;?><br/>
    Đủ điều kiện dự thi: <?php echo $exam->countToTake;?>
    &nbsp;&nbsp;&nbsp;&nbsp; Miễn thi: <?php echo $exam->countExempted; ?>
    &nbsp;&nbsp;&nbsp;&nbsp; Trượt quá trình: <?php echo $exam->countTotal - $exam->countAllowed;?>
    &nbsp;&nbsp;&nbsp;&nbsp; Nợ học phí: <?php echo $exam->countDebtors;?>
    <br/>
    <hr/>
</div>
<form action="<?php echo JRoute::_('index.php?option=com_eqa');?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    <input type="hidden" name="exam_id" value="<?php echo $exam->id;?>">
    <input type="hidden" name="phase" value="getdata">
    <input type="hidden" name="task" value="">
    <?php
    echo HTMLHelper::_('form.token');
    echo $this->form->renderFieldset('examrooms');
    ?>
</form>
