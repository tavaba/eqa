<?php
namespace Kma\Component\Survey\Administrator\Enum;
enum EntityType
{
    //Asset types
    const RESPONDENT = 10;
    const RESPONDENT_GROUP = 11;
    const RESPONDENT_UNIT = 12;
    const CREDIT_CLASS=13;
    const TOPIC = 20;
    const FORM = 21;
    const CAMPAIGN = 22;
    const SURVEY = 23;
    const ALL_TYPES = [
        self::RESPONDENT => 'Respondent',
        self::RESPONDENT_GROUP => 'RespondentGroup',
        self::RESPONDENT_UNIT => 'Unit',
        self::CREDIT_CLASS=>'Class',
        self::TOPIC => 'Topic',
        self::FORM => 'Form',
        self::CAMPAIGN => 'Campaign',
        self::SURVEY => 'Survey'
    ];
    public static function encode(string $assetType): int
    {
        $assetType = strtolower(trim($assetType));
        foreach (self::ALL_TYPES as $key=> $value){
            if(strtolower($value)==$assetType)
                return $key;
        }
        return -1;
    }
    public static function decode(int $code): string
    {
        return self::ALL_TYPES[$code] ?? '';
    }
    public static function getAll(): array
    {
        return self::ALL_TYPES;
    }
}
