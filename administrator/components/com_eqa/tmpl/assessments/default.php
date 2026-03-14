<?php

/**
 * Template: Danh sách kỳ sát hạch
 *
 * @package     Com_Eqa
 * @subpackage  tmpl/assessments
 * @since       2.0.5
 */

defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Kma\Library\Kma\Helper\ViewHelper;

/**
 * @var Kma\Component\Eqa\Administrator\View\Assessments\HtmlView $this
 */

// ============================================================================
// Bổ sung options năm động vào filter form trước khi render
// Joomla Form không hỗ trợ populate list options động từ DB qua XML,
// nên ta phải inject thủ công vào đây.
// ============================================================================
if (!empty($this->layoutData->filterForm) && !empty($this->availableYears)) {
	$yearField = $this->layoutData->filterForm->getField('year', 'filter');
	if ($yearField) {
		foreach ($this->availableYears as $year) {
			$yearField->addOption((string) $year, ['value' => (string) $year]);
		}
	}
}

// ============================================================================
// Render search tools (filter bar) — Joomla chuẩn
// ============================================================================
//if (!empty($this->layoutData->filterForm)) {
//	echo LayoutHelper::render(
//		'joomla.searchtools.default',
//		['view' => $this]
//	);
//}

// ============================================================================
// Render bảng danh sách
// ============================================================================
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
