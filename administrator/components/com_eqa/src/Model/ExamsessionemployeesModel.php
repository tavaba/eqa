<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class ExamsessionemployeesModel extends EqaListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('examroom');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'examroom', $direction = 'asc')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
		//Find out the examsession id
	    //this must be set by the VIEW
	    $examsessionId = $this->getState('filter.examsession_id');
		if(!is_numeric($examsessionId))
			return null;

        $db = $this->getDatabase();
        $columns = $db->quoteName(
            array('a.id', 'a.name',  'a.monitor1_id', 'a.monitor2_id', 'a.monitor3_id', 'a.examiner1_id', 'a.examiner2_id'),
            array('id',   'examroom','monitor1_id',   'monitor2_id',   'monitor3_id',   'examiner1_id',   'examiner2_id')
        );
        $query =  $db->getQuery(true)
            ->select($columns)
            ->from('#__eqa_examrooms AS a')
	        ->where('a.examsession_id='.$examsessionId);

        //Filtering

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','examroom'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
	protected function isValidData($data): bool
	{
		/*
		 * Điều kiện hợp lệ cần kiểm tra gồm
		 * 1) Giá trị (id) phải là số nguyên và không trùng lặp
		 * 2) Đã phân coi thi, chấm thi phòng nào thì phải phân đủ (và đúng)
		 * - Mỗi phòng thi có tối thiểu 2 CBCT hoặc 2 CBCTChT
		 * - Với CBCT:
		 *      + Phải phân công từ 1 đến 3, không được phép phân công 2, 3 khi chưa phân công 1
		 *      + Nếu có 2 CBCTChT rồi thì có thể có hoặc không có CBCT
		 *      + Nếu không có CBCTChT thì cần tối thiểu 2 CBCT
		*/

		$valid=true;

		//Kiểm tra giá trị nguyên và không trùng lặp
		$idSet = [];
		foreach ($data as $examroomId => $employeeIds){
			foreach ($employeeIds as $employeeId)
				if(!empty($employeeId))
				{
					if(is_numeric($employeeId))
						$idSet[]  = (int)$employeeId;
					else
					{
						$valid = false;
						break;
					}
				}
		}
		if(sizeof($idSet) != sizeof(array_unique($idSet)))
			$valid = false;
		if(!$valid)
			return false;

		//Kiểm tra vị trí, số lượng cán bộ đủ và đúng
		foreach ($data as $examroomId => $employeeIds)
		{
			//Kiểm tra CBCTChT
			$hasExaminers = false;
			if(empty($employeeIds['examiner1_id'])){
				if(!empty($employeeIds['examiner2_id']))
				{
					$valid = false;
					break;
				}
			}
			else //Đã có CBCTChT1
			{
				if(empty($employeeIds['examiner2_id']))
				{
					$valid = false;
					break;
				}
				$hasExaminers = true;
			}

			//Sau bước trên, hoặc đã có đủ CBCTChT, hoặc không có CBCTChT
			//Tiếp theo, kiểm tra các trường hợp hợp lệ đối với CBCT
			//1. Không (chưa) phân công CBCT
			if(empty($employeeIds['monitor1_id']) && empty($employeeIds['monitor2_id']) && empty($employeeIds['monitor3_id']))
				continue;
			//2. Đã có 2 CBCTChT và thêm một CBCT
			if($hasExaminers && !empty($employeeIds['monitor1_id']) && empty($employeeIds['monitor2_id']) && empty($employeeIds['monitor3_id']))
				continue;
			//3. Không có CBCTChT và có từ 2 CBCT trở lên
			if(!$hasExaminers && !empty($employeeIds['monitor1_id']) && !empty($employeeIds['monitor2_id']))
				continue;

			//Các trường hợp còn lại là không hợp lệ
			$valid = false;
			break;
		}
		return $valid;
	}
	public function save($examsessionId, $data):bool
	{
		$app = Factory::getApplication();

		//Check if the exam session has ben completed
		if(DatabaseHelper::isCompletedExamsession($examsessionId))
		{
			$app->enqueueMessage(Text::_('COM_EQA_MSG_EXAMSESSION_COMPLETED'),'error');
			return false;
		}

		//Check data validity
		if(!$this->isValidData($data))
		{
			$app->enqueueMessage(Text::_('COM_EQA_MSG_INVALID_DATA'),'error');
			return false;
		}

		//Save data
		$db = $this->getDatabase();
		$db->transactionStart();
		try
		{
			foreach ($data as $examroomId => $examroomEmployeeIds)
			{
				$setClause = [];
				foreach ($examroomEmployeeIds as $field => $value)
				{
					if(!empty($value))
						$setClause[] = $db->quoteName($field) . '=' . $value;
				}
				if(empty($setClause))
					continue;
				$query = $db->getQuery(true)
					->update('#__eqa_examrooms')
					->set($setClause)
					->where('id='.$examroomId);
				$db->setQuery($query);
				if(!$db->execute())
					throw new \Exception(Text::_('COM_EQA_MSG_DATABASE_ERROR'));
			}
		}
		catch (\Exception $e)
		{
			$db->transactionRollback();
			$app->enqueueMessage($e->getMessage(), 'error');
			return false;
		}
		$db->transactionCommit();
		$app->enqueueMessage(Text::_('COM_EQA_MSG_TASK_SUCCESS'),'success');
		return true;
	}
}