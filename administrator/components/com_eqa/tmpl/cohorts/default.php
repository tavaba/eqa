<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\CampusHelper;
use Kma\Library\Kma\Helper\ViewHelper;
echo CampusHelper::renderSwitcher();
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
