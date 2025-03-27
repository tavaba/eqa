<?php
defined('_JEXEC') or die();
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Helper\UnitHelper;

if(!empty($this->layoutData->items)) {
    foreach ($this->layoutData->items as $item) {
        $item->type = UnitHelper::UnitType($item->type);
    }
}
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
