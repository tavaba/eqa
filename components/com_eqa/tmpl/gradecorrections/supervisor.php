<?php

use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\ConfigHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Site\Helper\ViewHelper;

defined('_JEXEC') or die();

ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
