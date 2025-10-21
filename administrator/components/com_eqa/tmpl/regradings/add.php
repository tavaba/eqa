<?php
defined('_JEXEC') or die();
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
ViewHelper::printForm($this->form,'basic','index.php?option=com_eqa', []);
