<?php

defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;

/**
 * Template: Form chỉnh sửa cơ sở đào tạo
 *
 * @package     Com_Eqa
 * @subpackage  tmpl/campus
 * @since       2.1.6
 *
 * @var Kma\Component\Eqa\Administrator\View\Campus\HtmlView $this
 */

ViewHelper::printItemEditForm($this->form, $this->item->id);
