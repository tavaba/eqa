<?php

use Kma\Library\Kma\Helper\ViewHelper;
defined('_JEXEC') or die();

ViewHelper::printItemEditForm($this->form, $this->item->id);
