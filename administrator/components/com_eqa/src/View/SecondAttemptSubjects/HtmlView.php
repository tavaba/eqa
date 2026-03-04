<?php

namespace Kma\Component\Eqa\Administrator\View\SecondAttemptSubjects;

defined('_JEXEC') or die();

use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

/**
 * View HTML cho danh sách môn thi có trong bảng #__eqa_secondattempts.
 *
 * Mỗi hàng hiển thị một môn thi kèm thống kê số thí sinh:
 * tổng số, miễn phí, phải đóng phí, đã nộp, chưa nộp.
 *
 * @since 2.0.5
 */
class HtmlView extends ItemsHtmlView
{
    /**
     * Khai báo các cột hiển thị cho layout default.
     *
     * Không dùng checkbox (không có tác vụ batch nào trên view này).
     * Cột total_examinees có sortable = true để hỗ trợ sắp xếp.
     *
     * @return void
     */
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();

        $option->customFieldset1 = [];

        $option->customFieldset1[] = new ListLayoutItemFieldOption(
            'exam_code', 'Mã môn', true, false, 'text-center text-nowrap'
        );
        $option->customFieldset1[] = new ListLayoutItemFieldOption(
            'exam_name', 'Tên môn'
        );

        // Cột "Số thí sinh" — sortable
        $option->customFieldset1[] = new ListLayoutItemFieldOption(
            'total_examinees', 'Số thí sinh', true, false, 'text-center'
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
        ToolbarHelper::title('Môn thi — Danh sách thi lần 2');

        $backUrl = Route::_('index.php?option=com_eqa&view=secondattempts', false);
        ToolbarHelper::appendLink('core.manage', $backUrl, 'Danh sách thí sinh', 'list');
    }
}
