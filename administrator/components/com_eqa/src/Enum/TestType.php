<?php
namespace Kma\Component\Eqa\Administrator\Enum;

use Kma\Library\Kma\Enum\EnumHelper;

enum TestType : int
{
	use EnumHelper;
	case Unknown = 0;    //Chưa xác định
	case Paper = 10;    //Tự luận
	case Project = 11;    //Đồ án
	case Thesis = 12;     //Tiểu luận
	case Practice = 13;  //Thực hành
	case Dialogue = 14;  //Vấn đáp
	case MachineObjective = 20; //Trắc nghiệm
	case MachineHybrid = 21;    //Trắc nghiệm + Tự luận trên máy
	case ComboObjectiveAndPractice = 30; //Trắc nghiệm + Thực hành
	case ObjectiveOrPaper = 51; //Tùy chọn Trắc nghiệm hoặc Tự luận
	case ObjectiveOrPractive = 52; //Tùy chọn Trắc nghiệm hoặc Thực hành
	case ObjectiveOrDialog = 53; //Tùy chọn Trắc nghiệm hoặc Vấn đáp
	case PaperOrDialog = 61; //Tùy chọn Tự luận hoặc Vấn đáp

	public static function tryFromText(string $text): TestType|null
	{
		$text = trim($text);
		if (empty($text))
			return self::Unknown;
		foreach (self::cases() as $case)
		{
			if (strcasecmp($case->getLabel(), $text) === 0 || strcasecmp($case->getLabelAbbr(), $text) === 0)
				return $case;
		}
		return null;
	}
	public function getLabel(): string
	{
		return match($this)
		{
			self::Unknown => 'Chưa xác định',
			self::Paper => 'Tự luận',
			self::Project => 'Đồ án',
			self::Thesis => 'Tiểu luận',
			self::Practice => 'Thực hành',
			self::Dialogue => 'Vấn đáp',
			self::MachineObjective => 'Trắc nghiệm',
			self::MachineHybrid => 'Trắc nghiệm + Tự luận trên máy',
			self::ComboObjectiveAndPractice => 'Trắc nghiệm + Thực hành',
			self::ObjectiveOrPaper => 'Tùy chọn Trắc nghiệm hoặc Tự luận',
			self::ObjectiveOrPractive => 'Tùy chọn Trắc nghiệm hoặc Thực hành',
			self::ObjectiveOrDialog => 'Tùy chọn Trắc nghiệm hoặc Vấn đáp',
			self::PaperOrDialog => 'Tùy chọn Tự luận hoặc Vấn đáp',
		};
	}
	public function getLabelAbbr():string
	{
		return match($this)
		{
			self::Unknown => 'N/A',
			self::Paper => 'TL',
			self::Project => 'ĐA',
			self::Thesis => 'TiL',
			self::Practice => 'TH',
			self::Dialogue => 'VĐ',
			self::MachineObjective => 'TN',
			self::MachineHybrid => 'TN+TL',
			self::ComboObjectiveAndPractice => 'TN+TH',
			self::ObjectiveOrPaper => 'TN~TL',
			self::ObjectiveOrPractive => 'TN~TH',
			self::ObjectiveOrDialog => 'TN~VĐ',
			self::PaperOrDialog => 'TL~VĐ',
		};
	}

}
