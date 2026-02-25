<?php

namespace Kma\Library\Kma\View;

defined('_JEXEC') or die();

/*
 * ===Cách thức sử dụng trong View
 * $actions = $itemFields->actions = array();
 * $actions[] = $action = new EqaitemAction();
 */

class ItemAction
{
	public string $icon;
	public string $text;
	public string|null $class;
	public string|null $iconStyle;
	public string $urlFormatString;
    public string $urlFormatStringField;
	public string $displayConditionField;      //Thuộc tính của $item kiểu boolean cho biết hiển thị action hay không
	public function getHtml($item):string
	{
		if(isset($this->displayConditionField) && !$item->{$this->displayConditionField})
			return '';

		$html = '';

		//Dữ liệu không hợp lệ
		if(empty($this->icon) && empty($this->text))
			return $html;

        $fieldForUrl = $this->urlFormatStringField ?? 'id';
		$url = sprintf($this->urlFormatString, $item->{$fieldForUrl});

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
	public static function cast($obj): ItemAction
	{
		return $obj;
	}
}