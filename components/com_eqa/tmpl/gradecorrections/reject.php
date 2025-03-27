<?php

use Joomla\CMS\Toolbar\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Interface\ExamInfo;
use Kma\Component\Eqa\Administrator\Interface\LearnerInfo;
use Kma\Component\Eqa\Site\Helper\ViewHelper;

defined('_JEXEC') or die();
$action = 'index.php?option=com_eqa';
$form = $this->layoutData->form;
$formHiddenFields = $this->layoutData->formHiddenFields;
ViewHelper::printForm($this->layoutData->form, 'rejectcorrectionrequest', $action, $this->layoutData->formHiddenFields);
