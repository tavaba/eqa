<?php

use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Interface\LearnerInfo;

defined('_JEXEC') or die();
if($this->errorMessage)
{
	echo '<div class="alert alert-danger">' . $this->errorMessage . '</div>';
	return;
}
$learner = LearnerInfo::cast($this->learner);
$examseason = $this->examseason;
?>
	<div>
		<div>Thí sinh: <?php echo $learner->code, '. ', $learner->getFullName();?></div>
		<div>Kỳ thi: <?php echo $examseason ? htmlspecialchars($examseason->name) : 'Tất cả'; ?></div>
	</div>
<?php
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
