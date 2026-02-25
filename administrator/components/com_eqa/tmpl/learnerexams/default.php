<?php
defined('_JEXEC') or die();
use Kma\Library\Kma\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Interface\LearnerInfo;

if(isset($this->learner))
{
	$learner = LearnerInfo::cast($this->learner);
	$text = $learner->code . ': ' . $learner->getFullName();
	echo '<div>', htmlspecialchars($text), '</div>';
}

ViewHelper::printItemsDefaultLayout($this->getLayoutData(), $this->getListLayoutItemFields());
