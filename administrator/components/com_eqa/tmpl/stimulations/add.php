<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');

$form = $this->form;
?>
<div>
    <b>Lưu ý:</b><br/>
    <ol>
        <li>Điểm khuyến khích gắn liền với NGƯỜI HỌC và MÔN HỌC. Liên quan đến quy định về bảo lưu khuyến khích,
            có thể ghi nhận "khuyến khích" cả trong trường hợp HVSV chưa học môn được khuyến khích. Chính vì vậy,
            Cán bộ KT nên nhập thông tin này ngay khi có quyết định về áp dụng chế độ khuyến khích để tiện cho
            việc sử dụng sau này, mà <b>không cần</b> HVSV phải nộp đơn đề nghị áp dụng khuyến khích. </li>
        <li>Đặc biệt lưu ý trường hợp quy đổi điểm <b>tiếng Anh</b> vì chứng chỉ ngoại ngữ là có thời hạn.
            Chỉ nên nhập thông tin đổi điểm ở thời điểm phù hợp để tránh trường hợp chứng chỉ hết hạn ở
            thời điểm áp dụng việc quy đổi điểm.</li>
        <li><b>Lý do khuyến khích:</b> Nên ghi rõ số hiệu quyết định việc khuyến khích + trích yếu nội dung quyết định
            để tiện cho việc tra cứu về sau.<br/>
            Ví dụ 1: "210/QĐ-HVM, 09/10/2024, SV NCKH"<br/>
            Ví dụ 2: "123/QĐ-HVM, 20/3/2024, Đổi điểm Tiếng Anh"</li>
        <li>Nhập mã HVSV của các thí sinh được khuyến khích, cách nhau bằng dấu cách
            hoặc dấu xuống dòng, hoặc dấu phẩy, hoặc dấu chấm phẩy.</li>
    </ol>
</div>
<form action="<?php echo JRoute::_('index.php?option=com_eqa');?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    <input type="hidden" name="task" value="">
    <?php
    echo HTMLHelper::_('form.token');
    echo $this->form->renderFieldset('stimulate');
    ?>
</form>
