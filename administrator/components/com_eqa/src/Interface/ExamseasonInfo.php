<?php
namespace Kma\Component\Eqa\Administrator\Interface;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

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
			$deadlineTime = strtotime($this->ppaaRequestDeadline);
			$currentTime = time();
			if($currentTime > $deadlineTime) return false;
		}
		return true;
	}
}