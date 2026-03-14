<?php

use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\DataObject\ExamroomInfo;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');
/**
 * @var \Kma\Component\Eqa\Administrator\View\Examroom\HtmlView $this
 */
$examroom = $this->examroom;
$anomalyField = $this->anomalyField;
$examinees = $this->examineeAnomalies;
?>
<div>
	<?php echo $examroom->getHtml();?>
</div>
<form action="" method="post" name="adminForm" id="adminForm" class="form-validate">
	<?php echo JHtml::_('form.token');?>
	<input type="hidden" name="task">
	<input type="hidden" name="examroom_id" value="<?php echo $examroom->id;?>">
	<table class="table table-striped">
		<thead>
        <tr>
            <th class="text-center">STT</th>
            <th class="text-center">SBD</th>
            <th class="text-center">Mã HVSV</th>
            <th>Họ đệm</th>
            <th>Tên</th>
            <th>Bất thường (nếu có)</th>
            <th>Ghi chú</th>
        </tr>
		</thead>
		<tbody>
		<?php $seq=1; ?>
		<?php foreach ($examinees as $examinee): ?>
		<tr>
			<td class="text-center"><?php echo $seq++; ?></td>
			<td class="text-center"><?php echo $examinee->code;?></td>
			<td class="text-center"><?php echo $examinee->learner_code;?></td>
			<td><?php echo $examinee->lastname;?></td>
			<td><?php echo $examinee->firstname;?></td>
			<td>
				<?php
				$elementName = "jform[$examinee->id][anomaly]";
				$currentValue = $examinee->anomaly;
				$fieldHtml = $anomalyField->getElementHtml($elementName, $currentValue);
				echo $fieldHtml;
				?>
			</td>
			<td>
				<?php
				$elementName = "jform[$examinee->id][description]";
				$currentValue = $examinee->description;
				$fieldHtml = "<input type=\"text\" name=\"$elementName\" value= \"$currentValue\"/>";
				echo $fieldHtml;
				?>
			</td>
		</tr>
		<?php endforeach;?>
		</tbody>
	</table>
</form>
