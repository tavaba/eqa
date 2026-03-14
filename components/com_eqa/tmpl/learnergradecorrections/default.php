<?php

use Kma\Library\Kma\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\DataObject\LearnerInfo;

defined('_JEXEC') or die();
/**
 * @var \Kma\Component\Eqa\Site\View\Learnergradecorrections\HtmlView $this
 */
if($this->errorMessage)
{
	echo '<div class="alert alert-danger">' . $this->errorMessage . '</div>';
	return;
}
$learner = $this->learner;
$examseason = $this->examseason;
?>
<div>
	<div>Thí sinh: <?php echo $learner->code, '. ', $learner->getFullName();?></div>
	<div>Kỳ thi: <?php echo $examseason ? htmlspecialchars($examseason->name) : 'Tất cả'; ?></div>
</div>
<?php
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
