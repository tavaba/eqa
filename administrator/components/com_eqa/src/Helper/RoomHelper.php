<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;

abstract class RoomHelper{
    static protected $roomList;
    public const ROOM_TYPE_LECTURE=0;
    public const ROOM_TYPE_LECTURE_WITH_OUTLETS=1;
    public const ROOM_TYPE_COMPUTER=2;
    public const ROOM_TYPE_OUTSIDE=3;

    static protected function buildRoomList(){
        $db = DatabaseHelper::getDatabaseDriver();
        $db->setQuery('SELECT id, code FROM #__eqa_rooms');
        self::$roomList = $db->loadAssocList('id','code');
    }

    /**
     * Hàm này dịch từ mã loại phòng (lưu trong CSDL về khóa học, bảng #__eqa_rooms) thành tên loại phòng
     * @param int $type   Hằng số quy ước cho cấp học (định nghĩa theo danh mục cấp IV của Bộ GD&ĐT)
     * @return string|null  Tên cấp học (dịch từ tập tin language) tương ứng với hằng số
     * @since 1.0
     */
    static public function roomType(int $type): string|null
    {
        return match ($type) {
            self::ROOM_TYPE_LECTURE => Text::_('COM_EQA_CONST_ROOM_TYPE_LECTURE'),
            self::ROOM_TYPE_LECTURE_WITH_OUTLETS => Text::_('COM_EQA_CONST_ROOM_TYPE_LECTURE_WITH_OUTLETS'),
            self::ROOM_TYPE_COMPUTER => Text::_('COM_EQA_CONST_ROOM_TYPE_COMPUTER'),
            self::ROOM_TYPE_OUTSIDE => Text::_('COM_EQA_CONST_ROOM_TYPE_OUTSIDE'),
            default => null,
        };
    }

    /**
     * Hàm này trả về mảng thông tin các loại phòng trong đó $key là mã loại phòng được lưu trong CSDL
     * ở bảng #__eqa_rooms, còn $value là tên loại phoòng được dịch từ tập tin ngôn ngữ.
     * @return array    Mỗi phần tử $key=>$value ứng với $key là mã loại phòng, $value là tên loại phòng
     * @since 1.0
     */
    static public function getRoomTypes(): array
    {
        $types = array();
        $types[self::ROOM_TYPE_LECTURE] = self::roomType(self::ROOM_TYPE_LECTURE);
        $types[self::ROOM_TYPE_LECTURE_WITH_OUTLETS] = self::roomType(self::ROOM_TYPE_LECTURE_WITH_OUTLETS);
        $types[self::ROOM_TYPE_COMPUTER] = self::roomType(self::ROOM_TYPE_COMPUTER);
        $types[self::ROOM_TYPE_OUTSIDE] = self::roomType(self::ROOM_TYPE_OUTSIDE);
        return $types;
    }

    static public function getRoomCode(int $id){
        if(empty(self::$roomList))
            self::buildRoomList();
        if(array_key_exists($id, self::$roomList))
            return self::$roomList[$id];
        return null;
    }
}

