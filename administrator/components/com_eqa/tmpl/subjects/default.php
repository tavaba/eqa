<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
