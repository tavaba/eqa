<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\ViewHelper;

ViewHelper::printItemEditLayout($this->form, $this->item->id, $this->getName());
