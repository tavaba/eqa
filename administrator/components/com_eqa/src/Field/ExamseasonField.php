<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Service\CampusService;
use Kma\Library\Kma\Helper\ComponentHelper;

/**
 * Danh sách chọn 'kỳ thi' (examseason).
 *
 * Đây là VAN KIỂM SOÁT trung tâm của cây tổ chức thi (2.1.6): hầu hết các view
 * con (môn thi, phòng thi, phúc tra...) lọc theo filter.examseason_id lấy từ
 * chính field này. Vì vậy chỉ cần field liệt kê đúng các kỳ thi mà người dùng
 * được phép, toàn bộ view con tự động bị giới hạn theo cơ sở đào tạo.
 *
 *  - Người dùng không thuộc cơ sở nào  → danh sách rỗng.
 *  - Người dùng thường                 → chỉ kỳ thi của cơ sở đang làm việc.
 *  - Người dùng có quyền mọi cơ sở     → mặc định kỳ thi của cơ sở đang làm
 *                                        việc; xem tất cả bằng bộ lọc cơ sở
 *                                        riêng (nếu view có).
 *
 * LƯU Ý: đây chỉ là giới hạn hiển thị. Chốt chặn quyền khi View nhận
 * examseason_id trực tiếp từ URL nằm ở CampusScopedByExamseason::assertExamseasonInCampusScope().
 *
 * @since  1.6
 */
class ExamseasonField extends ListField
{
    protected $type = 'examseason';

    /**
     * @return  array
     * @since   1.0
     */
    protected function getOptions()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'name']))
            ->from($db->quoteName('#__eqa_examseasons'))
            ->order($db->quoteName('id') . ' DESC');

        $this->applyCampusScope($query);

        $db->setQuery($query);
        $items = $db->loadObjectList();

        $options   = [];
        $options[] = HTMLHelper::_('select.option', null, ' -Chọn kỳ thi- ');
        foreach ($items as $item) {
            $options[] = HTMLHelper::_('select.option', $item->id, $item->name);
        }

        return $options;
    }

    /**
     * Giới hạn danh sách kỳ thi theo cơ sở đào tạo được phép của người dùng.
     *
     * @param   \Joomla\Database\QueryInterface  $query
     *
     * @return  void
     * @since   2.1.6
     */
    private function applyCampusScope($query): void
    {
        $db = $this->getDatabase();

        /** @var CampusService $campusService */
        $campusService = ComponentHelper::getComponent()->getCampusService();

        $allowedIds = $campusService->getUserCampusIds();

        // Không thuộc cơ sở nào → không kỳ thi nào
        if (empty($allowedIds)) {
            $query->where('1 = 0');

            return;
        }

        // Mặc định: cơ sở đang làm việc (kể cả người dùng có quyền mọi cơ sở)
        $activeCampusId = $campusService->getActiveCampusId();

        if ($activeCampusId > 0) {
            $query->where($db->quoteName('campus_id') . ' = ' . (int) $activeCampusId);

            return;
        }

        // activeCampusId không hợp lệ nhưng vẫn thuộc ít nhất một cơ sở:
        // giới hạn trong các cơ sở được phép để không lộ dữ liệu cơ sở khác
        $query->where(
            $db->quoteName('campus_id') . ' IN (' . implode(',', array_map('intval', $allowedIds)) . ')'
        );
    }
}
