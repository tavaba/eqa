<?php

/**
 * Template mặc định cho view SecondAttemptLearners.
 *
 * Hiển thị danh sách người học có trong bảng #__eqa_secondattempts,
 * kèm thống kê số môn thi theo từng người học.
 */

defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;

/** @var \Kma\Component\Eqa\Administrator\View\SecondAttemptLearners\HtmlView $this */

ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
