<?php

namespace Kma\Component\Survey\Administrator\Base;
use Kma\Library\Kma\View\ItemHtmlView as Base;
abstract class ItemHtmlView extends Base
{
    protected function init(): void
    {
        //Call parent method
        parent::init();

        //Load scripts and styles
        $this->wa->useScript('select2.script');
        $this->wa->useStyle('select2.style');
        $this->wa->useScript('com_survey.script');
        $this->wa->useStyle('com_survey.style');
    }
}
