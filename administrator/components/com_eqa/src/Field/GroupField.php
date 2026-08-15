<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use Kma\Library\Kma\Field\StateAwareListField;
use Kma\Library\Kma\Helper\StateHelper;

/**
 * Danh sách chọn lớp hành chính.
 *
 * Lọc theo trạng thái của CẢ lớp hành chính lẫn khóa học chứa nó: khóa học đã
 * lưu trữ thì các lớp thuộc khóa đó cũng không còn dùng để tạo dữ liệu mới.
 *
 * @since 1.0
 */
class GroupField extends StateAwareListField
{
    protected $type = 'group';

    protected string $stateColumn = 'a.state';
    protected string $keyColumn   = 'a.id';

    protected function buildQuery(DatabaseInterface $db): QueryInterface
    {
        $columns = $db->quoteName(
            array('a.id', 'a.code', 'b.code', 'b.admissionyear'),
            array('id',   'code',   'course', 'admissionyear')
        );

        $query = $db->getQuery(true)
            ->from($db->quoteName('#__eqa_groups', 'a'))
            ->leftJoin($db->quoteName('#__eqa_courses', 'b') . ' ON a.course_id = b.id')
            ->select($columns)
            ->order($db->quoteName('code') . ' ASC');

        /*
         * Điều kiện trạng thái của BẢNG CHA đặt tại đây, không đặt ở $stateColumn.
         * Nhờ vậy khi StateAwareListField nạp bù giá trị đang lưu (bước xóa toàn bộ
         * mệnh đề WHERE), lớp hành chính vẫn tìm lại được kể cả khi khóa học chứa
         * nó đã bị lưu trữ.
         */
        $query->where($db->quoteName('b.state') . ' = ' . StateHelper::STATE_PUBLISHED);

        return $query;
    }

    protected function buildOptionText(object $row): string
    {
        return (string) $row->code;
    }
}
