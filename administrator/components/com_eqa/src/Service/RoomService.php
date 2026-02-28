<?php

namespace Kma\Component\Eqa\Administrator\Service;

defined('_JEXEC') or die();

use Joomla\CMS\Factory;

/**
 * Service cung cấp các thông tin cơ bản về phòng (room) của com_eqa.
 *
 * Dữ liệu toàn bộ phòng được lazy-load một lần duy nhất trong vòng đời
 * của đối tượng, tránh truy vấn DB lặp lại khi dùng cho danh sách dài.
 *
 * Cách dùng:
 *   $roomService = new RoomService();
 *   $fullCode    = $roomService->getFullCode(5);   // "TA1-104"
 *   $capacity    = $roomService->getCapacity(5);   // 40
 *
 * @since 2.0.1
 */
class RoomService
{
    /**
     * Cache nội bộ: room_id (int) → object { full_code: string, capacity: int }
     *   null  = chưa load lần nào
     *   array = đã load (có thể rỗng nếu bảng trống)
     *
     * @var array<int, object>|null
     * @since 2.0.1
     */
    private ?array $roomMap = null;

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Trả về mã đầy đủ của phòng theo định dạng "{building_code}-{room_code}".
     *
     * Ví dụ: phòng code "104" thuộc tòa nhà code "TA1" → "TA1-104".
     *
     * @param  int    $roomId ID của phòng trong bảng #__eqa_rooms.
     * @return string Mã đầy đủ nếu tìm thấy; chuỗi rỗng nếu không tìm thấy.
     * @since 2.0.1
     */
    public function getFullCode(int $roomId): string
    {
        $this->ensureLoaded();

        return $this->roomMap[$roomId]->full_code ?? '';
    }

    /**
     * Trả về sức chứa thi (capacity) của phòng.
     *
     * Đây là số chỗ ngồi được phép dùng khi tổ chức thi (cột `capacity`),
     * có thể nhỏ hơn sức chứa tối đa (cột `maxcapacity`).
     *
     * @param  int $roomId ID của phòng trong bảng #__eqa_rooms.
     * @return int Sức chứa nếu tìm thấy; 0 nếu không tìm thấy.
     * @since 2.0.1
     */
    public function getCapacity(int $roomId): int
    {
        $this->ensureLoaded();

        return (int) ($this->roomMap[$roomId]->capacity ?? 0);
    }

    // =========================================================================
    // Internal
    // =========================================================================

    /**
     * Load toàn bộ dữ liệu phòng vào cache nội bộ nếu chưa load.
     *
     * JOIN `#__eqa_rooms` với `#__eqa_buildings` để ghép sẵn
     * full_code = "{building_code}-{room_code}" trong SQL.
     * Kết quả được index theo cột 'id' để truy cập O(1).
     *
     * @return void
     * @since 2.0.1
     */
    private function ensureLoaded(): void
    {
        if ($this->roomMap !== null) {
            return;
        }

        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('r.id'),
                $db->quoteName('r.capacity'),
                'CONCAT('
                    . $db->quoteName('b.code')
                    . ', \'-\', '
                    . $db->quoteName('r.code')
                    . ') AS ' . $db->quoteName('full_code'),
            ])
            ->from($db->quoteName('#__eqa_rooms', 'r'))
            ->leftJoin(
                $db->quoteName('#__eqa_buildings', 'b')
                . ' ON ' . $db->quoteName('b.id')
                . ' = ' . $db->quoteName('r.building_id')
            );

        $db->setQuery($query);

        // loadObjectList('id') → index theo cột 'id', truy cập O(1)
        $this->roomMap = $db->loadObjectList('id') ?? [];
    }
}
