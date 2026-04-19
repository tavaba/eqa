<?php

/**
 * Template: Danh sách thí sinh sát hạch của một phòng thi.
 *
 * @var \Kma\Component\Eqa\Administrator\View\Examroom\HtmlView $this
 * @since 2.0.6
 */

use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\ViewHelper;

defined('_JEXEC') or die();

$examroom   = $this->examroom;
?>

	<!-- Header: thông tin phòng thi sát hạch -->
	<div class="card mb-3">
		<div class="card-body">
			<?php if ($examroom->isAssessmentRoom): ?>
				<p class="mb-1">
					<strong>Kỳ sát hạch:</strong>
					<?php echo htmlspecialchars($examroom->assessmentTitle); ?>
				</p>
			<?php endif; ?>
			<?php if (!empty($examroom)): ?>
				<?php
				$dayOfWeek = DatetimeHelper::getDayOfWeek($examroom->examTime);
				$examTime  = DatetimeHelper::getDayAndTime($examroom->examTime);
				?>
				<p class="mb-1">
					<strong>Ca thi:</strong>
					<?php echo htmlspecialchars($examroom->examsession); ?>
					(<?php echo $dayOfWeek; ?>, <?php echo $examTime; ?>)
				</p>
				<p class="mb-0">
					<strong>Phòng thi:</strong>
					<b><?php echo htmlspecialchars($examroom->name); ?></b>
					&nbsp;&nbsp;
					<strong>Số thí sinh:</strong>
					<?php echo (int) $examroom->examineeCount; ?>
				</p>
			<?php endif; ?>
		</div>
	</div>

<?php
ViewHelper::printItemsDefaultLayout($this->listLayoutData, $this->listLayoutItemFields);