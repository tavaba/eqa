<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Helper\ProgramHelper;
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
