<?php

use Kma\Library\Kma\Helper\ViewHelper;

defined('_JEXEC') or die();
$cohort = $this->item;
$hiddenFields = [
        'cohort_id' => $cohort->id,
];
ViewHelper::printForm($this->form,'basic', $hiddenFields);
