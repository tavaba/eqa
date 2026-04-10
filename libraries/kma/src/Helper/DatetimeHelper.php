<?php
namespace Kma\Library\Kma\Helper;
defined('_JEXEC') or die();

use DateTime;
use DateTimeZone;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Exception;

abstract class DatetimeHelper
{
	/**
     * Encode an academic year into an integer. The input must be a string with
     * four digits separated by either a hyphen or a dash. For example,
     * "2020-2021" will be encoded as 2020 and "2021_2022" will also be encoded
     * as 2021.
     *
     * @param string $academicYear A string representing an academic year
     * in the form YYYY[-_]YYYY where YYYY represents a four-digit year.
     * @return int An integer representation of the academic year.
     * @throws Exception
     * @since 1.0.0
     */
    public static function encodeAcademicYear(string $academicYear):int
    {
        //Remove any spaces in the academic year string ($academicYear).
        $academicYear = str_replace(' ','',$academicYear);

        //Check that the academic year string contains exactly two numbers
        //separated by a hyphen or a dash.
        $pattern = '/^(\d{4})[_\-](\d{4})$/';
        preg_match($pattern,$academicYear,$matches);
        $isValid = count($matches)==3 && (int)$matches[1]+1 == (int)$matches[2];
        if(!$isValid)
            throw new Exception('Invalid academic year format. Please use XXXX-YYYY format.');

        return (int)$matches[1];
    }
    public static function decodeAcademicYear(int $encodedAcademicYear, string $oneCharSeparator='-'):string
    {
        return $encodedAcademicYear.$oneCharSeparator.($encodedAcademicYear+1);
    }

	/**
	 * Convert a local time string to a UTC time string, using the specified timezone.
	 * If the timezone is not provided, it will be detected from the operating system's configuration.
	 *
	 * @param string $localTime A date and time string representing the local time.
	 * @param string|null $timezone An optional timezone identifier (e.g. 'Asia/Ho_Chi_Minh').
	 *                             If null, the method will use the user timezone.
	 * @param string $format The desired output format for the UTC time string. Default is 'Y-m-d H:i:s'.
	 *
	 * @return string A date and time string representing the corresponding UTC time, formatted according to the specified format.
	 * @since 1.0.2
	 * @throws Exception
	 */
	public static function convertToUtc(string $localTime, ?DateTimeZone $timezone = null, string $format = 'Y-m-d H:i:s'): string
	{
		$timezone ??= self::getUserTimezone();

		$dt = new DateTime($localTime, $timezone);
		$dt->setTimezone(new DateTimeZone('UTC'));

		return $dt->format($format);
	}

	/**
	 * Convert a UTC time string to a local time string, using the specified timezone.
	 * If the timezone is not provided, it will be detected from the operating system's configuration.
	 *
	 * @param string $utcTime A date and time string representing the UTC time.
	 * @param string|null $timezone An optional timezone identifier (e.g. 'Asia/Ho_Chi_Minh').
	 *                             If null, the timezone of the logged in user is used.
	 * @param string $format The desired output format for the local time string. Default is 'Y-m-d H:i:s'.
	 *
	 * @return string A date and time string representing the corresponding local time, formatted according to the specified format.
	 * @since 1.0.2
	 * @throws Exception
	 */
	public static function convertToLocalTime(string $utcTime, ?DateTimeZone $timezone = null, string $format = 'Y-m-d H:i:s'): string
	{
		$timezone ??= self::getUserTimezone();

		$dt = new DateTime($utcTime, new DateTimeZone('UTC'));
		if($timezone)
			$dt->setTimezone($timezone);

		return $dt->format($format);
	}

	public static function getUserTimezone():?DateTimeZone
	{
		$user = Factory::getApplication()->getIdentity();
		return $user?->getTimezone();
	}

	/**
	 * @param   DateTimeZone|null  $timezone The local timezone, if null the timezone of
	 *                                       the logged in user is used.
	 * @param   string             $format
	 *
	 * @return string
	 *
	 * @since 1.0.3
	 */
	public static function getCurrentTime(?DateTimeZone $timezone = null, string $format='Y-m-d H:i:s'):string
	{
		$timezone ??= self::getUserTimezone();
		$dt = new DateTime('now', $timezone);
		return $dt->format($format);
	}

	/**
	 * Get the current date and time in UTC.
	 *
	 * @param   string  $format  A date format string compatible with PHP's date() function.
	 * Default is 'Y-m-d H:i:s'.
	 *
	 * @return  string  The current UTC date and time formatted according to the specified format.
	 * @since   1.0.3
	 * @throws  Exception
	 */
	public static function getCurrentUtcTime(string $format = 'Y-m-d H:i:s'): string
	{
		$tz = new DateTimeZone('UTC');
		return (new DateTime('now', $tz))->format($format);
	}

