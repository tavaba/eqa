<?php

use Joomla\CMS\HTML\HTMLHelper;
use Kma\Library\Kma\Helper\ViewHelper;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');
ViewHelper::printForm($this->form,'examsessions');
