<?php
namespace Kma\Component\Survey\Administrator\Helper;
use Exception;
use Joomla\CMS\Factory;
use Kma\Component\Survey\Administrator\Enum\EntityType;
use Kma\Library\Kma\Helper\DatabaseHelper;

defined('_JEXEC') or die();

abstract class LogHelper
{
    //Action types
    const ACTION_CREATE = 10;
    const ACTION_EDIT = 20;
    const ACTION_EDIT_STATE = 21;
    const ACTION_PUBLISH = 22;
    const ACTION_UNPUBLISH = 23;
    const ACTION_ARCHIVE = 24;
    const ACTION_TRASH = 25;
    const ACTION_SYNC = 30;
    const ACTION_DELETE = 40;
    const ACTION_TRACK = 50;
    const ACTION_ANALYZE = 51;

    //Results
    const RESULT_SUCCESS = 1;
    const RESULT_FAIL = -1;
    const RESULT_PARTIAL = 0;

    public static function decodeActionType(int $code): string
    {
        return match ($code) {
            self::ACTION_CREATE => 'Create',
            self::ACTION_EDIT => 'Edit',
            self::ACTION_EDIT_STATE => 'Edit state',
            self::ACTION_PUBLISH => 'Publish',
            self::ACTION_UNPUBLISH => 'Unpublish',
            self::ACTION_ARCHIVE => 'Archive',
            self::ACTION_TRASH => 'Trash',
            self::ACTION_SYNC => 'Sync',
            self::ACTION_DELETE => 'Delete',
            self::ACTION_TRACK => 'Track survey',
            self::ACTION_ANALYZE => 'Analyze survey results',
            default => ''
        };
    }
    public static function getActionTypes(): array
    {
        return [
            self::ACTION_CREATE => self::decodeActionType(self::ACTION_CREATE),
            self::ACTION_EDIT => self::decodeActionType(self::ACTION_EDIT),
            self::ACTION_EDIT_STATE => self::decodeActionType(self::ACTION_EDIT_STATE),
            self::ACTION_PUBLISH => self::decodeActionType(self::ACTION_PUBLISH),
            self::ACTION_UNPUBLISH => self::decodeActionType(self::ACTION_UNPUBLISH),
            self::ACTION_ARCHIVE => self::decodeActionType(self::ACTION_ARCHIVE),
            self::ACTION_TRASH => self::decodeActionType(self::ACTION_TRASH),
            self::ACTION_SYNC => self::decodeActionType(self::ACTION_SYNC),
            self::ACTION_DELETE => self::decodeActionType(self::ACTION_DELETE),
            self::ACTION_TRACK => self::decodeActionType(self::ACTION_TRACK),
            self::ACTION_ANALYZE => self::decodeActionType(self::ACTION_ANALYZE),
        ];
    }

    public static function decodeResultCode(int $code): string
    {
        return match ($code) {
            self::RESULT_SUCCESS => 'Thành công',
            self::RESULT_FAIL => 'Thất bại',
            self::RESULT_PARTIAL => 'Bị lỗi một phần',
            default => ''
        };
    }
    public static function getResultCodes(): array
    {
        return [
            self::RESULT_SUCCESS => self::decodeResultCode(self::RESULT_SUCCESS),
            self::RESULT_FAIL => self::decodeResultCode(self::RESULT_FAIL),
            self::RESULT_PARTIAL => self::decodeResultCode(self::RESULT_PARTIAL),
        ];
    }

    /**
     * Add a record to the log table.
     *
     * @param int $action               Action code
     * @param integer|string $assetType Asset type (e.g. respondent, form, etc.) or its code
     * @param integer $itemId           ID of the item that was affected by this action
     * @param integer $result           Result code
     * @param string $jsonData          Additional information in JSON format
     * @param integer|null $userId      User who performed the action; defaults to current user's ID
     *
     * @return  void
     * @throws Exception
     * @since 1.0.0
     */
    public static function Add(int $action, string|int $assetType, int $itemId, int $result, string $jsonData='', ?int $userId=null): void
    {
        //Initialize and validate parameters
        if(is_integer($assetType))
            $itemType = $assetType;
        else
            $itemType = EntityType::encode($assetType);
        if(empty($itemType))
            throw new Exception("Cannot add log: Invalid item type");
        if(is_null($userId))
            $userId = Factory::getApplication()->getIdentity()->id;
        if(!empty($jsonData) && !json_decode($jsonData))
            throw new Exception("Cannot add log: Invalid JSON data");

        //Insert record into database
        $db = DatabaseHelper::getDatabaseDriver();
        $columns = ['user_id','action','entity_type','entity_id','result','data'];
        $values = [$userId,$action,$itemType, $itemId,$result,$db->quote($jsonData)];
        $query = $db->getQuery(true)
            ->insert('#__survey_logs')
            ->columns($columns)
            ->values(implode(',', $values));
        $db->setQuery($query);
        $db->execute();
    }
}