<?php
namespace Kma\Component\Eqa\Administrator\Enum;

use Kma\Library\Kma\Enum\EnumHelper;

enum ExamType: int {
	use EnumHelper;
	case Other = 0;                     // Thi khác
	case SubjectFinalTest = 1;          // Thi kết thúc học phần
	case Certification = 2;             // Thi sát hạch (đầu vào, đầu ra, lấy chứng chỉ...)
	case Graduation = 3;                // Thi tốt nghiệp

	/*
	 * Lấy tên hiển thị tiếng Việt
	 */
	public function getLabel(): string {
		return match($this) {
			self::Other => 'Thi khác',
			self::SubjectFinalTest => 'Thi kết thúc học phần',
			self::Certification => 'Thi sát hạch',
			self::Graduation => 'Thi tốt nghiệp',
		};
	}
}