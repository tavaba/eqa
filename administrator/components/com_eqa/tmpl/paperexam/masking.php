<?php
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Helper\ViewHelper;

HTMLHelper::_('behavior.formvalidator');
ViewHelper::printForm($this->form,'masking');
