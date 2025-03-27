<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Helper\ProgramHelper;

if(!empty($this->layoutData->items)) {
    foreach ($this->layoutData->items as $item) {
        $item->degree = CourseHelper::Degree($item->degree);
        $item->format = ProgramHelper::format($item->format);
        $item->approach = ProgramHelper::approach($item->approach);
    }
}

ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
