<?php

namespace Kma\Component\Eqa\Administrator\View\ResitSubjects;

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

/**
 * View HTML cho danh sách môn thi có trong MỘT danh sách thi lần 2.
 *
 * Mỗi hàng hiển thị một môn thi kèm thống kê số thí sinh:
 * tổng số, miễn phí, phải đóng phí, đã nộp, chưa nộp.
 *
 * Danh sách thi lần 2 được xác định bởi tham số 'resit_id' của request.
 *
 * @since 2.0.5
 */
class HtmlView extends ItemsHtmlView
{
	protected ?string $listModelName = 'ResitSubjects';

    /**
     * Mã danh sách thi lần 2 đang xem.
     *
     * @var    int
     * @since  2.1.8
     */
    protected int $resitId = 0;

    /**
     * Tên danh sách thi lần 2 đang xem.
     *
     * @var    string
     * @since  2.1.8
     */
    protected string $resitName = '';
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
     * Xác định danh sách thi lần 2 đang xem rồi nạp dữ liệu.
     *
     * @return  void
     * @since   2.1.8
     */
    protected function prepareDataForLayoutDefault(): void
    {
        $this->resitId = (int) Factory::getApplication()->getInput()->getInt('resit_id');

        if ($this->resitId <= 0) {
            die('Không xác định được danh sách thi lần 2.');
        }

        $this->resitName = DatabaseHelper::getResitName($this->resitId);

        $this->getModel()->setState('filter.resit_id', $this->resitId);

        parent::prepareDataForLayoutDefault();

        // Giữ tham số danh sách khi phân trang, sắp xếp, lọc
        $this->layoutData->formActionParams = [
            'view'    => 'ResitSubjects',
            'resit_id' => $this->resitId,
        ];
        $this->layoutData->formHiddenFields = ['resit_id' => $this->resitId];
    }

    /**
     * Toolbar cho layout default.
     *
     * Chỉ có title và một link button quay về danh sách thí sinh của cùng danh sách.
     *
     * @return void
     */
    protected function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title('Môn thi — ' . ($this->resitName ?: 'Danh sách thi lần 2'));

        $backUrl = Route::_(
            'index.php?option=com_eqa&view=ResitExaminees&resit_id=' . $this->resitId,
            false
        );
        ToolbarHelper::appendLink('core.manage', $backUrl, 'Danh sách thí sinh', 'list');
    }
}
