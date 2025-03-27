<?php
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Interface\ExamInfo;

$exam = ExamInfo::cast($this->exam);
?>
    <div class="accordion">
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> Hướng dẫn sử dụng </button></h2>
            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                <div class="accordion-body">
                    <ol>
                        <li>"<b>Khuyến khích</b>":
                            <ul>
                                <li>Rà soát các trường hợp ưu tiên của thí sinh trong môn thi này. Cần thực hiện
                                    thao tác này trước khi chia phòng.</li>
                                <li>Miễn thi KTHP: Ghi nhận điểm thi KTHP cho HVSV. Điều kiện là HVSV phải đáp ứng
                                    điều kiện dự thi KTHP (điểm quá trình, học phí).</li>
                                <li>Cộng điểm thi: Chỉ đánh dấu là có cộng điểm, chờ điểm thi mới xử lý tiếp. Điều
                                    kiện là HVSV phải đáp ứng điều kiện dự thi KTHP (điểm quá trình, học phí)</li>
                                <li>Quy đổi điểm: Ghi nhận điểm đánh giá HP cho HVSV. Tức là bao gồm
                                    cả điểm đánh giá quá trình ở lớp học phần tương ứng. Điều kiện là SV không nợ
                                    học phí.</li>
                            </ul>
                        </li>
                        <li>"<b>Chia phòng ngẫu nhiên</b>": Phân ngẫu nhiên thí sinh về các phòng thi</li>
                        <li>"<b>Chia phòng theo lớp</b>": Mỗi lớp học phần được chia về một phòng thi. Thường áp dụng
                            cho thi thực hành, vấn đáp, đồ án</li>
                        <li>"<b>Xuất</b>": Xuất danh sách thí sinh để công bố cho thí sinh</li>
                        <li>"<b>Xuất iTest</b>": Xuất file dữ liệu để tạo ca thi iTest</li>
                    </ol>
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
    </div>
<?php

$view = ViewHelper::castToEqaItemsHtmlView($this);
ViewHelper::printItemsDefaultLayout($view->getLayoutData(), $view->getListLayoutItemFields());
