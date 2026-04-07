<?php
namespace Kma\Component\Eqa\Administrator\DataObject;
use DateTime;
use DateTimeZone;
use Kma\Library\Kma\Helper\DatetimeHelper;

defined('_JEXEC') or die();
class ExamseasonInfo
{
	public int $id;
	public string $name;
	public int $term;
	public string $academicyear;
	public bool $completed;
	public bool $ppaaRequestEnabled;
	public string|null $ppaaRequestDeadline;
}