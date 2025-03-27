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
}


