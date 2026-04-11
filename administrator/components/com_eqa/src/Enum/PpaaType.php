<?php
namespace Kma\Component\Eqa\Administrator\Enum;
use Kma\Library\Kma\Enum\EnumHelper;

enum PpaaType: int {
	use EnumHelper;
	case None = 0;          // Không có
	case Review = 10;       // Phúc khảo (Sinh viên yêu cầu chấm lại bài)
	case Correction = 20;   // Điều chỉnh/Sửa đổi (Giáo viên hoặc phòng đào tạo phát hiện sai sót cần sửa điểm)
	case Moderation = 30;   // Chấm kiểm tra (Nhà trường chủ động hậu kiểm để đảm bảo chất lượng)
	/*
	 * Lấy mô tả tiếng Việt
	 */
	public function getLabel(): string {
		return match($this) {
			self::None => 'Không có',
			self::Review => 'Chấm phúc khảo',
			self::Correction => 'Đính chính sai sót',
			self::Moderation => 'Chấm kiểm tra',
		};
	}
}