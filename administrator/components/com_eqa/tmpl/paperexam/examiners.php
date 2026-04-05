<?php
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Enum\TestType;
use Kma\Component\Eqa\Administrator\Field\EmployeeField;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\DataObject\ExamInfo;

HTMLHelper::_('behavior.formvalidator');
/**
 * @var ExamInfo $exam
 */
$exam = $this->exam;
$packages = $this->packages;
$field = new EmployeeField();

if(empty($exam)){
	echo 'Không có thông tin môn thi';
	return;
}
else
{
	echo 'Môn thi: <b>', htmlspecialchars($exam->name), '</b><br/>';
	echo 'Hình thức thi: ', TestType::from($exam->testtype)->getLabel(),'<br/>';
}
if(empty($packages)){
	echo 'Không có thông tin túi bài thi. Hãy thực hiện việc đánh phách, dồn túi!';
	return;
}
else
	echo 'Số túi: ', sizeof($packages),'<br/>';
?>
<form action="<?php echo Route::_('index.php?option=com_eqa');?>" method="POST" name="adminForm" id="adminForm" class="form-validate" >
	<input type="hidden" name="task" value=""/>
	<input type="hidden" name="exam_id" value="<?php echo $exam->id;?>">
	<?php
	echo JHtml::_('form.token');
	?>
	<table class="table table-hover">
		<thead>
            <tr>
                <th>Túi bài thi</th>
                <th>CBChT 1</th>
                <th>CBChT 2</th>
            </tr>
		</thead>
		<tbody>
		<?php
		foreach ($packages as $package){
			echo '<tr>';
			echo "<td>Túi số $package->number</td>";
			echo '<td>' . $field->getElementHtml("jform[$package->number][examiner1_id]",$package->examiner1_id) . '</td>';
			echo '<td>' . $field->getElementHtml("jform[$package->number][examiner2_id]",$package->examiner2_id) . '</td>';
			echo '</tr>';
		}
		?>
		</tbody>
	</table>
</form>
<?php
