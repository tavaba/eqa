<?php

/**
 * Template 'edit' cho view Resit: form tạo/sửa danh sách thi lần 2.
 */

defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;

/** @var \Kma\Component\Eqa\Administrator\View\Resit\HtmlView $this */

ViewHelper::printItemEditForm($this->form, $this->item->id);
