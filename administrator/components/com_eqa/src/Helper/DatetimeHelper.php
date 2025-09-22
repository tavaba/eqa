<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use DateTime;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Matrix\Exception;

abstract class DatetimeHelper{
	const TERM_NONE = 0;
	const TERM_1 = 1;
	const TERM_2 = 2;
	const TERM_3 = 3;
	const TERM_SUMMER = 9;
	public static function decodeTerm(int $term): string
	{
		return match ($term) {
			self::TERM_NONE => 'Cả năm',
			self::TERM_1 => 'Học kỳ 1',
			self::TERM_2 => 'Học kỳ 2',
			self::TERM_3 => 'Học kỳ 3',
			self::TERM_SUMMER=>'Học kỳ hè'
		};
	}
	public static function getTerms(): array
	{
		return [
			self::TERM_NONE => self::decodeTerm(self::TERM_NONE),
			self::TERM_1 => self::decodeTerm(self::TERM_1),
			self::TERM_2 => self::decodeTerm(self::TERM_2),
			self::TERM_3 => self::decodeTerm(self::TERM_3),
			self::TERM_SUMMER=>self::decodeTerm(self::TERM_SUMMER),
		];
	}
	static public function getSigningDateString(string $place='Hà Nội', string $datetimeString='now'):string
	{
		$datetime = new DateTime($datetimeString);
		$s = $place;
		$s .= ', ngày ' . $datetime->format('d');
		$s .= ' tháng ' . $datetime->format('m');
		$s .= ' năm ' . $datetime->format('Y');
		return $s;
	}
    static public function getDayOfWeek(string $datetimeString): string
    {
        $date = new Date($datetimeString);
        return $date->format('l',true);
    }
    static public function getHourAndMinute(string $datetimeString):string
    {
        $datetime = new DateTime($datetimeString);
        return $datetime->format('H') . ':' . $datetime->format('i');
    }
	static public function getDayAndMonth(string $datetimeString):string
	{
		$datetime = new DateTime($datetimeString);
		return $datetime->format('d') . '/' . $datetime->format('m') ;
	}
	static public function getDayAndTime(string $datetimeString):string
	{
		$datetime = new DateTime($datetimeString);
		$res = $datetime->format('d') . '/' . $datetime->format('m');
		$res .= '-';
		$res .= $datetime->format('H') . ':' . $datetime->format('i');
		return $res;
	}
    static public function getFullDate(string $datetimeString):string
    {
        $datetime = new DateTime($datetimeString);
        return $datetime->format('d') . '/' . $datetime->format('m') . '/' . $datetime->format('Y');
    }
	static public function isValidDate($date, $format = 'Y-m-d') {
		$d = DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) === $date;
	}
	static public function isWeekend(string $datetimeString){
		$date = new DateTime($datetimeString);

		// Format 'N' returns a number for the day of the week (1 = Monday, 7 = Sunday)
		$dayOfWeek = $date->format('N');

		// Check if it's Saturday (6) or Sunday (7)
		return $dayOfWeek >= 6;
	}
	static public function getCurrentHanoiDatetime():string
	{
		$tz = new \DateTimeZone("Asia/Ho_Chi_Minh");
		$dt = new \DateTime();
		$dt->setTimeZone($tz);
		return $dt->format('Y-m-d H:i:s');
	}
}


