<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Collator;
use Exception;
use JFactory;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

abstract class StringHelper{
	static public function getVietnamseCollator(){
		return new Collator('vi_VN');
	}
    /**
     * Chuyển danh từ tiếng Anh số ít thành số nhiều.
     * Được dùng để tự động xác định tên các đối tượng, giá trị các tham số
     * trong Controller, Model tự định nghĩa (prefix 'Eqa')
     *
     * @param $singleNoun string An english noun in single form
     * @return string
     * @since 1.0
     */
    public static function convertSingleToPlural(string $singleNoun): string{
        $origLen = strlen($singleNoun);
        if(str_ends_with($singleNoun,'y'))
            return substr($singleNoun,0,$origLen-1).'ies';
        if(str_ends_with($singleNoun,'s'))
            return $singleNoun.'es';
        return $singleNoun.'s';
    }

    /**
     * Chuyển danh từ tiếng Anh số nhiều thành số ít.
     * Được dùng để tự động xác định tên các đối tượng, giá trị các tham số
     * trong Controller, Model tự định nghĩa (prefix 'Eqa')
     *
     * @param $pluralNoun string An english noun in plural form
     * @return string
     * @since 1.0
     */
    public static function convertPluralToSingle(string $pluralNoun): string{
        $origLen = strlen($pluralNoun);
        return match ($pluralNoun){
            'specialities' => 'speciality',
            'Specialities' => 'Speciality',
            'classes' => 'class',
            'Classes' => 'Class',
            default => substr($pluralNoun,0,$origLen-1)
        };
    }

    static public function isUnicodeAlphaString($string): bool
    {
        // Ensure the object is a string
        if (!is_string($string)) {
            return false;
        }

        // Use a regular expression to check if the string contains only alphabetic characters
        $pattern = '/^\p{L}+$/u';
        if(preg_match($pattern, $string))
            return true;
        return false;
    }
    static public function isUnicodeAlphaStringWithSpacesAndHyphens($string): bool
    {
        // Ensure the object is a string
        if (!is_string($string)) {
            return false;
        }

        // Use a regular expression to check if the string contains only alphabetic characters
        // and spaces and hyphens
        $pattern = '/^[\p{L} \-]+$/u';
        if(preg_match($pattern, $string))
            return true;
        return false;
    }

}
