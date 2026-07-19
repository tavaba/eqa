<?php

namespace Kma\Component\Eqa\Administrator\View\Campus; // Namespace phải kết thúc bằng TÊN VIEW

defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\ItemHtmlView;

/**
 * View chỉnh sửa 'cơ sở đào tạo' (campus).
 *
 * @since 2.1.6
 */
class HtmlView extends ItemHtmlView
{
    /**
     * Khai báo tường minh tiền tố task và tiêu đề toolbar.
     *
     * @return  void
     * @since   2.1.6
     */
    protected function prepareDataForLayoutEdit(): void
    {
        parent::prepareDataForLayoutEdit();

        $this->toolbarOption->taskPrefixItem  = 'campus';
        $this->toolbarOption->taskPrefixItems = 'campuses';
        $this->toolbarOption->title = empty($this->item->id)
            ? 'Thêm cơ sở đào tạo'
            : 'Chỉnh sửa cơ sở đào tạo';
    }
}
