<?php
namespace Kma\Component\Eqa\Administrator\DataObject;
use DateTime;
use DateTimeZone;

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

	public function canSendPpaaRequest(): bool
	{
		if($this->completed) return false;
		if(!$this->ppaaRequestEnabled) return false;
		if(!is_null($this->ppaaRequestDeadline)) {
			$timezone = new DateTimeZone('Asia/Ho_Chi_Minh');
			$now = new DateTime('now', $timezone);
			$deadlineTime = new DateTime($this->ppaaRequestDeadline, $timezone);
			if($now > $deadlineTime) return false;
		}
		return true;
	}
}