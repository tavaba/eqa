<?php
namespace Kma\Component\Eqa\Administrator\DataObject;

defined('_JEXEC') or die();
class PpaaEntryInfo extends ObjectInfo
{
	public ?int $type;
	public ?int $mask;
	public ?float $oldMark;
	public ?float $newMark;
	public ?string $requestReason;
	public ?string $changeDescription;
}