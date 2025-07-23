<?php
defined('_JEXEC') or die();
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Interface\LearnerInfo;

if(isset($this->learner))
{
	$learner = LearnerInfo::cast($this->learner);
	$text = $learner->code . ': ' . $learner->getFullName();
	echo '<div>', htmlspecialchars($text), '</div>';
}

$view = ViewHelper::castToEqaItemsHtmlView($this);
ViewHelper::printItemsDefaultLayout($view->getLayoutData(), $view->getListLayoutItemFields());
