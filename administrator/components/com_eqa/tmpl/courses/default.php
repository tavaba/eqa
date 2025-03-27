<?php
defined('_JEXEC') or die();
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;

//Preprocessing
if(!empty($this->layoutData->items)){
    foreach ($this->layoutData->items as $key=>$item){
        $item->degree = CourseHelper::Degree($item->degree);
        if($item->admissionyear==0)
            $item->admissionyear=null;
    }
}

//Output
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
