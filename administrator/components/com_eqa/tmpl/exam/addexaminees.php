<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\DataObject\ExamInfo;
use Kma\Library\Kma\Helper\ViewHelper;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');

/**
 * @var ExamInfo $exam
 */
$exam = $this->exam;
$form = $this->form;
?>
<div>
    <?php echo $exam->getHtml(['basic_info_only'=>true]);?>
    <br/>
</div>
<?php
$hiddenFields = [
	'exam_id' => $exam->id,
	'phase' => 'getdata',
];
ViewHelper::printForm($this->form,'addexamexaminees', $hiddenFields);
