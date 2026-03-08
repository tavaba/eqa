<?php
namespace Kma\Component\Eqa\Administrator\Enum;

enum MarkConstituent: int
{
	use EnumHelper;
	case Pam1 = 10;       // Điểm quá trình 1 (thường là điểm chuyên cần hoặc bài tập nhỏ)
	case Pam2 = 20;       // Điểm quá trình 2 (thường là điểm kiểm tra giữa kỳ)
	case Pam = 30;     // Tổng điểm quá trình (Tổng hợp của các điểm PAM)
	case FinalExam = 100;   // Điểm thi kết thúc học phần
	case All = 120;         // Tất cả các loại điểm (Tổng điểm học phần)

	/*
	 * Lấy mô tả tiếng Việt cho từng thành phần điểm
	 */
	public function getLabel(): string {
		return match($this) {
			self::Pam1 => 'Điểm quá trình 1',
			self::Pam2 => 'Điểm quá trình 2',
			self::Pam => 'Tổng điểm quá trình',
			self::FinalExam => 'Điểm thi kết thúc học phần',
			self::All => 'Tất cả thành phần điểm',
		};
	}
}