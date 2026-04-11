<?php
namespace Kma\Component\Eqa\Administrator\Enum;

use Kma\Library\Kma\Enum\EnumHelper;

enum SecondAttemptMarkLimitMode : int
{
	use EnumHelper;
	case NoLimit = 0;       // Không giới hạn điểm lần 2
	case OnExamMark = 1;    // Áp dụng giới hạn lên điểm thi
	case OnModuleMark = 2;  // Áp dụng giới hạn lên điểm học phần
	public function getLabel():string
	{
		return match($this)
		{
			self::NoLimit => 'Không giới hạn điểm lần 2',
			self::OnExamMark => 'Áp dụng giới hạn lên điểm thi',
			self::OnModuleMark => 'Áp dụng giới hạn lên điểm học phần',
		};
	}
}
