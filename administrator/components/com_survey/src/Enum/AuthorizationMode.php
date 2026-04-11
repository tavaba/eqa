<?php

namespace Kma\Component\Survey\Administrator\Enum;


use Kma\Library\Kma\Enum\EnumHelper;

enum AuthorizationMode : int
{
	use EnumHelper;

	case Anyone = 0;                //Bất kỳ ai (kể cả không đăng nhập)
	case Authenticated = 10;        //Người dùng đã đăng nhập
	case Respondent = 15;           //Người có trong bảng 'respondents'
	case AssignedRespondent = 20;   //Người được chỉ định


	/**
	 * Trả về nhãn
	 */
	public function getLabel(): string
	{
		return match ($this) {
			self::Anyone => 'Bất kỳ ai (kể cả không đăng nhập)',
			self::Authenticated => 'Người dùng đã đăng nhập',
			self::Respondent => 'Người có trong danh sách người được khảo sát',
			self::AssignedRespondent => 'Người trong danh sách được chỉ định'
		};
	}

}
