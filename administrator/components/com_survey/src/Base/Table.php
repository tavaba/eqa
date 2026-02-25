<?php

namespace Kma\Component\Survey\Administrator\Base;
use Kma\Component\Survey\Administrator\Helper\LogHelper;
use Kma\Library\Kma\Helper\StateHelper;
use Kma\Library\Kma\Table\Table as Base;
abstract class Table extends Base
{
    public function store($updateNulls = true): bool
    {
        $targetName = $this->itemName;
        $itemId = $this->{$this->_tbl_key};
        $action = empty($itemId) ? LogHelper::ACTION_CREATE : LogHelper::ACTION_EDIT; //Detect whether this is a create or edit operation (based on primary key))

        //Call parent method and determine the result code at the same time
        $result = parent::store($updateNulls);
        $resultCode = $result ? LogHelper::RESULT_SUCCESS : LogHelper::RESULT_FAIL;

        //Update the item id if it was created in this request
        $itemId = $this->{$this->_tbl_key}??0;

        //Log the action
        LogHelper::Add($action, $targetName, $itemId, $resultCode);

        return $result;
    }

    public function delete($pk = null): bool
    {
        $result = parent::delete($pk);

        //Log
        $itemId = $pk??$this->{$this->_tbl_key};
        $targetName = $this->itemName;
        $resultCode = $result ? LogHelper::RESULT_SUCCESS : LogHelper::RESULT_FAIL;
        LogHelper::Add(LogHelper::ACTION_DELETE, $targetName, $itemId, $resultCode);

        return $result;
    }

    public function publish($pks = null, $state = 1, $userId = 0): bool
    {
        $result = parent::publish($pks, $state, $userId);

        //Log
        if(is_array($pks)){
            if(count($pks)>1){
                $itemId=0;
                $data=json_encode(['ids'=>$pks]);
            }
            else{
                $itemId=(int)$pks[0];
                $data='';
            }
        }
        else
        {
            $itemId = (int)$pks;
            $data='';
        }
        $targetName = $this->itemName;
        $resultCode = $result ? LogHelper::RESULT_SUCCESS : LogHelper::RESULT_FAIL;
        $action = match ($state){
	        StateHelper::STATE_UNPUBLISHED => LogHelper::ACTION_UNPUBLISH,
	        StateHelper::STATE_PUBLISHED => LogHelper::ACTION_PUBLISH,
	        StateHelper::STATE_ARCHIVED => LogHelper::ACTION_ARCHIVE,
	        StateHelper::STATE_TRASHED => LogHelper::ACTION_TRASH
        };
        LogHelper::Add($action, $targetName, $itemId, $resultCode,$data,$userId);

        return $result;
    }
}
