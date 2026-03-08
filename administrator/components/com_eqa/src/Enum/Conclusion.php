<?php
namespace Kma\Component\Eqa\Administrator\Enum;

enum Conclusion: int
{
	use EnumHelper;
	case Ineligible = 5;           //Không đủ điều kiện thi
	case Passed = 10;               //Qua môn, hết lượt thi
	case RetakeExam = 20;               //Không qua môn, thi lại
	case RetakeCourse = 21;     //Không qua môn, hết lượt thi
	case Postponed = 30;             //Bảo lưu lượt thi
	public function getLabel(): string
	{
		return match($this)
		{
			self::Ineligible => 'Không được thi',
			self::Passed => 'Đạt',
			self::RetakeExam => 'Thi lại',
			self::RetakeCourse => 'Học lại',
			self::Postponed => 'Bảo lưu',
		};
	}
}