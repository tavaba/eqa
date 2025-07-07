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
        <h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> Hướng dẫn upload điểm phúc khảo bài thi viết </button></h2>
        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
            <div class="accordion-body">
                File mẫu ở đây: <a href="<?php echo $sampleUrl;?>">sample_regrading_result_paper.xlsx</a><br/>
                Một số nội dung cần biết về việc upload kết quả chấm phúc khảo bài thi viết:<br/>
                <ol>
                    <li>Có thể upload đồng thời nhiều file</li>
                    <li>Mỗi file chứa một hoặc một số sheet từ file "Phiếu chấm phúc khảo bài thi viết" được tải về qua chức năng tương ứng của Hệ thống này</li>
                </ol>
            </div>
        </div>
    </div>
</div>
<hr/>
<form action="<?php echo JRoute::_('index.php?option=com_eqa');?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data" class="form-validate">
    <input type="hidden" name="task" value=""/>
	<?php echo JHtml::_('form.token'); ?>
	<?php echo $form->renderFieldset('upload'); ?>
</form>
