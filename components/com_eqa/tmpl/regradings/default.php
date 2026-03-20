<?php

use Kma\Library\Kma\Helper\ViewHelper;

defined('_JEXEC') or die();
if(empty($this->errorMessage))
{
	echo '<div class="alert alert-danger">' . $this->errorMessage . '</div>';
	return;
}

ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
