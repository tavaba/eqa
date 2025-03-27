<?php
defined('_JEXEC') or die();
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Helper\RoomHelper;

//Preprocessing
if(!empty($this->layoutData->items)) {
    foreach ($this->layoutData->items as $item) {
        $item->type = RoomHelper::roomType($item->type);
    }
}

ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
