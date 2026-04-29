<?php
defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;

ViewHelper::printItemEditForm($this->form, $this->item->id);
