<?php


use Kma\Component\Eqa\Administrator\Helper\ViewHelper;

defined('_JEXEC') or die();
if($this->errorMessage)
{
	echo '<div class="alert alert-danger">' . $this->errorMessage . '</div>';
	return;
}

ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
