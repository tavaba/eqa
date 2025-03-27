<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Interface\ExamInfo;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');
$form = $this->form;
$urlAnomaly = JRoute::_('index.php?option=com_eqa&view=examrooms', false);
?>
<div>
    Một số lưu ý đối với việc nhập kết quả thi từ hệ thống iTest:
    <ol>
        <li>Cần <a href="<?php echo $urlAnomaly;?>">nhập thông tin bất thường phòng thi</a>
            <span style="font-weight: bold; color:red;">TRƯỚC KHI</span> khi thực hiện nhập điểm.
            Nếu không, kết quả xử lý điểm sẽ bị sai lệch. Tuy nhiên, cần lưu ý rằng điểm xuất từ iTest là điểm
            sau khi đã xử lý kỷ luật (trừ 25%, trừ 50%, trừ 100%). Do vậy, chỉ nhập thông tin bất thường cho
            các trường hợp "<b>Đình chỉ thi</b>" (để không cho thi tiếp), "<b>Hoãn thi</b>" và "<b>Dừng thi,
            làm lại bài thi</b>" (để bảo lưu lượt thi cho thí sinh).</li>
        <li>Trong file kết quả không có thông tin về môn thi nên cần chọn chính xác tên
            môn thi để đảm bảo không làm sai lệch điểm trong CSDL của hệ thống</li>
        <li>Thí sinh không có điểm thi sẽ được ghi điểm là 0</li>
        <li>Khi nhập điểm thi, hệ thống cũng sẽ tự động tính toán điểm học phần, tự động giới hạn
            điểm đối với các trường hợp thi lần 2, tự động kết luận Đạt/Không đạt, được thi tiếp hay không.</li>
        <li>Riêng đối với bài thi <b>Tiếng Anh</b> (và các môn mà không tự động quy đổi ra thang điểm 10)
            thì cần xử lý điểm thủ công, sử dụng mẫu biên bản thi thực hành để nhập điểm</li>
    </ol>
</div>
<form action="<?php echo JRoute::_('index.php?option=com_eqa');?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data" class="form-validate">
    <input type="hidden" name="task" value="">
    <?php
    echo HTMLHelper::_('form.token');
    echo $this->form->renderFieldset('upload_itest');
    ?>
</form>
