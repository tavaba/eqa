<?php
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;


HTMLHelper::_('behavior.formvalidator');

$samplePath = 'media/com_eqa/xlsx/sample_classes.xls';
$sampleUrl = JUri::root().$samplePath;
$formAction = JRoute::_('index.php?option=com_eqa');
?>
    <div class="accordion">
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> Hướng dẫn nạp điểm quá trình </button></h2>
            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                <div class="accordion-body">
                    File mẫu ở đây: <a href="<?php echo $sampleUrl;?>">sample_pam.xls</a><br/>
                    Một số nội dung cần biết về việc nạp điểm quá trình:<br/>
                    <ol>
                        <li>
                            File có định dạng Microsoft Excel (.xls hoặc .xlsx) được xuất từ Hệ thống Quản lý đào tạo với các đặc điểm sau:
                            <ul>
                                <li>Có thể có nhiều sheet, mỗi sheet là một lớp học phần</li>
                                <li>M6 chứa mã môn học</li>
                                <li>C7 chứa tên lớp học phần</li>
                                <li>D8 chứa tên giảng viên (nếu có)</li>
                                <li>B15 chứa mã HVSV đầu tiên</li>
                                <li>Điểm TP1 tại cột I, điểm TP2 tại cột J và điểm quá trình tại cột K</li>
                                <li>Ghi chú tại cột M</li>
                                <li>Sau HVSV cuối cùng phải có ít nhất một dòng trống</li>
                            </ul>
                        </li>
                        <li>Có thể nạp đồng thời nhiều file cho nhiều lớp, nhiều môn học khác nhau</li>
                        <li>Tùy chọn "<b>Bỏ qua lớp trắng</b>": Một số giảng viên nhập điểm quá trình cho lớp của mình nhưng vẫn để nguyên,
                            không xóa các lớp khác (ở các lớp này, cột điểm quá trình sẽ trống rỗng). Tùy chọn này cho phép
                            bỏ qua các lớp trống như vậy. Nếu không kích hoạt tùy chọn này mà gặp SV không có điểm quá trình
                            thì sẽ phát sinh lỗi. Lưu ý rằng, một lớp được xác định là "trắng" nếu sinh viên đầu tiên không
                            có điểm quá trình. Nếu sinh viên đầu tiên có điểm quá trình thì lớp không được coi là trắng; nếu
                            phía dưới có sinh viên không có điểm quá trình thì sẽ phát sinh lỗi.
                        </li>
                        <li>Tùy chọn "<b>Hoàn thành điểm quá trình</b>": Một số GV chỉ nhập TP1 và TP2 mà không tính ra điểm quá trình.
                            Tùy chọn này cho phép tự động tính điểm quá trình theo công thức mặc định 0.7*TP1 + 0.3*TP2.</li>
                        <li>Tùy chọn "<b>Hôm nay là ngày bàn giao ĐQT</b>": sau khi nạp điểm, ứng dụng sẽ kiểm tra, nếu 100% HVSV
                            của lớp học đều đã có ĐQT thì sẽ ghi nhận hôm nay là ngày bàn giao ĐQT của lớp tương ứng.</li>
                        <li>KHÔNG cập nhật (sửa) điểm cho HVSV đã có điểm quá trình</li>
                        <li>Việc nạp điểm quá trình sẽ đồng thời xác định điều kiện dự thi cho HVSV theo quy định hiện hành</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
<hr/>
<form action="<?php echo $formAction;?>" method="post" enctype="multipart/form-data" name="adminForm" id="adminForm" class="form-validate">
    <input type="hidden" name="task" value=""/>
    <?php echo JHtml::_('form.token');?>

    <?php
    if(isset($this->form)){
        echo $this->form->renderFieldset('uploadpam');
    }
    ?>
</form>
<?php
