<?php
namespace Kma\Component\Eqa\Administrator\Interface;

defined('_JEXEC') or die();
class PpaaEntryInfo extends ObjectInfo
{
	public ?int $type;
	public ?int $mask;
	public ?float $oldMark;
	public ?float $newMark;
	public ?string $requestReason;
	public ?string $changeDescription;
	static public function cast($obj): PpaaEntryInfo
	{
		return $obj;
	}
}