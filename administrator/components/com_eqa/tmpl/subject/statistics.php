<?php
defined('_JEXEC') or die();

/**
 * Layout thống kê một môn học.
 *
 * Hiển thị:
 *   1. Header thông tin môn học
 *   2. Summary cards: số lớp, lượt người học, lượt thi đạt, tỉ lệ đạt, điểm TB
 *   3. Bảng điểm trung bình và tỉ lệ đạt theo khóa đào tạo (tối đa 10 khóa)
 *
 * @var \Kma\Component\Eqa\Administrator\View\Subject\HtmlView $this
 * @since 2.0.8
 */


$stats   = $this->statistics;
$subject = $stats->subject;

/**
 * Helper nội bộ: format số thực, trả '-' nếu null.
 *
 * @param  float|null $val
 * @param  int        $decimals
 * @return string
 */
$fmt = static function (?float $val, int $decimals = 2): string {
	return $val !== null ? number_format($val, $decimals, ',', '.') : '—';
};

/**
 * Helper nội bộ: tạo badge Bootstrap cho tỉ lệ đạt.
 * Xanh lá ≥ 80%, vàng ≥ 50%, đỏ < 50%, '—' nếu null.
 *
 * @param  float|null $rate  Giá trị phần trăm (0–100)
 * @return string HTML
 */
$passRateBadge = static function (?float $rate) use ($fmt): string {
	if ($rate === null) {
		return '<span class="text-muted">—</span>';
	}
	$color = $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger');
	return '<span class="badge bg-' . $color . '">'
		. $fmt($rate, 1) . ' %'
		. '</span>';
};
?>

<?php /* =====================================================================
   1. HEADER — thông tin cơ bản môn học
   ===================================================================== */ ?>
	<div class="card mb-3 border-primary">
		<div class="card-body py-2">
			<div class="row align-items-center g-2">
				<div class="col-auto">
                <span class="badge bg-primary fs-6 font-monospace">
                    <?= htmlspecialchars($subject->code ?? '') ?>
                </span>
				</div>
				<div class="col">
					<h5 class="mb-0"><?= htmlspecialchars($subject->name ?? '') ?></h5>
				</div>
				<?php if (!empty($subject->credits)): ?>
					<div class="col-auto text-muted small">
						<?= (int) $subject->credits ?> tín chỉ
					</div>
				<?php endif; ?>
				<?php if (!empty($subject->degree)): ?>
					<div class="col-auto">
                <span class="badge bg-secondary">
                    <?= htmlspecialchars($subject->degree) ?>
                </span>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

<?php /* =====================================================================
   2. SUMMARY CARDS
   ===================================================================== */ ?>
	<div class="row g-2 mb-4">

		<?php /* --- Số lớp học phần --- */ ?>
		<div class="col-6 col-md-4 col-xl-2">
			<div class="card text-center border-secondary h-100">
				<div class="card-body py-3 px-1">
					<div class="fs-3 fw-bold text-secondary">
						<?= $stats->class_count ?>
					</div>
					<div class="small text-muted">Lớp học phần</div>
				</div>
			</div>
		</div>

		<?php /* --- Số lượt người học --- */ ?>
		<div class="col-6 col-md-4 col-xl-2">
			<div class="card text-center border-info h-100">
				<div class="card-body py-3 px-1">
					<div class="fs-3 fw-bold text-info">
						<?= $stats->enrollment_count ?>
					</div>
					<div class="small text-muted">Lượt người học</div>
				</div>
			</div>
		</div>

		<?php /* --- Số lượt thi đạt --- */ ?>
		<div class="col-6 col-md-4 col-xl-2">
			<div class="card text-center border-success h-100">
				<div class="card-body py-3 px-1">
					<div class="fs-3 fw-bold text-success">
						<?= $stats->passed_count ?>
					</div>
					<div class="small text-muted">
						Đạt
						<?php if ($stats->concluded_count > 0): ?>
							<span class="text-muted">/ <?= $stats->concluded_count ?></span>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<?php /* --- Tỉ lệ đạt --- */ ?>
		<div class="col-6 col-md-4 col-xl-2">
			<div class="card text-center border-warning h-100">
				<div class="card-body py-3 px-1">
					<div class="fs-3 fw-bold">
						<?php if ($stats->pass_rate !== null): ?>
							<?= $fmt($stats->pass_rate, 1) ?> %
						<?php else: ?>
							<span class="text-muted fs-5">—</span>
						<?php endif; ?>
					</div>
					<div class="small text-muted">Tỉ lệ đạt</div>
				</div>
			</div>
		</div>

		<?php /* --- Điểm thi trung bình --- */ ?>
		<div class="col-6 col-md-4 col-xl-2">
			<div class="card text-center border-primary h-100">
				<div class="card-body py-3 px-1">
					<div class="fs-3 fw-bold text-primary">
						<?= $fmt($stats->avg_final_mark) ?>
					</div>
					<div class="small text-muted">Điểm thi TB</div>
				</div>
			</div>
		</div>

		<?php /* --- Điểm học phần trung bình --- */ ?>
		<div class="col-6 col-md-4 col-xl-2">
			<div class="card text-center border-dark h-100">
				<div class="card-body py-3 px-1">
					<div class="fs-3 fw-bold">
						<?= $fmt($stats->avg_module_mark) ?>
					</div>
					<div class="small text-muted">Điểm học phần TB</div>
				</div>
			</div>
		</div>

	</div>

