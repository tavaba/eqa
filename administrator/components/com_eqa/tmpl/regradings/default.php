<?php
defined('_JEXEC') or die();
use Kma\Library\Kma\Helper\ViewHelper;

/**
 * @var \Kma\Component\Eqa\Administrator\View\Regradings\HtmlView $this
 */
$examseasonName             = '';
$examseasonPpaaRequestDeadline = '';
if (isset($this->examseason)) {
	$examseason = $this->examseason;
	$examseasonName = htmlspecialchars($examseason->name);
	if (!empty($examseason->ppaaRequestDeadline))
		$examseasonPpaaRequestDeadline = htmlspecialchars($examseason->ppaaRequestDeadline);
}
echo "<div>Kỳ thi: <b>$examseasonName</b></div>";
echo "<div>Thời hạn phúc khảo: <b>$examseasonPpaaRequestDeadline</b></div>";

// ── Thống kê phí — lấy từ model (toàn bộ kết quả lọc, không phụ thuộc phân trang) ──
$stat = $this->paymentStatistic;
if (!empty($stat) && ($stat['paid'] + $stat['unpaid'] + $stat['free']) > 0) {
	$total        = $stat['paid'] + $stat['unpaid'] + $stat['free'];
	$paidAmount   = number_format($stat['paidAmount'],   0, ',', '.');
	$totalAmount  = number_format($stat['totalAmount'],  0, ',', '.');

	$paidBadge   = '<span class="badge bg-success">'   . $stat['paid']   . ' đã nộp</span>';
	$unpaidBadge = '<span class="badge bg-warning text-dark">' . $stat['unpaid'] . ' chưa nộp</span>';
	$freeBadge   = $stat['free'] > 0
		? ' &nbsp;<span class="badge bg-secondary">' . $stat['free'] . ' miễn phí</span>'
		: '';

	$amountHtml = ($stat['totalAmount'] > 0)
		? ' &nbsp;<span class="text-muted small">Thu được: <b>' . $paidAmount . '</b> / ' . $totalAmount . '&nbsp;đ</span>'
		: '';

	echo '<div class="mb-2 mt-1">Phí phúc khảo: '
		. $paidBadge . ' &nbsp;' . $unpaidBadge . $freeBadge . $amountHtml
		. '</div>';
}
// ─────────────────────────────────────────────────────────────────────────────

ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
