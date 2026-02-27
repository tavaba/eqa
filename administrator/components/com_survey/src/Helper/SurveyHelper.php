<?php
namespace Kma\Component\Survey\Administrator\Helper;
use Kma\Library\Kma\Helper\StringHelper;

defined('_JEXEC') or die();

abstract class SurveyHelper
{
    //Survey types
    const SURVEY_TYPE_PROGRAM = 10;
    const SURVEY_TYPE_ADMISSION = 20;
    const SURVEY_TYPE_TEACHING = 30;
    const SURVEY_TYPE_LEARNER_SUPPORT = 40;
    const SURVEY_TYPE_EXAM = 50;
    const SURVEY_TYPE_STAFF = 80;
    const SURVEY_TYPE_FACILITIES = 90;

    //Authorization modes
    const AUTH_MODE_ANYONE = 0;
    const AUTH_MODE_AUTHENTICATED = 10;
    const AUTH_MODE_RESPONDENT = 15;
    const AUTH_MODE_ASSIGNED = 20;

    public const PERFORMANCE_SLOW = 1;
    public const PERFORMANCE_FAST = 2;
    public const PERFORMANCE_FASTER = 3;
    public const PERFORMANCE_FASTEST = 4;
    private const PERFORMANCE_MODES = [
        self::PERFORMANCE_SLOW => 'Chậm',
        self::PERFORMANCE_FAST => 'Nhanh',
        self::PERFORMANCE_FASTER => 'Nhanh hơn',
        self::PERFORMANCE_FASTEST => 'Nhanh nhất',
    ];
    public static function getPerformanceModes()
    {
        return self::PERFORMANCE_MODES;
    }
    public static function decodeSurveyType(int $code): string
    {
        return match ($code) {
            self::SURVEY_TYPE_PROGRAM => 'KS về chưương trình đào tạo',
            self::SURVEY_TYPE_ADMISSION => 'KS về tuyển sinh',
            self::SURVEY_TYPE_TEACHING => 'KS về hoạt động giảng dạy',
            self::SURVEY_TYPE_LEARNER_SUPPORT => 'KS về hoạt động hỗ trợ người học',
            self::SURVEY_TYPE_EXAM => 'KS về tổ chức thi',
            self::SURVEY_TYPE_STAFF => 'KS về công tác cán bộ',
            self::SURVEY_TYPE_FACILITIES => 'KS về cơ sở vật chất',
            default => ''
        };
    }
    public static function getSurveyTypes(): array
    {
        return [
            self::SURVEY_TYPE_PROGRAM => self::decodeSurveyType(self::SURVEY_TYPE_PROGRAM),
            self::SURVEY_TYPE_ADMISSION => self::decodeSurveyType(self::SURVEY_TYPE_ADMISSION),
            self::SURVEY_TYPE_TEACHING => self::decodeSurveyType(self::SURVEY_TYPE_TEACHING),
            self::SURVEY_TYPE_LEARNER_SUPPORT => self::decodeSurveyType(self::SURVEY_TYPE_LEARNER_SUPPORT),
            self::SURVEY_TYPE_EXAM => self::decodeSurveyType(self::SURVEY_TYPE_EXAM),
            self::SURVEY_TYPE_STAFF => self::decodeSurveyType(self::SURVEY_TYPE_STAFF),
            self::SURVEY_TYPE_FACILITIES => self::decodeSurveyType(self::SURVEY_TYPE_FACILITIES)
            ];
    }

    public static function decodeAuthMode(int $code): string
    {
        return match ($code) {
            self::AUTH_MODE_ANYONE => 'Bất kỳ ai, kể cả không đăng nhập',
            self::AUTH_MODE_AUTHENTICATED => 'Người dùng bất kỳ đã đăng nhập',
            self::AUTH_MODE_RESPONDENT => 'Người khảo sát bất kỳ',
            self::AUTH_MODE_ASSIGNED => 'Người khảo sát được chọn',
        };
    }
    public static function getAuthModes(): array
    {
        return [
            self::AUTH_MODE_ANYONE => self::decodeAuthMode(self::AUTH_MODE_ANYONE),
            self::AUTH_MODE_AUTHENTICATED => self::decodeAuthMode(self::AUTH_MODE_AUTHENTICATED),
            self::AUTH_MODE_RESPONDENT => self::decodeAuthMode(self::AUTH_MODE_RESPONDENT),
            self::AUTH_MODE_ASSIGNED => self::decodeAuthMode(self::AUTH_MODE_ASSIGNED),
        ];
    }

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