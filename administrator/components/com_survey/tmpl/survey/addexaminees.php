<?php

use Kma\Library\Kma\Helper\ViewHelper;

defined('_JEXEC') or die();
$survey = $this->item;
echo 'Chọn một kỳ thi để thêm thí sinh vào cuộc khảo sát <b>' . htmlspecialchars($survey->title) . '</b>';
$hiddenFields = [
    'survey_id' => $survey->id
];
ViewHelper::printForm($this->form, 'basic',$hiddenFields);