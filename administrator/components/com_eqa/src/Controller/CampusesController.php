<?php

namespace Kma\Component\Eqa\Administrator\Controller;

defined('_JEXEC') or die();

use Kma\Library\Kma\Controller\AdminController;

/**
 * Controller cho danh sách 'cơ sở đào tạo' (campus).
 *
 * getModel() được khai báo tường minh vì cơ chế suy diễn tên model từ dạng số
 * nhiều của Joomla không đáng tin với danh từ tận cùng bằng '-us'
 * ('campuses' → 'campus').
 *
 * @since 2.1.6
 */
class CampusesController extends AdminController
{
    /**
     * Tiền tố language key cho các thông báo mặc định của AdminController.
     *
     * @var string
     * @since 2.1.6
     */
    protected $text_prefix = 'COM_EQA_CAMPUSES';

    /**
     * @param   string  $name
     * @param   string  $prefix
     * @param   array   $config
     *
     * @return  object
     * @since   2.1.6
     */
    public function getModel($name = 'Campus', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
