<?php

/**
 * Template mặc định cho view ResitSubjects.
 *
 * Hiển thị danh sách môn thi có trong bảng #__eqa_resit_learner,
 * kèm thống kê số thí sinh theo từng môn.
 */

defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;

/** @var \Kma\Component\Eqa\Administrator\View\ResitSubjects\HtmlView $this */

ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
