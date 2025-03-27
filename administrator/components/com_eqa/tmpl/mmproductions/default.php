<?php
defined('_JEXEC') or die();
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
$view = ViewHelper::castToEqaItemsHtmlView($this);
ViewHelper::printItemsDefaultLayout($view->getLayoutData(), $view->getListLayoutItemFields());
