<?php

defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;

/**
 * Template: Danh sách cơ sở đào tạo
 *
 * @package     Com_Eqa
 * @subpackage  tmpl/campuses
 * @since       2.1.6
 *
 * @var Kma\Component\Eqa\Administrator\View\Campuses\HtmlView $this
 */

ViewHelper::printItemsDefaultLayout($this->getLayoutData(), $this->getListLayoutItemFields());
