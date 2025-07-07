<?php
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
HTMLHelper::_('behavior.formvalidator');

//Preprocessing
$form = $this->layoutData->form;

$samplePath = 'media/com_eqa/xlsx/sample_regrading_result_paper.xlsx';
$sampleUrl = JUri::root().$samplePath;
?>
<div class="accordion">
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingOne"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne"> Hướng dẫn upload điểm phúc khảo bài thi hỗn hợp iTest </button></h2>
        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
            <div class="accordion-body">
                Hiện tại, hệ thống iTest chưa có chức năng xuất riêng kết quả phúc khảo
                nên cần download file kết quả đầy đủ của môn thi từ iTest để upload lên
                hệ thống này. Hệ thống sẽ tự động tìm kiếm trong bảng điểm đầy đủ đó điểm
                của các thí sinh có trong danh sách phùc khảo.<br/>
                Lưu ý:
                <ol>
                    <li><span style="font-size: x-large; font-weight: bold; color: red;">Chọn đúng môn thi!</span>
                        Do trong tập tin xuất ra từ iTest không có thông tin về môn thi nên môn thi phải được
                        xác định thủ công. Nếu chọn nhầm môn thi, điểm của thí sinh ở môn thi đó sẽ bị sai lệch do
                        bị thay thế bởi điểm từ file được tải lên.</li>
                    <li>Cần đảm bảo rằng việc chấm phúc khảo trên iTest đã hoàn tất</li>
                    <li>Nếu môn thi gồm nhiều ca thi thì phải download đồng thời kết quả
                        của tất cả các ca thi trong 1 file</li>
                </ol>
            </div>
        </div>
    </div>
</div>
<hr/>
<form action="<?php echo JRoute::_('index.php?option=com_eqa');?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data" class="form-validate">
    <input type="hidden" name="task" value=""/>
	<?php echo JHtml::_('form.token'); ?>
	<?php echo $form->renderFieldset('upload_itest'); ?>
</form>
