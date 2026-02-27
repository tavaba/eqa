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
	protected const WINDOWS_TO_IANA_TIMEZONE_MAP = [
		'Dateline Standard Time'              => 'Etc/GMT+12',
		'UTC-11'                              => 'Etc/GMT+11',
		'Aleutian Standard Time'              => 'America/Adak',
		'Hawaiian Standard Time'              => 'Pacific/Honolulu',
		'Marquesas Standard Time'             => 'Pacific/Marquesas',
		'Alaskan Standard Time'               => 'America/Anchorage',
		'UTC-09'                              => 'Etc/GMT+9',
		'Pacific Standard Time (Mexico)'      => 'America/Tijuana',
		'UTC-08'                              => 'Etc/GMT+8',
		'Pacific Standard Time'               => 'America/Los_Angeles',
		'US Mountain Standard Time'           => 'America/Phoenix',
		'Mountain Standard Time (Mexico)'     => 'America/Chihuahua',
		'Mountain Standard Time'              => 'America/Denver',
		'Yukon Standard Time'                 => 'America/Whitehorse',
		'Central America Standard Time'       => 'America/Guatemala',
		'Central Standard Time'               => 'America/Chicago',
		'Easter Island Standard Time'         => 'Pacific/Easter',
		'Central Standard Time (Mexico)'      => 'America/Mexico_City',
		'Canada Central Standard Time'        => 'America/Regina',
		'SA Pacific Standard Time'            => 'America/Bogota',
		'Eastern Standard Time (Mexico)'      => 'America/Cancun',
		'Eastern Standard Time'               => 'America/New_York',
		'Haiti Standard Time'                 => 'America/Port-au-Prince',
		'Cuba Standard Time'                  => 'America/Havana',
		'US Eastern Standard Time'            => 'America/Indiana/Indianapolis',
		'Turks And Caicos Standard Time'      => 'America/Grand_Turk',
		'Paraguay Standard Time'              => 'America/Asuncion',
		'Atlantic Standard Time'              => 'America/Halifax',
		'Venezuela Standard Time'             => 'America/Caracas',
		'Central Brazilian Standard Time'     => 'America/Cuiaba',
		'SA Western Standard Time'            => 'America/La_Paz',
		'Pacific SA Standard Time'            => 'America/Santiago',
		'Newfoundland Standard Time'          => 'America/St_Johns',
		'Tocantins Standard Time'             => 'America/Araguaina',
		'E. South America Standard Time'      => 'America/Sao_Paulo',
		'SA Eastern Standard Time'            => 'America/Cayenne',
		'Argentina Standard Time'             => 'America/Argentina/Buenos_Aires',
		'Greenland Standard Time'             => 'America/Godthab',
		'Montevideo Standard Time'            => 'America/Montevideo',
		'Magallanes Standard Time'            => 'America/Punta_Arenas',
		'Saint Pierre Standard Time'          => 'America/Miquelon',
		'Bahia Standard Time'                 => 'America/Bahia',
		'UTC-02'                              => 'Etc/GMT+2',
		'Azores Standard Time'                => 'Atlantic/Azores',
		'Cape Verde Standard Time'            => 'Atlantic/Cape_Verde',
		'UTC'                                 => 'Etc/GMT',
		'GMT Standard Time'                   => 'Europe/London',
		'Greenwich Standard Time'             => 'Atlantic/Reykjavik',
		'Sao Tome Standard Time'              => 'Africa/Sao_Tome',
		'Morocco Standard Time'               => 'Africa/Casablanca',
		'W. Europe Standard Time'             => 'Europe/Berlin',
		'Central Europe Standard Time'        => 'Europe/Budapest',
		'Romance Standard Time'               => 'Europe/Paris',
		'Central European Standard Time'      => 'Europe/Warsaw',
		'W. Central Africa Standard Time'     => 'Africa/Lagos',
		'Jordan Standard Time'                => 'Asia/Amman',
		'GTB Standard Time'                   => 'Europe/Bucharest',
		'Middle East Standard Time'           => 'Asia/Beirut',
		'Egypt Standard Time'                 => 'Africa/Cairo',
		'E. Europe Standard Time'             => 'Asia/Nicosia',
		'Syria Standard Time'                 => 'Asia/Damascus',
		'West Bank Standard Time'             => 'Asia/Hebron',
		'South Africa Standard Time'          => 'Africa/Johannesburg',
		'FLE Standard Time'                   => 'Europe/Kiev',
		'Israel Standard Time'                => 'Asia/Jerusalem',
		'Kaliningrad Standard Time'           => 'Europe/Kaliningrad',
		'Sudan Standard Time'                 => 'Africa/Khartoum',
		'Libya Standard Time'                 => 'Africa/Tripoli',
		'Namibia Standard Time'               => 'Africa/Windhoek',
		'Arabic Standard Time'                => 'Asia/Baghdad',
		'Turkey Standard Time'                => 'Europe/Istanbul',
		'Arab Standard Time'                  => 'Asia/Riyadh',
		'Belarus Standard Time'               => 'Europe/Minsk',
		'Russian Standard Time'               => 'Europe/Moscow',
		'E. Africa Standard Time'             => 'Africa/Nairobi',
		'Iran Standard Time'                  => 'Asia/Tehran',
		'Arabian Standard Time'               => 'Asia/Dubai',
		'Astrakhan Standard Time'             => 'Europe/Astrakhan',
		'Azerbaijan Standard Time'            => 'Asia/Baku',
		'Russia Time Zone 3'                  => 'Europe/Samara',
		'Mauritius Standard Time'             => 'Indian/Mauritius',
		'Saratov Standard Time'               => 'Europe/Saratov',
		'Georgian Standard Time'              => 'Asia/Tbilisi',
		'Volgograd Standard Time'             => 'Europe/Volgograd',
		'Caucasus Standard Time'              => 'Asia/Yerevan',
		'Afghanistan Standard Time'           => 'Asia/Kabul',
		'West Asia Standard Time'             => 'Asia/Tashkent',
		'Ekaterinburg Standard Time'          => 'Asia/Yekaterinburg',
		'Pakistan Standard Time'              => 'Asia/Karachi',
		'Qyzylorda Standard Time'             => 'Asia/Qyzylorda',
		'India Standard Time'                 => 'Asia/Calcutta',
		'Sri Lanka Standard Time'             => 'Asia/Colombo',
		'Nepal Standard Time'                 => 'Asia/Katmandu',
		'Central Asia Standard Time'          => 'Asia/Almaty',
		'Bangladesh Standard Time'            => 'Asia/Dhaka',
		'Omsk Standard Time'                  => 'Asia/Omsk',
		'Myanmar Standard Time'               => 'Asia/Rangoon',
		'SE Asia Standard Time'               => 'Asia/Bangkok',
		'Altai Standard Time'                 => 'Asia/Barnaul',
		'W. Mongolia Standard Time'           => 'Asia/Hovd',
		'North Asia Standard Time'            => 'Asia/Krasnoyarsk',
		'N. Central Asia Standard Time'       => 'Asia/Novosibirsk',
		'Tomsk Standard Time'                 => 'Asia/Tomsk',
		'China Standard Time'                 => 'Asia/Shanghai',
		'North Asia East Standard Time'       => 'Asia/Irkutsk',
		'Singapore Standard Time'             => 'Asia/Singapore',
		'W. Australia Standard Time'          => 'Australia/Perth',
		'Taipei Standard Time'                => 'Asia/Taipei',
		'Ulaanbaatar Standard Time'           => 'Asia/Ulaanbaatar',
		'Aus Central W. Standard Time'        => 'Australia/Eucla',
		'Transbaikal Standard Time'           => 'Asia/Chita',
		'Tokyo Standard Time'                 => 'Asia/Tokyo',
		'North Korea Standard Time'           => 'Asia/Pyongyang',
		'Korea Standard Time'                 => 'Asia/Seoul',
		'Yakutsk Standard Time'               => 'Asia/Yakutsk',
		'Cen. Australia Standard Time'        => 'Australia/Adelaide',
		'AUS Central Standard Time'           => 'Australia/Darwin',
		'E. Australia Standard Time'          => 'Australia/Brisbane',
		'AUS Eastern Standard Time'           => 'Australia/Sydney',
		'West Pacific Standard Time'          => 'Pacific/Port_Moresby',
		'Tasmania Standard Time'              => 'Australia/Hobart',
		'Vladivostok Standard Time'           => 'Asia/Vladivostok',
		'Lord Howe Standard Time'             => 'Australia/Lord_Howe',
		'Bougainville Standard Time'          => 'Pacific/Bougainville',
		'Russia Time Zone 10'                 => 'Asia/Srednekolymsk',
		'Magadan Standard Time'               => 'Asia/Magadan',
		'Norfolk Standard Time'               => 'Pacific/Norfolk',
		'Sakhalin Standard Time'              => 'Asia/Sakhalin',
		'Central Pacific Standard Time'       => 'Pacific/Guadalcanal',
		'Russia Time Zone 11'                 => 'Asia/Kamchatka',
		'New Zealand Standard Time'           => 'Pacific/Auckland',
		'UTC+12'                              => 'Etc/GMT-12',
		'Fiji Standard Time'                  => 'Pacific/Fiji',
		'Chatham Islands Standard Time'       => 'Pacific/Chatham',
		'UTC+13'                              => 'Etc/GMT-13',
		'Tonga Standard Time'                 => 'Pacific/Tongatapu',
		'Samoa Standard Time'                 => 'Pacific/Apia',
		'Line Islands Standard Time'          => 'Pacific/Kiritimati',
	];
	protected static function getOsTimezone(): string
	{
		// Linux/macOS: đọc symlink /etc/localtime
		if (PHP_OS_FAMILY !== 'Windows') {
			if (is_link('/etc/localtime')) {
				$link = readlink('/etc/localtime');
				if (preg_match('#zoneinfo/(.+)$#', $link, $matches)) {
					return $matches[1];
				}
			}

			// Một số distro lưu tên timezone trong /etc/timezone
			if (file_exists('/etc/timezone')) {
				$tz = trim(file_get_contents('/etc/timezone'));
				if (!empty($tz)) {
					return $tz;
				}
			}

			// Fallback: dùng lệnh date của OS
			$offset = shell_exec('date +%z');
			if ($offset && preg_match('/([+-])(\d{2})(\d{2})/', trim($offset), $m)) {
				$totalMinutes = ((int)$m[2] * 60 + (int)$m[3]) * ($m[1] === '+' ? 1 : -1);
				return sprintf('%+03d:%02d', intdiv($totalMinutes, 60), abs($totalMinutes % 60));
			}
		}

		// Windows: dùng lệnh tzutil /g rồi map sang IANA
		if (PHP_OS_FAMILY === 'Windows') {
			$windowsTz = trim((string) shell_exec('tzutil /g'));
			if (!empty($windowsTz)) {
				if (isset(self::WINDOWS_TO_IANA_TIMEZONE_MAP[$windowsTz])) {
					return self::WINDOWS_TO_IANA_TIMEZONE_MAP[$windowsTz];
				}
			}

			// Fallback Windows: dùng wmic
			$offset = shell_exec('wmic timezone get Bias /value');
			if ($offset && preg_match('/Bias=(-?\d+)/', trim($offset), $m)) {
				$bias    = (int) $m[1]; // phút, Windows lưu bias ngược dấu
				$minutes = -$bias;
				return sprintf('%+03d:%02d', intdiv($minutes, 60), abs($minutes % 60));
			}
		}

		return 'UTC';
	}

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
	 * Get the current date and time as a string formatted according to the specified format.
	 * The timezone used is determined by the operating system's configuration
	 * (not the PHP configuration).
	 * The method attempts to detect the OS timezone using various methods
	 *
	 * @param   string  $format  A date format string compatible with PHP's date() function.
	 *                           Default is 'Y-m-d H:i:s'.
	 *
	 * @return string The current date and time formatted according to the specified format.
	 * @since 1.0.2
	 * @throws Exception
	 */
	public static function getSystemCurrentClockTime(string $format = 'Y-m-d H:i:s'): string
	{
		$tz = new DateTimeZone(self::getOsTimezone());
		return (new DateTime('now', $tz))->format($format);
	}

	/**
	 * Convert a local time string to a UTC time string, using the specified timezone.
	 * If the timezone is not provided, it will be detected from the operating system's configuration.
	 *
	 * @param string $localTime A date and time string representing the local time.
	 * @param string|null $timezone An optional timezone identifier (e.g. 'Asia/Ho_Chi_Minh').
	 *                             If null, the method will attempt to detect the OS timezone.
	 * @param string $format The desired output format for the UTC time string. Default is 'Y-m-d H:i:s'.
	 *
	 * @return string A date and time string representing the corresponding UTC time, formatted according to the specified format.
	 * @since 1.0.2
	 * @throws Exception
	 */
	public static function toUtc(string $localTime, ?string $timezone = null, string $format = 'Y-m-d H:i:s'): string
	{
		$timezone ??= self::getOsTimezone(); // hàm đã viết ở trên

		$dt = new DateTime($localTime, new DateTimeZone($timezone));
		$dt->setTimezone(new DateTimeZone('UTC'));

		return $dt->format($format);
	}

	/**
	 * Convert a UTC time string to a local time string, using the specified timezone.
	 * If the timezone is not provided, it will be detected from the operating system's configuration.
	 *
	 * @param string $utcTime A date and time string representing the UTC time.
	 * @param string|null $timezone An optional timezone identifier (e.g. 'Asia/Ho_Chi_Minh').
	 *                             If null, the method will attempt to detect the OS timezone.
	 * @param string $format The desired output format for the local time string. Default is 'Y-m-d H:i:s'.
	 *
	 * @return string A date and time string representing the corresponding local time, formatted according to the specified format.
	 * @since 1.0.2
	 * @throws Exception
	 */
	public static function fromUtc(string $utcTime, ?string $timezone = null, string $format = 'Y-m-d H:i:s'): string
	{
		$timezone ??= self::getOsTimezone();

		$dt = new DateTime($utcTime, new DateTimeZone('UTC'));
		$dt->setTimezone(new DateTimeZone($timezone));

		return $dt->format($format);
	}

	public static function getCurrentHanoiDatetime():string
    {
        $tz = new DateTimeZone("Asia/Ho_Chi_Minh");
        $dt = new DateTime();
        $dt->setTimeZone($tz);
        return $dt->format('Y-m-d H:i:s');
    }
	public static function getSigningDateString(string $place='Hà Nội', string $datetimeString='now'):string
	{
		$datetime = new DateTime($datetimeString);
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
	 * @param   string  $timestamp A date/time string that can be parsed by DateTime, representing the time to compare against the current time.
	 * @param   bool    $isUTC Indicates whether the input timestamp is in UTC. If true, the method will compare it against the current UTC time. If false, it will determine the timezone of the input timestamp and compare it against the current time in that timezone.
	 * @param   string  $timezone An optional timezone identifier (e.g. 'Asia/Ho_Chi_Minh') to specify the timezone of the input timestamp if $isUTC is false. If not provided, the method will attempt to detect the timezone according to the value of $useOSTimezone.
	 * @param   bool    $useOSTimezone When $isUTC is false and $timezone is not provided, this parameter determines whether to detect the timezone from the operating system's configuration (if true) or to use the timezone configured in PHP (if false). Default is true.
	 *
	 * @return bool Returns true if the input timestamp is in the past compared to the current time (i.e., the time has passed), or false if it is in the future or exactly the same as the current time.
	 *
	 * @throws \DateInvalidTimeZoneException
	 * @throws \DateMalformedStringException
	 * @since 1.0.0
	 */
	public static function isTimeOver(
		string $timestamp,
		bool   $isUTC          = true,
		string $timezone       = '',
		bool   $useOSTimezone  = true
	): bool {
		if ($isUTC) {
			// $timestamp là UTC → so sánh thẳng với thời gian UTC hiện tại
			$inputDt = new DateTime($timestamp, new DateTimeZone('UTC'));
			$nowDt   = new DateTime('now',      new DateTimeZone('UTC'));
		} else {
			// Xác định timezone của $timestamp
			if ($timezone !== '') {
				// Timezone được chỉ định rõ
				$tz = new DateTimeZone($timezone);
			} elseif ($useOSTimezone) {
				// Lấy timezone từ hệ điều hành (dùng hàm getOsTimezone() đã viết ở trên)
				$tz = new DateTimeZone(self::getOsTimezone());
			} else {
				// Lấy timezone từ cấu hình PHP
				$tz = new DateTimeZone(date_default_timezone_get());
			}

			$inputDt = new DateTime($timestamp, $tz);
			$nowDt   = new DateTime('now',      $tz);
		}

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


