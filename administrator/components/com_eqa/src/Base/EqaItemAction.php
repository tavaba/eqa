<?php

namespace Kma\Component\Eqa\Administrator\Base;

defined('_JEXEC') or die();

/*
 * ===Cách thức sử dụng trong View
 * $actions = $itemFields->actions = array();
 * $actions[] = $action = new EqaitemAction();
 */

class EqaItemAction
{
	public string $icon;
	public string $text;
	public string|null $class;
	public string|null $iconStyle;
	public string $urlFormatStringForItemId;
	public string $displayConditionItemField;           //Thuộc tính của $item kiểu boolean cho biết hiển thị action hay không
	public function getHtml($item):string
	{
		if(isset($this->displayConditionItemField) && !$item->$this->displayConditionItemField)
			return '';

		$itemId = $item->id;
		$html = '';

		//Dữ liệu không hợp lệ
		if(empty($this->icon) && empty($this->text))
			return $html;

		$url = sprintf($this->urlFormatStringForItemId, $itemId);

		//Opening A-tag
		$html .= "<a href=\"$url\"";
		if(!empty($this->class))
			$html .= " class=\"$this->class\"";
		if(!empty($this->text))
			$html .= " title=\"$this->text\"";
		$html .= '>';

		//A-tag's body
		if(!empty($this->icon))
		{
			$html .= "<span class=\"icon-$this->icon\"";
			if(!empty($this->style))
				$html .= " style=\"$this->iconStyle\"";
			$html .= ' aria-hidden="true"></span>';
		}
		else{
			$html .= $this->text;
		}

		//Closing A-tag
		$html .= '</a>';

		//Return
		return $html;
	}
	static public function cast($obj): EqaItemAction
	{
		return $obj;
	}
}