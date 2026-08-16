<?php

/**
 * Template mặc định cho view ResitLearners.
 *
 * Hiển thị danh sách người học có trong bảng #__eqa_resit_learner,
 * kèm thống kê số môn thi theo từng người học.
 */

defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;

/** @var \Kma\Component\Eqa\Administrator\View\ResitLearners\HtmlView $this */

ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
