<?php

use Kma\Library\Kma\Helper\ViewHelper;

defined('_JEXEC') or die();
$survey = $this->item;
echo '<h1>' . htmlspecialchars($survey->title) . '</h1>';
ViewHelper::printItemsDefaultLayout($this->listLayoutData, $this->listLayoutItemFields);
