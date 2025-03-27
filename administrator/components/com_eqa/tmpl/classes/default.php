<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\ViewHelper;

//Preprocessing
$view = ViewHelper::castToEqaItemsHtmlView($this);
$layoutData = $view->getLayoutData();

//Output
ViewHelper::printItemsDefaultLayout($layoutData, $view->getListLayoutItemFields());
