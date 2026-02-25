<?php
defined('_JEXEC') or die();
use Kma\Library\Kma\Helper\ViewHelper;

if(!empty($this->layoutData->items)) {
    foreach ($this->layoutData->items as $key => $item) {
        if (empty($item->cocde))
            $item->code = 'N/A';
    }
}
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
