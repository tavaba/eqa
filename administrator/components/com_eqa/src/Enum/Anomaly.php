<?php
namespace Kma\Component\Eqa\Administrator\Enum;

use Joomla\CMS\Language\Text;

enum Anomaly: int
{
	use EnumHelper;
	case None = 0;                  // Không có bất thường
	case Penalized25 = 11;          // Bị kỷ luật, trừ 25% điểm
	case Penalized50 = 12;          // Bị kỷ luật, trừ 50% điểm
	case Suspended = 13;            // Đình chỉ thi
	case Absent = 20;               // Vắng thi không lý do
	case Deferred = 30; 		    // Hoãn thi (Vắng có lý do)
	case Retake = 40;               // Làm lại bài thi (Sự cố khách quan)
	public function getLabel(): string
	{
		return match ($this)
		{
			self::None => 'Không',
			self::Penalized25 => 'Trừ 25%',
			self::Penalized50 => 'Trừ 50%',
			self::Suspended => 'Đình chỉ',
			self::Absent => 'Vắng thi',
			self::Deferred => 'Hoãn thi',
			self::Retake => 'Làm lại bài thi',
		};
	}
	static public function tryFromLabel(string|null $anomalyText): self|null
	{
		if(empty($anomalyText))
			return self::None;

		return match ($anomalyText)
		{
			'Không', 'Không có' => self::None,
			'K25', 'Khiển trách' => self::Penalized25,
			'K50', 'Cảnh cáo' => self::Penalized50,
			'ĐC', 'DC', 'Đình chỉ', 'Đình chỉ thi' => self::Suspended,
			'Vắng thi' => self::Absent,
			'Hoãn thi' => self::Deferred,
			'Dừng thi' => self::Retake,
			default => null
		};
	}
	static public function fromLabel(string $anomalyText): self
	{
		$result = self::tryFromLabel($anomalyText);
		if(is_null($result))
			throw new \InvalidArgumentException(Text::sprintf('Giá trị bất thường "%s" không hợp lệ', $anomalyText));
		return $result;
	}
}
