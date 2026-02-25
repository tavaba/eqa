<?php
defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;

//Preprocessing
$layoutData = $this->getLayoutData();
$getListLayoutItemFields = $this->getListLayoutItemFields();

//Output
ViewHelper::printItemsDefaultLayout($layoutData, $getListLayoutItemFields);
