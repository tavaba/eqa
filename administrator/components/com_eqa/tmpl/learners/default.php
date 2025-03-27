<?php
defined('_JEXEC') or die();
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
?>
<?php

//Preprocession
if(!empty($this->layoutData->items)) {
    foreach ($this->layoutData->items as $item) {
        if ($item->admissionyear == 0)
            $item->admissionyear = null;
    }
}

ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
