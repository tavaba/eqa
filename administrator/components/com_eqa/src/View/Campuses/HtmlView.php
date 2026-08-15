<?php

namespace Kma\Component\Eqa\Administrator\View\Campuses; // Namespace phải kết thúc bằng TÊN VIEW

defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

/**
 * View danh sách 'cơ sở đào tạo' (campus).
 *
 * @since 2.1.6
 */
class HtmlView extends ItemsHtmlView
{
    /**
     * Cấu hình các cột hiển thị của bảng danh sách.
     *
     * @return  void
     * @since   2.1.6
     */
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check    = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = [];

        $field = new ListLayoutItemFieldOption('code', 'Ký hiệu', true, true);
        $field->cellCssClasses = 'text-center';
        $option->customFieldset1[] = $field;

        $option->customFieldset1[] = new ListLayoutItemFieldOption('name', 'Tên cơ sở đào tạo', true, true);

        $field = new ListLayoutItemFieldOption('userCount', 'Số tài khoản', true, false);
        $field->cellCssClasses = 'text-center';
        $option->customFieldset1[] = $field;

        $option->customFieldset1[] = new ListLayoutItemFieldOption('description', 'Mô tả');

        $option->state = ListLayoutItemFields::defaultFieldState();

        $this->itemFields = $option;
    }

    /**
     * Khai báo tường minh tiền tố task của toolbar.
     *
     * Class cha suy diễn tiền tố bằng cách chuyển tên view sang dạng số ít
     * ('campuses' → ?), vốn không đáng tin với danh từ tận cùng bằng '-us'.
     *
     * @return  void
     * @since   2.1.6
     */
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        $this->toolbarOption->taskPrefixItem  = 'campus';
        $this->toolbarOption->taskPrefixItems = 'campuses';
        $this->toolbarOption->title           = 'Quản lý cơ sở đào tạo';
    }
}
