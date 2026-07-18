<?php
defined('_JEXEC') or die();

/** @var \Kma\Component\Eqa\Site\View\EmployeeMarking\HtmlView $this */

if (!empty($this->errorMessage))
{
	echo '<div class="alert alert-warning">', htmlspecialchars($this->errorMessage), '</div>';
	return;
}

// ============================================================================
// Thông tin chung
// ============================================================================
if (!empty($this->exam))
	echo '<div>Môn thi: <b>', htmlspecialchars($this->exam->name), '</b></div>';
if (!empty($this->employee))
{
	$fullName = trim($this->employee->lastname . ' ' . $this->employee->firstname);
	echo '<div>Cán bộ chấm thi: <b>', htmlspecialchars($fullName), '</b></div>';
}
?>
<div class="text-muted small mb-2">
	Bảng dưới đây hiển thị toàn bộ thí sinh của môn thi.
	Các dòng được tô màu là bài do bạn tham gia chấm.
	Với môn thi chấm trên máy, không xác định được người chấm theo từng bài thi.
</div>

<?php
// ============================================================================
// Bảng 1: Toàn bộ thí sinh của môn thi
// ============================================================================
if (empty($this->examinees))
{
	echo '<div class="alert alert-info">Không có dữ liệu thí sinh.</div>';
}
else
{
	?>
	<table class="table table-bordered table-hover table-sm">
		<thead class="table-light">
			<tr>
				<th class="text-center">STT</th>
				<th class="text-center">SBD</th>
				<th class="text-center">Số phách</th>
				<th class="text-center">Túi</th>
				<th class="text-center">Mã HVSV</th>
				<th>Họ đệm</th>
				<th>Tên</th>
				<th class="text-center">Lần thi</th>
				<th class="text-center">Điểm gốc</th>
				<th class="text-center">Bất thường</th>
				<th>Chấm 1</th>
				<th>Chấm 2</th>
			</tr>
		</thead>
		<tbody>
		<?php $seq = 0; foreach ($this->examinees as $item): $seq++; ?>
			<tr<?php echo $item->isMine ? ' class="table-primary"' : ''; ?>>
				<td class="text-center"><?php echo $seq; ?></td>
				<td class="text-center"><?php echo htmlspecialchars((string) ($item->examineeCode ?? '')); ?></td>
				<td class="text-center"><?php echo htmlspecialchars((string) ($item->mask ?? '')); ?></td>
				<td class="text-center"><?php echo htmlspecialchars((string) ($item->packageNumber ?? '')); ?></td>
				<td class="text-center"><?php echo htmlspecialchars($item->learnerCode); ?></td>
				<td><?php echo htmlspecialchars($item->lastname); ?></td>
				<td><?php echo htmlspecialchars($item->firstname); ?></td>
				<td class="text-center"><?php echo htmlspecialchars((string) ($item->attempt ?? '')); ?></td>
				<td class="text-center"><?php echo htmlspecialchars((string) ($item->markOrig ?? '')); ?></td>
				<td class="text-center"><?php echo htmlspecialchars((string) ($item->anomalyLabel ?? '')); ?></td>
				<td><?php echo htmlspecialchars((string) ($item->examiner1Name ?? '')); ?></td>
				<td><?php echo htmlspecialchars((string) ($item->examiner2Name ?? '')); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

// ============================================================================
// Bảng 2: Các bài chấm phúc khảo của cán bộ (nếu có)
// ============================================================================
if (!empty($this->regradings))
{
	?>
	<h5 class="mt-4">Chấm phúc khảo</h5>
	<table class="table table-bordered table-hover table-sm">
		<thead class="table-light">
			<tr>
				<th class="text-center">STT</th>
				<th class="text-center">Mã HVSV</th>
				<th>Họ đệm</th>
				<th>Tên</th>
				<th class="text-center">Điểm gốc</th>
				<th class="text-center">Kết quả phúc khảo</th>
				<th class="text-center">Vai trò</th>
				<th class="text-center">Trạng thái xử lý</th>
			</tr>
		</thead>
		<tbody>
		<?php $seq = 0; foreach ($this->regradings as $item): $seq++; ?>
			<tr>
				<td class="text-center"><?php echo $seq; ?></td>
				<td class="text-center"><?php echo htmlspecialchars($item->learnerCode); ?></td>
				<td><?php echo htmlspecialchars($item->lastname); ?></td>
				<td><?php echo htmlspecialchars($item->firstname); ?></td>
				<td class="text-center"><?php echo htmlspecialchars((string) ($item->markOrig ?? '')); ?></td>
				<td class="text-center"><?php echo htmlspecialchars((string) ($item->result ?? '')); ?></td>
				<td class="text-center"><?php echo htmlspecialchars($item->role); ?></td>
				<td class="text-center"><?php echo htmlspecialchars($item->statusLabel); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}