<?php /* =====================================================================
   3. BẢNG THỐNG KÊ THEO KHÓA ĐÀO TẠO
   ===================================================================== */ ?>
<?php if (!empty($stats->by_course)): ?>
	<div class="card">
		<div class="card-header">
			<strong>Thống kê theo khóa đào tạo</strong>
			<span class="text-muted small ms-2">(<?= count($stats->by_course) ?> khóa gần nhất)</span>
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table table-sm table-hover table-bordered mb-0 align-middle">
					<thead class="table-light text-center">
					<tr>
						<th class="text-start">Khóa</th>
						<th>Nhập học</th>
						<th>Lượt học</th>
						<th>Đã kết luận</th>
						<th>Số đạt</th>
						<th>Tỉ lệ đạt</th>
						<th>Điểm thi TB</th>
						<th>Điểm QT TB</th>
						<th>Điểm HP TB</th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($stats->by_course as $row): ?>
						<tr>
							<td class="font-monospace fw-semibold">
								<?= htmlspecialchars($row->course_code ?? '—') ?>
							</td>
							<td class="text-center">
								<?= $row->admission_year !== null ? (int) $row->admission_year : '—' ?>
							</td>
							<td class="text-center"><?= (int) $row->enrollment_count ?></td>
							<td class="text-center"><?= (int) $row->concluded_count ?></td>
							<td class="text-center"><?= (int) $row->passed_count ?></td>
							<td class="text-center"><?= $passRateBadge($row->pass_rate) ?></td>
							<td class="text-center"><?= $fmt($row->avg_final_mark) ?></td>
							<td class="text-center"><?= $fmt($row->avg_pam) ?></td>
							<td class="text-center"><?= $fmt($row->avg_module_mark) ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<?php /* Hàng tổng hợp */ ?>
					<tfoot class="table-light fw-semibold">
					<tr>
						<td colspan="2" class="text-end text-muted small">Tổng / TB toàn bộ:</td>
						<td class="text-center"><?= $stats->enrollment_count ?></td>
						<td class="text-center"><?= $stats->concluded_count ?></td>
						<td class="text-center"><?= $stats->passed_count ?></td>
						<td class="text-center"><?= $passRateBadge($stats->pass_rate) ?></td>
						<td class="text-center"><?= $fmt($stats->avg_final_mark) ?></td>
						<td class="text-center"><?= $fmt($stats->avg_pam) ?></td>
						<td class="text-center"><?= $fmt($stats->avg_module_mark) ?></td>
					</tr>
					</tfoot>
				</table>
			</div>
		</div>
	</div>
<?php else: ?>
	<div class="alert alert-info">
		<span class="icon-info-circle me-1"></span>
		Chưa có dữ liệu thi cho môn học này.
	</div>
<?php endif; ?>