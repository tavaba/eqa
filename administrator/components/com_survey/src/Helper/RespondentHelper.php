<?php
namespace Kma\Component\Survey\Administrator\Helper;
defined('_JEXEC') or die();

abstract class RespondentHelper
{
    //Respondent types
    const RESPONDENT_TYPE_LEARNER = 10;
    const RESPONDENT_TYPE_EMPLOYEE = 20;
    const RESPONDENT_TYPE_VISITING_LECTURER = 30;
    const RESPONDENT_TYPE_EXPERT = 40;
    const RESPONDENT_TYPE_EMPLOYER = 50;
    const RESPONDENT_TYPE_VISITING_OTHER = 100;

    //Respondent group types
    const RESPONDENT_GROUP_TYPE_STUDENT = 10;
    const RESPONDENT_GROUP_TYPE_MASTER_STUDENT = 11;
    const RESPONDENT_GROUP_TYPE_GRADUATED_STUDENT = 20;
    const RESPONDENT_GROUP_TYPE_TEACHER = 30;
    const RESPONDENT_GROUP_TYPE_VISITING_TEACHER = 35;
    const RESPONDENT_GROUP_TYPE_STAFF = 40;
    const RESPONDENT_GROUP_TYPE_RESEARCHER = 50;
    const RESPONDENT_GROUP_TYPE_EMPLOYEE = 60;
    const RESPONDENT_GROUP_TYPE_EMPLOYER = 70;
    const RESPONDENT_GROUP_TYPE_MIXED = 100;

    //Unit types of respondents
    const RESPONDENT_UNIT_TYPE_COURSE=10;
    const RESPONDENT_UNIT_TYPE_DEPARTMENT=20;
    const RESPONDENT_UNIT_TYPE_COMPANY=50;

