<?php

namespace Kma\Library\Kma\Helper;

abstract class NumberHelper
{
	public static function isInteger($value): bool
	{
		if(!is_numeric($value))
			return  false;
		$intValue = (int)$value;
		if($intValue != $value)
			return false;
		return true;
	}
    public static function isIntegerArray(array $items): bool
    {
        if(empty($items))
            return false;

        foreach ($items as $item) {
            if (is_int($item)) {
                continue;
            }

            if (is_string($item)) {
                // Check if the string represents an integer (including negative numbers)
                if (filter_var($item, FILTER_VALIDATE_INT) !== false) {
                    continue;
                }
            }

            return false;
        }

        return true;
    }
	public static function toFloat($value, $precison=null): float|bool
	{
		if(is_numeric($value))  //Nếu là số thì coi như xong
		{
			if(is_null($precison))
				return $value;
			else
				return round($value, $precison);
		}

		if(empty($value))   //Nếu không phải số mà rỗng thì hỏng
			return false;

		$value = str_replace(',', '.', $value);    //Trường hợp sử dụng dấu phẩy thay vì dấu chấm
		if(is_numeric($value))                                  //và kiểm tra lại lần nữa
		{
			if(is_null($precison))
				return $value;
			else
				return round($value, $precison);
		}

		return false;
	}
}