	public static function getSigningDateString(string $place='Hà Nội', string $datetimeString='now'):string
	{
		$timezone = self::getUserTimezone();
		$datetime = new DateTime($datetimeString, $timezone);
		$s = $place;
		$s .= ', ngày ' . $datetime->format('d');
		$s .= ' tháng ' . $datetime->format('m');
		$s .= ' năm ' . $datetime->format('Y');
		return $s;
	}
    public static function getDayOfWeek(string $datetimeString): string
    {
        $date = new Date($datetimeString);
        return $date->format('l',true);
    }
    public static function getHourAndMinute(string $datetimeString):string
    {
        $datetime = new DateTime($datetimeString);
        return $datetime->format('H') . ':' . $datetime->format('i');
    }
	public static function getDayAndMonth(string $datetimeString):string
	{
		$datetime = new DateTime($datetimeString);
		return $datetime->format('d') . '/' . $datetime->format('m') ;
	}
	public static function getDayAndTime(string $datetimeString):string
	{
		$datetime = new DateTime($datetimeString);
		$res = $datetime->format('d') . '/' . $datetime->format('m');
		$res .= '-';
		$res .= $datetime->format('H') . ':' . $datetime->format('i');
		return $res;
	}
    public static function getYear(string $datetimeString):int
    {
        $datetime = new DateTime($datetimeString);
        return intval($datetime->format('Y'));
    }
    public static function getFullDate(string $datetimeString):string
    {
        $datetime = new DateTime($datetimeString);
        return $datetime->format('d') . '/' . $datetime->format('m') . '/' . $datetime->format('Y');
    }
	public static function isValidDate($date, $format = 'Y-m-d'): bool
	{
		$d = DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) === $date;
	}
	public static function isWeekend(string $datetimeString): bool
	{
		$date = new DateTime($datetimeString);

		// Format 'N' returns a number for the day of the week (1 = Monday, 7 = Sunday)
		$dayOfWeek = $date->format('N');

		// Check if it's Saturday (6) or Sunday (7)
		return $dayOfWeek >= 6;
	}
    public static function isTimeOver_bak(string $timestamp):bool
    {
        $hanoiTimezone = 'Asia/Ho_Chi_Minh';
        $inputTime = new Date($timestamp, $hanoiTimezone);
        $currentTime = new Date('now');
        return $inputTime < $currentTime;
    }

	/**
	 * @param   string  $timestamp  A date/time string that can be parsed by DateTime,
	 *                              representing the time to compare against the current time.
	 * @param   string  $timezone   An optional timezone identifier (e.g. 'Asia/Ho_Chi_Minh')
	 *                              to specify the timezone of the input timestamp.
	 *
	 * @return bool Returns true if the input timestamp is in the past compared to the current time
	 * (i.e., the time has passed), or false if it is in the future
	 * or exactly the same as the current time.
	 *
	 * @since 1.0.0
	 */
	public static function isTimeOver(string $timestamp, string|DateTimeZone $timezone = 'UTC'): bool
	{
		if(is_string($timezone))
			$timezone = new DateTimeZone($timezone);

		$inputDt = new DateTime($timestamp, $timezone);
		$nowDt   = new DateTime('now', $timezone);
		return $inputDt < $nowDt;
	}

    /**
     * Converts Vietnamese day of week to English day of week
     * @param string $vietnameseDayOfWeek
     * @return string
     * @since 1.0.0
     */
    public static function toEnglishDayOfWeek(string $vietnameseDayOfWeek):string
    {
        $input = strtolower(trim($vietnameseDayOfWeek));
        return match ($input) {
            'thứ hai', 'thứ 2' => 'Monday',
            'thứ ba', 'thứ 3' => 'Tuesday',
            'thứ tư', 'thứ 4' => 'Wednesday',
            'thứ năm', 'thứ 5' => 'Thursday',
            'thứ sáu', 'thứ 6' => 'Friday',
            'thứ bảy', 'thứ 7' => 'Saturday',
            'chủ nhật' => 'Sunday',
            default => '',
        };
    }

    /**
     * Get the first date when a given event occurs within a given time period.
     * @param string $periodStart A date string in the specified format, e.g. 27/02/2025
     * @param string $periodEnd A date string in the specified format, e.g. 30/4/2025
     * @param string $eventDay The name of the day of the week when the event occurs. It should be one of:
     *              Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, Sunday
     * @param string $inputFormat
     * @return DateTime
     * @throws Exception
     * @since 1.0.0
     */
    public static function getFirstEventDate(string $periodStart, string $periodEnd, string $eventDay, string $inputFormat='d/m/Y'):DateTime
    {
        $start = DateTime::createFromFormat($inputFormat, $periodStart)->setTime(0, 0, 0);
        $end = DateTime::createFromFormat($inputFormat, $periodEnd)->setTime(0, 0, 0);
        if (!$start || !$end || $start>$end)
            throw new Exception('Invalid period date');

        $first = $start;
        if ($first->format('l') !== $eventDay)
            $first = $first->modify("next $eventDay");
        if ($first > $end)
        {
            $msg = sprintf('getFirstEventDate: Invalid event schedule: start=%s, end=%s, eventDay=%s',
                $periodStart, $periodEnd, $eventDay);
            throw new Exception($msg);
        }
        return $first;
    }

    /**
     * Get the first date when a given event occurs within a given time period.
     * @param string $periodStart A date string in the specified format, e.g. 27/02/2025
     * @param string $periodEnd A date string in the specified format, e.g. 30/4/2025
     * @param string $eventDay The name of the day of the week when the event occurs. It should be one of:
     *              Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, Sunday
     * @param string $inputFormat
     * @return DateTime
     * @throws Exception
     * @since 1.0.0
     */
    public static function getLastEventDate(string $periodStart, string $periodEnd, string $eventDay, string $inputFormat='d/m/Y'):DateTime
    {
        $start = DateTime::createFromFormat($inputFormat, $periodStart)->setTime(0, 0, 0);
        $end = DateTime::createFromFormat($inputFormat, $periodEnd)->setTime(0, 0, 0);
        if (!$start || !$end || $start>$end)
            throw new Exception('Invalid period date');

        $last = clone $end;
        if ($last->format('l') !== $eventDay)
            $last->modify("last $eventDay");
        if ($last<$start)
        {
            $msg = sprintf('getLastEventDate: Invalid event schedule: start=%s, end=%s, eventDay=%s',
                $periodStart, $periodEnd, $eventDay);
            throw new Exception($msg);
        }
        return $last;
    }

}


