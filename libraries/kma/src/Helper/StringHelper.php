<?php
namespace Kma\Library\Kma\Helper;
defined('_JEXEC') or die();

use Collator;

abstract class StringHelper{
	public static function getVietnamseCollator(): Collator
	{
		return new Collator('vi_VN');
	}

    public static function isUnicodeAlphaString($string): bool
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
    public static function isUnicodeAlphaStringWithSpacesAndHyphens($string): bool
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

    /**
     * Generate random string
     *
     * @param   integer  $length  String length
     *
     * @return  string  Random string
     *
     * @since   1.0.0
     */
    public static function generateRandomString(int $length = 10, string $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'): string
    {
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }

    /**
     * Remove accents from Vietnamese strings.
     *
     * @param   string  $str  The input string with accents.
     * @return  string  The input string without accents.
     * @since   1.0.0
     */
    public static function removeAccents(string $str): string
    {
        $str = preg_replace("/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/", "a", $str);
        $str = preg_replace("/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/", "e", $str);
        $str = preg_replace("/(ì|í|ị|ỉ|ĩ)/", "i", $str);
        $str = preg_replace("/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/", "o", $str);
        $str = preg_replace("/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/", "u", $str);
        $str = preg_replace("/(ỳ|ý|ỵ|ỷ|ỹ)/", "y", $str);
        $str = preg_replace("/(đ)/", "d", $str);
        $str = preg_replace("/(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)/", "A", $str);
        $str = preg_replace("/(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)/", "E", $str);
        $str = preg_replace("/(Ì|Í|Ị|Ỉ|Ĩ)/", "I", $str);
        $str = preg_replace("/(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)/", "O", $str);
        $str = preg_replace("/(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)/", "U", $str);
        $str = preg_replace("/(Ỳ|Ý|Ỵ|Ỷ|Ỹ)/", "Y", $str);
        $str = preg_replace("/(Đ)/", "D", $str);
        return $str;
    }

    /**
     * Convert text to slug
     * @param $text
     * @return array|string|string[]|null
     * @since 1.0.0
     */
    public static function convertToSlug($text) {
        $slug = self::removeAccents($text);
        $slug = strtolower(str_replace(array('.', ',', ';'), '-', $slug));
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        return $slug;
    }

    /**
     * Parse full name into first name and last name. If there is only one word in fullName,
     * it will be considered as first name.
     *
     * @param   string  $fullName  Full name of person
     *
     * @return  array<string,string>  Array containing LAST name and FIRST name
     * @since   1.0.0
     */
    public static function parseVietnameseFullName(string $fullName): array
    {
        $parts = explode(" ", $fullName);
        if (count($parts) > 1) {
            $lastName = implode(" ", array_slice($parts, 0, count($parts)-1));
            $firstName = end($parts);
        } else {
            $lastName = "";
            $firstName = $fullName;
        }
        return [trim($lastName), trim($firstName)];
    }

    public static function CapitalizeFirstLetter(string $string): string
    {
        return ucfirst(strtolower($string));
    }
}
