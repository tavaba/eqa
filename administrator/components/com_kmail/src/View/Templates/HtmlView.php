<?php
namespace Kma\Component\Kmail\Administrator\View\Templates;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Kma\Library\Kma\Enum\MailContextType;
use Kma\Library\Kma\View\ItemsHtmlView;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
class HtmlView extends ItemsHtmlView
{
	protected function configureItemFieldsForLayoutDefault(): void
	{
		$fields = new ListLayoutItemFields();

		$fields->sequence = ListLayoutItemFields::defaultFieldSequence();
		$fields->check    = ListLayoutItemFields::defaultFieldCheck();
		$fields->published = ListLayoutItemFields::defaultFieldPublished();

		$fields->customFieldset1 = [];

		$fields->customFieldset1[] = new ListLayoutItemFieldOption('title', 'Tên template', true, true, '');

		// Ngữ cảnh
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('contextLabel', 'Ngữ cảnh', true, false, 'text-center');

		// Tiêu đề email
		$fields->customFieldset1[] = new ListLayoutItemFieldOption(
			'subject', 'Tiêu đề email', false, false, 'text-muted small'
		);

		// Người tạo
		$fields->customFieldset1[] = new ListLayoutItemFieldOption(
			'creator_name', 'Người tạo', false, false, 'text-center small'
		);

		$this->itemFields = $fields;
	}

	// =========================================================================
	// Chuẩn bị dữ liệu
	// =========================================================================

	/**
	 * @throws Exception
	 * @since  2.0.9
	 */
	protected function prepareDataForLayoutDefault(): void
	{
		// Kiểm tra quyền
		$user = Factory::getApplication()->getIdentity();
		if (!$user->authorise('core.manage', 'com_eqa')) {
			throw new Exception('Bạn không có quyền truy cập chức năng này.', 403);
		}

		parent::prepareDataForLayoutDefault();

		//Preprocess
		if(!empty($this->layoutData->items))
		{
			foreach ($this->layoutData->items as $item)
			{
				$item->contextLabel = MailContextType::tryFrom($item->context_type)?->getLabel();
			}
		}
	}


}