    //Respondent genders
    const RESPONDENT_GENDER_MALE = 1;
    const RESPONDENT_GENDER_FEMALE=2;
    static  public function decodeType(int $code): string
    {
        return match ($code) {
            self::RESPONDENT_TYPE_LEARNER => 'HVSV',
            self::RESPONDENT_TYPE_EMPLOYEE => 'CB-GV-NV',
            self::RESPONDENT_TYPE_VISITING_LECTURER => 'GV thỉnh giảng',
            self::RESPONDENT_TYPE_EXPERT => 'Chuyên gia',
            self::RESPONDENT_TYPE_EMPLOYER => 'Nhà tuyển dụng',
            self::RESPONDENT_TYPE_VISITING_OTHER => 'Khác',
            default => '',
        };
    }
    public static function getTypes(): array
    {
        return [
            self::RESPONDENT_TYPE_LEARNER => self::decodeType(self::RESPONDENT_TYPE_LEARNER),
            self::RESPONDENT_TYPE_EMPLOYEE => self::decodeType(self::RESPONDENT_TYPE_EMPLOYEE),
            self::RESPONDENT_TYPE_VISITING_LECTURER => self::decodeType(self::RESPONDENT_TYPE_VISITING_LECTURER),
            self::RESPONDENT_TYPE_EXPERT => self::decodeType(self::RESPONDENT_TYPE_EXPERT),
            self::RESPONDENT_TYPE_EMPLOYER => self::decodeType(self::RESPONDENT_TYPE_EMPLOYER),
            self::RESPONDENT_TYPE_VISITING_OTHER => self::decodeType(self::RESPONDENT_TYPE_VISITING_OTHER),
        ];
    }
    public static function decodeGroupType(int $code): string
    {
        return match ($code) {
            self::RESPONDENT_GROUP_TYPE_STUDENT => 'Sinh viên',
            self::RESPONDENT_GROUP_TYPE_MASTER_STUDENT => 'Học viên cao học',
            self::RESPONDENT_GROUP_TYPE_GRADUATED_STUDENT => 'Cựu sinh viên (đã tốt nghiệp)',
            self::RESPONDENT_GROUP_TYPE_TEACHER => 'Giảng viên',
            self::RESPONDENT_GROUP_TYPE_VISITING_TEACHER => 'Giảng viên thỉnh giảng',
            self::RESPONDENT_GROUP_TYPE_STAFF => 'Cán bộ phòng ban',
            self::RESPONDENT_GROUP_TYPE_RESEARCHER => 'Cán bộ nghiên cứu',
            self::RESPONDENT_GROUP_TYPE_EMPLOYEE => 'Cán bộ, giảng viên, nhân viên Học viện',
            self::RESPONDENT_GROUP_TYPE_EMPLOYER => 'Nhà tuyển dụng',
            self::RESPONDENT_GROUP_TYPE_MIXED => 'Hỗn hợp',
            default => ''
        };
    }
    public static function getGroupTypes(): array
    {
        return [
            self::RESPONDENT_GROUP_TYPE_STUDENT => self::decodeGroupType(self::RESPONDENT_GROUP_TYPE_STUDENT),
            self::RESPONDENT_GROUP_TYPE_MASTER_STUDENT => self::decodeGroupType(self::RESPONDENT_GROUP_TYPE_MASTER_STUDENT),
            self::RESPONDENT_GROUP_TYPE_GRADUATED_STUDENT => self::decodeGroupType(self::RESPONDENT_GROUP_TYPE_GRADUATED_STUDENT),
            self::RESPONDENT_GROUP_TYPE_TEACHER => self::decodeGroupType(self::RESPONDENT_GROUP_TYPE_TEACHER),
            self::RESPONDENT_GROUP_TYPE_VISITING_TEACHER => self::decodeGroupType(self::RESPONDENT_GROUP_TYPE_VISITING_TEACHER),
            self::RESPONDENT_GROUP_TYPE_STAFF => self::decodeGroupType(self::RESPONDENT_GROUP_TYPE_STAFF),
            self::RESPONDENT_GROUP_TYPE_RESEARCHER => self::decodeGroupType(self::RESPONDENT_GROUP_TYPE_RESEARCHER),
            self::RESPONDENT_GROUP_TYPE_EMPLOYEE => self::decodeGroupType(self::RESPONDENT_GROUP_TYPE_EMPLOYEE),
            self::RESPONDENT_GROUP_TYPE_EMPLOYER => self::decodeGroupType(self::RESPONDENT_GROUP_TYPE_EMPLOYER),
            self::RESPONDENT_GROUP_TYPE_MIXED => self::decodeGroupType(self::RESPONDENT_GROUP_TYPE_MIXED),
        ];
    }
    public static function decodeUnitType(int $unitTypeCode): string
    {
        return match($unitTypeCode){
            self::RESPONDENT_UNIT_TYPE_COURSE=>'Khóa đào tạo',
            self::RESPONDENT_UNIT_TYPE_DEPARTMENT=>'Phòng/Ban/Khoa',
            self::RESPONDENT_UNIT_TYPE_COMPANY=>'Công ty'
        };
    }
    public static function getUnitTypes(): array
    {
        return [
            self::RESPONDENT_UNIT_TYPE_COURSE=>self::decodeUnitType(self::RESPONDENT_UNIT_TYPE_COURSE),
            self::RESPONDENT_UNIT_TYPE_DEPARTMENT=>self::decodeUnitType(self::RESPONDENT_UNIT_TYPE_DEPARTMENT),
            self::RESPONDENT_UNIT_TYPE_COMPANY=>self::decodeUnitType(self::RESPONDENT_UNIT_TYPE_COMPANY)
        ];
    }
    public static function decodeGender(int $genderCode): string
    {
        return match($genderCode){
            self::RESPONDENT_GENDER_MALE=>'Nam',
            self::RESPONDENT_GENDER_FEMALE=>'Nữ'
        };
    }
    public static function getGenders(): array
    {
        return [
            self::RESPONDENT_GENDER_MALE=>self::decodeGender(self::RESPONDENT_GENDER_MALE),
            self::RESPONDENT_GENDER_FEMALE=>self::decodeGender(self::RESPONDENT_GENDER_FEMALE)
        ];
    }
}