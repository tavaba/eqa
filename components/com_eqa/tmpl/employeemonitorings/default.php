<?php
defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;

/** @var \Kma\Component\Eqa\Site\View\EmployeeMonitorings\HtmlView $this */

if (!empty($this->errorMessage))
{
	echo '<div class="alert alert-warning">', htmlspecialchars($this->errorMessage), '</div>';
	return;
}

// Thông tin cán bộ
if (!empty($this->employee))
{
	$fullName = trim($this->employee->lastname . ' ' . $this->employee->firstname);
	echo '<div>Cán bộ: <b>', htmlspecialchars($fullName), '</b>';
	if (!empty($this->employee->unit))
		echo ' (', htmlspecialchars($this->employee->unit), ')';
	echo '</div>';
}
?>
<h5 class="mt-3">Thống kê theo kỳ thi</h5>
<div class="text-muted small mb-2">Bấm vào tên kỳ thi để xem chi tiết các lượt của kỳ thi đó.</div>
<?php
// Bảng thống kê (danh sách chính: có searchtools + pagination)
ViewHelper::printItemsDefaultLayout($this->getLayoutData(), $this->getListLayoutItemFields());

// Bảng chi tiết của kỳ thi đang chọn
if (!empty($this->selectedExamseason))
{
	?>
	<h5 class="mt-4">Chi tiết các lượt — <?php echo htmlspecialchars($this->selectedExamseason->name); ?></h5>
	<?php
	if (empty($this->details))
	{
		echo '<div class="alert alert-info">Không có lượt nào trong kỳ thi này.</div>';
	}
	else
	{
		?>
		<table class="table table-bordered table-hover table-sm">
			<thead class="table-light">
				<tr>
					<th class="text-center">STT</th>
					<th class="text-center">Thời gian</th>
					<th>Ca thi</th>
					<th class="text-center">Phòng</th>
					<th>Phòng thi</th>
                    <th>Môn thi</th>
                    <th class="text-center">Vai trò</th>
				</tr>
			</thead>
			<tbody>
			<?php $seq = 0; foreach ($this->details as $detail): $seq++; ?>
				<tr>
					<td class="text-center"><?php echo $seq; ?></td>
					<td class="text-center"><?php echo htmlspecialchars($detail->startText); ?></td>
					<td><?php echo htmlspecialchars($detail->sessionName); ?></td>
					<td class="text-center"><?php echo htmlspecialchars($detail->roomCode); ?></td>
					<td><?php echo htmlspecialchars($detail->examroomName); ?></td>
                    <td><?php echo $detail->examsHtml; ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($detail->role); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
?>
<script>
	/**
	 * Chọn kỳ thi cần xem chi tiết: set giá trị cho field lọc examseason_id
	 * trong searchtools của adminForm rồi submit form. Nếu vì lý do nào đó
	 * field không tồn tại (ví dụ bị gỡ khỏi filter form), tạo input ẩn thay thế.
	 */
	function eqaSelectExamseason(examseasonId)
	{
		var form = document.getElementById('adminForm');
		if (!form) {
			return false;
		}
		var field = form.querySelector('[name="filter[examseason_id]"]');
		if (!field) {
			field = document.createElement('input');
			field.type = 'hidden';
			field.name = 'filter[examseason_id]';
			form.appendChild(field);
		}
		field.value = examseasonId;
		form.submit();
		return false;
	}
</script>
