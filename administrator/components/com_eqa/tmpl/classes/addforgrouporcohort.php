<?php
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;


HTMLHelper::_('behavior.formvalidator');

$formAction = JRoute::_('index.php?option=com_eqa');
?>
    <div class="accordion">
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> Hướng dẫn tạo lớp học phần </button></h2>
            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                <div class="accordion-body">
                </div>
            </div>
        </div>
    </div>
<hr/>
<form action="<?php echo $formAction;?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    <input type="hidden" name="task" value=""/>
    <?php echo JHtml::_('form.token');?>

    <?php
    if(isset($this->form)){
        echo $this->form->renderFieldset('basic');
    }
    ?>
</form>
<?php
