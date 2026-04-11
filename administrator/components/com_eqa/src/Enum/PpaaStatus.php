<?php
namespace Kma\Component\Eqa\Administrator\Enum;

use Kma\Library\Kma\Enum\EnumHelper;

enum PpaaStatus: int {
	use EnumHelper;
	case Init = 0;              // Trạng thái khởi tạo / Mới tiếp nhận
	case Accepted = 20;         // Đã được chấp nhận / Phê duyệt
	case RequireInfo = 25;      // Yêu cầu bổ sung thêm thông tin/minh chứng
	case Rejected = 30;         // Từ chối (Không đủ điều kiện xử lý)
	case Done = 40;             // Đã hoàn tất xử lý (đã có kết quả cuối cùng, không còn thay đổi)

	/*
	 * Lấy mô tả tiếng Việt cho trạng thái hậu kiểm/phúc khảo
	 */
	public function getLabel(): string {
		return match($this) {
			self::Init => 'Khởi tạo',
			self::Accepted => 'Đã chấp nhận',
			self::RequireInfo => 'Yêu cầu bổ sung thông tin',
			self::Rejected => 'Từ chối',
			self::Done => 'Đã hoàn tất',
		};
	}
}