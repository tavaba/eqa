<?php
namespace Kma\Component\Survey\Administrator\Helper;
use Kma\Library\Kma\Helper\StringHelper;

defined('_JEXEC') or die();

abstract class SurveyHelper
{
    /**
     * Generate a non-numeric token for a respondent to attend a survey.
     * This must be non-numeric so that it can be distinguished from the numeric ID
     * of a survey in a URL.
     *
     * @return string
     * @since 1.0.0
     */
    public static function generateNonNumericToken(int $length=12): string
    {
        while (true)
        {
            $token=StringHelper::generateRandomString($length);
            if(!is_numeric($token))
                return $token;
        }
    }
}