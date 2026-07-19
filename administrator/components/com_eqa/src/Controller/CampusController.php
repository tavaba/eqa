<?php

namespace Kma\Component\Eqa\Administrator\Controller;

defined('_JEXEC') or die();

use Kma\Library\Kma\Controller\FormController;

/**
 * Controller cho form chỉnh sửa 'cơ sở đào tạo' (campus).
 *
 * $view_list được khai báo tường minh vì cơ chế suy diễn dạng số nhiều của
 * Joomla không đáng tin với danh từ tận cùng bằng '-us'.
 *
 * @since 2.1.6
 */
class CampusController extends FormController
{
    /**
     * Tên view danh sách để quay về sau khi lưu/hủy.
     *
     * @var string
     * @since 2.1.6
     */
    protected $view_list = 'campuses';
}
