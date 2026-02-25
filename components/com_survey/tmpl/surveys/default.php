<?php

use Kma\Library\Kma\Helper\ViewHelper;

defined('_JEXEC') or die();

ViewHelper::printItemsDefaultLayout($this->getLayoutData(), $this->getListLayoutItemFields());
