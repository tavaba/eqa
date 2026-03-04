<?php

namespace Kma\Component\Eqa\Administrator\View\SecondAttemptLearners;

defined('_JEXEC') or die();

use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

/**
 * View HTML cho danh sách người học có trong bảng #__eqa_secondattempts.
 *
 * Mỗi hàng hiển thị một người học kèm thống kê số môn thi:
 * tổng số, miễn phí, phải đóng phí, đã nộp, chưa nộp.
 *
 * @since 2.0.5
 */
class HtmlView extends ItemsHtmlView
{
    /**
     * Khai báo các cột hiển thị cho layout default.
     *
     * Không dùng checkbox (view chỉ đọc, không có tác vụ batch).
     * Các cột learner_code, learner_firstname, total_subjects có sortable = true.
     *
     * @return void
     */
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();

        $option->customFieldset1 = [];

        $option->customFieldset1[] = new ListLayoutItemFieldOption(
            'learner_code', 'Mã HVSV', true, false, 'text-center text-nowrap font-monospace'
        );
        $option->customFieldset1[] = new ListLayoutItemFieldOption(
            'learner_lastname', 'Họ đệm'
        );
        $option->customFieldset1[] = new ListLayoutItemFieldOption(
            'learner_firstname', 'Tên', true
        );

        // Cột "Số môn" — sortable
        $option->customFieldset1[] = new ListLayoutItemFieldOption(
            'total_subjects', 'Số môn', true, false, 'text-center'
        );
        $option->customFieldset1[] = new ListLayoutItemFieldOption(
            'total_free', 'Miễn phí', false, false, 'text-center'
        );
        $option->customFieldset1[] = new ListLayoutItemFieldOption(
            'total_paid_required', 'Có phí', false, false, 'text-center'
        );
        $option->customFieldset1[] = new ListLayoutItemFieldOption(
            'total_paid', 'Đã nộp', false, false, 'text-center'
        );
        $option->customFieldset1[] = new ListLayoutItemFieldOption(
            'total_unpaid', 'Chưa nộp', false, false, 'text-center'
        );

        $this->itemFields = $option;
    }

    /**
     * Toolbar cho layout default.
     *
     * Chỉ có title và một link button quay về view SecondAttempts.
     *
     * @return void
     */
    protected function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title('Người học — Danh sách thi lần 2');

        $backUrl = Route::_('index.php?option=com_eqa&view=secondattempts', false);
        ToolbarHelper::appendLink('core.manage', $backUrl, 'Danh sách thí sinh', 'list');
    }
}
