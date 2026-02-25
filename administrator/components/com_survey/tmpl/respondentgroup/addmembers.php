<?php

use Kma\Library\Kma\Helper\ViewHelper;

defined('_JEXEC') or die();
$group = $this->item;
echo '<div>';
echo '<div> Tên nhóm: <b>', htmlspecialchars($group->name),'</b></div>';
echo '<div> Mô tả: <b>', htmlspecialchars($group->description),'</b></div>';
echo '</div>';
ViewHelper::printItemsDefaultLayout($this->listLayoutData, $this->itemFields);
