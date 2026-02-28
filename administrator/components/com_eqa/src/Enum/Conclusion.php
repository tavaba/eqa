<?php
namespace Kma\Component\Eqa\Administrator\Enum;

enum Conclusion: int
{
	use EnumHelper;
	case Passed = 10;               //Qua môn, hết lượt thi
	case Failed = 20;               //Không qua môn, thi lại
	case FailedAndExpired = 21;     //Không qua môn, hết lượt thi
	case Deferred = 30;             //Bảo lưu lượt thi
	public function getLabel(): string
	{
		return match($this)
		{
			self::Passed => 'Đạt',
			self::Failed => 'Thi lại',
			self::FailedAndExpired => 'Học lại',
			self::Deferred => 'Bảo lưu',
		};
	}
}