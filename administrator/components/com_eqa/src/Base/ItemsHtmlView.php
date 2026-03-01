<?php

namespace Kma\Component\Eqa\Administrator\Base;
use Kma\Library\Kma\View\ItemsHtmlView as Base;
abstract class ItemsHtmlView extends Base
{
    protected function init(): void
    {
        //Call parent method
        parent::init();

        //Load scripts and styles
        $this->wa->useScript('select2.script');
        $this->wa->useStyle('select2.style');
	    $this->wa->useScript('com_eqa.script');
	    $this->wa->useStyle('com_eqa.style');
    }
}
