<?php

namespace Kma\Component\Eqa\Administrator\View\Resits;

defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

/**
 * View HTML cho danh sách các 'danh sách thi lần 2'.
 *
 * Mỗi dòng là một danh sách thí sinh thi lần 2 của cơ sở đào tạo, kèm số liệu
 * thống kê. Nhấn vào tên danh sách để mở danh sách thí sinh bên trong.
 *
 * @since 2.1.8
 */
class HtmlView extends ItemsHtmlView
{
    /**
     * Chỉ định tường minh vì tên model gồm nhiều từ theo dạng CamelCase.
     *
     * @var    string|null
     * @since  2.1.8
     */
    protected ?string $listModelName = 'Resits';

    /**
     * Khai báo các cột hiển thị cho layout default.
     *
     * @return  void
     * @since   2.1.8
     */
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check    = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = [];

        // Tên danh sách — nhấn vào để mở danh sách thí sinh bên trong
        $f                  = new ListLayoutItemFieldOption('name', 'Tên danh sách', true);
        $f->urlFormatString = 'index.php?option=com_eqa&view=ResitExaminees&resit_id=%d';
        $option->customFieldset1[] = $f;

        // Trạng thái kích hoạt (đã được tiền xử lý thành badge trong prepareData)
        $f           = new ListLayoutItemFieldOption('active_label', 'Kích hoạt', false, false, 'text-center');
        $f->printRaw = true;
        $option->customFieldset1[] = $f;

        $option->customFieldset1[] = new ListLayoutItemFieldOption(
            'total_attempts', 'Tổng số lượt', true, false, 'text-center'
        );
        $option->customFieldset1[] = new ListLayoutItemFieldOption(
            'total_learners', 'HVSV', false, false, 'text-center'
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
        $option->customFieldset1[] = new ListLayoutItemFieldOption(
            'created_at', 'Ngày tạo', true, false, 'text-center text-nowrap'
        );
        $option->customFieldset1[] = new ListLayoutItemFieldOption('description', 'Mô tả');

        $option->state = ListLayoutItemFields::defaultFieldState();

        $this->itemFields = $option;
    }

    /**
     * Nạp dữ liệu và tiền xử lý từng dòng.
     *
     * @return  void
     * @since   2.1.8
     */
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        if (empty($this->layoutData->items)) {
            return;
        }

        foreach ($this->layoutData->items as $item) {
            $item->active_label = $item->active
                ? '<span class="badge bg-success">Đang kích hoạt</span>'
                : '<span class="badge bg-secondary">Không</span>';

            // Thời gian lưu trong CSDL là UTC, hiển thị theo giờ địa phương
            $item->created_at = empty($item->created_at)
                ? ''
                : DatetimeHelper::convertToLocalTime($item->created_at, null, 'd/m/Y H:i');
        }
    }

    /**
     * Toolbar cho layout default.
     *
     * @return  void
     * @since   2.1.8
     */
    protected function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title('Danh sách thi lần 2');
        ToolbarHelper::appendGoHome();

        ToolbarHelper::appendButton(
            'core.create',
            'plus-2',
            'Tạo danh sách mới',
            'resit.add',
            false,
            'btn btn-success'
        );

        ToolbarHelper::appendButton(
            'core.edit',
            'pencil-2',
            'Sửa',
            'resit.edit',
            true
        );

        $msg = 'Mỗi cơ sở đào tạo chỉ có một danh sách đang kích hoạt. Đó là danh sách mà thí sinh '
            . 'đăng ký thi lại vào, đồng thời là căn cứ để tạo môn thi lần 2 và rà soát việc nộp phí. '
            . 'Bạn có chắc muốn kích hoạt danh sách đã chọn?';
        ToolbarHelper::appendConfirmButton(
            'core.edit',
            $msg,
            'checkmark-circle',
            'Kích hoạt',
            'resits.activate',
            true,
            'btn btn-primary'
        );

        $msg = 'Xóa danh sách sẽ xóa TOÀN BỘ thí sinh và thông tin đóng phí thuộc danh sách đó. '
            . 'Hành động này không thể hoàn tác. Bạn có chắc muốn xóa?';
        ToolbarHelper::appendDelete('resits.delete', 'Xóa', $msg);
    }
}
