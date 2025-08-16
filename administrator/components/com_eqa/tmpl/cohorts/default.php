<?php
defined('_JEXEC') or die();
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;

ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
