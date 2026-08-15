<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Library\Kma\Field\StateAwareListField;

/**
 * Danh sách chọn môn học.
 *
 * Nhãn của option dùng TÊN PHÂN BIỆT để quản trị viên không bị nhầm giữa các
 * môn học trùng tên chính thức nhưng khác mã, khác nội dung. (2.1.7)
 *
 * Kế thừa StateAwareListField nên môn học đã lưu trữ tự động biến mất khỏi danh
 * sách, nhưng nếu bản ghi đang sửa vẫn trỏ tới môn học đó thì option tương ứng
 * vẫn được nạp bù — tránh mất khóa ngoại khi lưu. (2.1.7)
 *
 * @since 1.0
 */
class SubjectField extends StateAwareListField
{
    protected $type = 'subject';

    protected string $stateColumn = 'a.state';
    protected string $keyColumn   = 'a.id';

    protected function buildQuery(DatabaseInterface $db): QueryInterface
    {
        return $db->getQuery(true)
            ->select('a.id, a.unit_id, a.code')
            ->select(DatabaseHelper::displayNameExpr('a') . ' AS ' . $db->quoteName('name'))
            ->from($db->quoteName('#__eqa_subjects', 'a'))
            ->order($db->quoteName('a.code') . ' ASC');
    }

    protected function buildOptionText(object $row): string
    {
        return $row->code . ' - ' . $row->name;
    }
}
