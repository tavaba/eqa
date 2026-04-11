<?php
namespace Kma\Library\Kma\View;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;

/**
 * View danh sách Mail Template.
 *
 * URL: index.php?option=com_eqa&view=mailtemplates
 *
 * @since 2.0.9
 */
abstract class MailTemplatesHtmlView extends ItemsHtmlView
{

	// =========================================================================
	// Abstract methods that child class must override
	// =========================================================================
	abstract protected function getContextTypeLabel(int $contextType): ?string;


	// =========================================================================
    // Cấu hình cột
    // =========================================================================

    protected function configureItemFieldsForLayoutDefault(): void
    {
        $fields = new ListLayoutItemFields();

        $fields->sequence = ListLayoutItemFields::defaultFieldSequence();
        $fields->check    = ListLayoutItemFields::defaultFieldCheck();
		$fields->published = ListLayoutItemFields::defaultFieldPublished();

        $fields->customFieldset1 = [];

        // Tên template (link sang form edit)
        $f           = new ListLayoutItemFieldOption('title_link', 'Tên template', true, false, '');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

        // Ngữ cảnh
        $f           = new ListLayoutItemFieldOption('context_label', 'Ngữ cảnh', true, false, 'text-center');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

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

        // Preprocessing items
        foreach ($this->layoutData->items as &$item) {
            $this->preprocessItem($item);
        }
        unset($item);
    }

    /**
     * @since 2.0.9
     */
    private function preprocessItem(object &$item): void
    {
        // Link sang form edit
        $editUrl = Route::_(
            'index.php?option=com_eqa&view=mailtemplate&layout=edit&id=' . (int) $item->id,
            false
        );
        $item->title_link =
            '<a href="' . $editUrl . '">' . htmlspecialchars($item->title) . '</a>';

        // Ngữ cảnh: badge theo context_type
	    $contextLabel = $this->getContextTypeLabel((int) $item->context_type);
        $item->context_label =
            '<span class="badge bg-info text-dark">' . $contextLabel . '</span>';

    }

}
