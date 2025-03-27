<?php
namespace Kma\Component\Eqa\Administrator\Interface;
defined('_JEXEC') or die();
class ObjectInfo
{
	public function __construct(object|null $obj=null)
	{
		if(is_null($obj))
			return;

		foreach ($obj as $key => $value) {
			if (property_exists($this, $key)) {
				$this->$key = $value;
			}
		}
	}
}