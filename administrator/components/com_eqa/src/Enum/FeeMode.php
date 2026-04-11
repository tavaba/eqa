<?php
namespace Kma\Component\Eqa\Administrator\Enum;

use Kma\Library\Kma\Enum\EnumHelper;

enum FeeMode:int
{
	use EnumHelper;
	case Free=0;          // Miễn phí
	case PerExam=10;      // Tính phí theo số môn
	case PerCredit=20;    // Tính phí theo số tín chỉ
	public function getLabel():string
	{
		return match($this)
		{
			self::Free => 'Miễn phí',
			self::PerExam => 'Tính phí theo số môn',
			self::PerCredit => 'Tính phí theo số tín chỉ',
		};
	}
}
