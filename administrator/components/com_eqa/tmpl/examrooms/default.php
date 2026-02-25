<?php
defined('_JEXEC') or die();
use Kma\Library\Kma\Helper\ViewHelper;
ViewHelper::printItemsDefaultLayout($this->getLayoutData(), $this->getListLayoutItemFields());
