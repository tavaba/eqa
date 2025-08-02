<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class SubjectsModel extends EqaListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('department_code','code','credits','finaltesttype','testbankyear','published', 'ordering');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'code', $direction = 'desc')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $query =  $db->getQuery(true);
        $columns = $db->quoteName(
            array('a.id','b.code','b.name','a.code', 'a.name','a.degree', 'a.credits', 'a.finaltesttype', 'a.testbankyear', 'a.published', 'a.ordering'),
            array('id','department_code','department_name','code','name','degree','credits', 'finaltesttype','testbankyear', 'published',  'ordering')
        );
        $query->from('#__eqa_subjects AS a')
            ->leftJoin('#__eqa_units AS b','a.unit_id = b.id')
            ->select($columns);

        /*
         * Special filter
         * Filter này được set và unset trong view 'Examseason' khi layout là 'addexams'
         * Ở đó, cần lấy danh sách các môn học (cũng tức là môn thi) để người dùng lựa chọn
         * nhằm thêm vào kỳ thi. Cần loại bớt những môn đã có sẵn trong kỳ thi đó.
         */
        $limitSubjectIds = $this->getState('filter.limit_subject_ids');
        if(is_array($limitSubjectIds)){
            $query->where($db->quoteName('a.id') . ' IN (' . implode(',', array_map('intval', $limitSubjectIds)) . ')');
        }

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
            $query->where('(a.code LIKE '.$like.' OR a.name LIKE '.$like.')');
        }

        $unit_id = $this->getState('filter.department_id');
        if(!empty($unit_id)){
            $query->where('a.unit_id = '.(int)$unit_id);
        }

        $degree = $this->getState('filter.degree');
        if(is_numeric($degree)){
            $query->where('a.degree = '.(int)$degree);
        }

        $finaltesttype = $this->getState('filter.testtype_code');
        if(is_numeric($finaltesttype)){
            $query->where('a.finaltesttype = '.(int)$finaltesttype);
        }

        $published = $this->getState('filter.published');
        if(is_numeric($published)){
            $query->where('a.published = '.(int)$published);
        }

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','department_code'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.department_id');
        $id .= ':' . $this->getState('filter.degree');
        $id .= ':' . $this->getState('filter.testtype_code');
        $id .= ':' . $this->getState('filter.published');
        return parent::getStoreId($id);
    }

	/**
	 * Thêm môn học vào CSDL. Các tham số phải được chuẩn hóa, được kiểm tra tính hợp lệ trước khi gọi hàm này
	 * @param   array     $subjects       Mảng chứa thông tin của các môn học được đọc từ file excel
	 * @param   boolean   $updateExisting Nếu true thì sẽ cập nhật lại dữ liệu cho các môn học đã tồn tại
	 * @return  void
	 * @throws Exception Controller chịu trách nhiệm xử lý exception nếu có lỗi xảy ra
	 * @since 1.2.0
	 */
	public function import(array $subjects, bool $updateExisting, string $username, string $time): void
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Get reversed unit map
		$query = $db->getQuery(true)
			->select('id, code')
			->from('#__eqa_units');
		$db->setQuery($query);
		$units = $db->loadAssocList('code', 'id');

		//2. Get existing subjects reversed map
		$query = $db->getQuery(true)
			->select('id, code')
			->from('#__eqa_subjects');
		$db->setQuery($query);
		$existingSubjects = $db->loadAssocList('code', 'id');

		//3. Insert new subjects and update existing ones
		$countExisting = 0;
		$db->transactionStart();
		try
		{
			foreach ($subjects as $subject)
			{
				//3.1. Prepare data for insert/update
				$rowIndex = $subject['row_index'];
				$unitCode = $subject['unit_code'];
				$subjectCode = $subject['subject_code'];
				$subjectName = $subject['subject_name'];
				$degree = $subject['degree'];
				$credits = $subject['credit_hours'];
				$finaltesttype = $subject['final_test_type'];
				$testbankyear = $subject['test_bank_year']?:'NULL';
				$finaltestweight=0.7;

				//3.1. Check if there is a an unit with the given code in database
				if (!isset($units[$unitCode]))
					throw new Exception("Không tồn tại đơn vị với mã {$unitCode} trong CSDL");
				$unitId = (int)$units[$unitCode];

				//3.2. Check if there is already a subject with the same code in database
				if(isset($existingSubjects[$subjectCode]))
				{
					$countExisting++;
					$subjectId = (int)$existingSubjects[$subjectCode];

					//Continue if we don't want to update existing subjects
					if(!$updateExisting)
						continue;

					//Or update the existing subject if it's required
					$query = $db->getQuery(true)
						->update('#__eqa_subjects')
						->set([
							'unit_id='.$unitId,
							'name='.$db->quote($subjectName),
							'degree='.$degree,
							'credits='.$credits,
							'finaltesttype='.$finaltesttype,
							'testbankyear='.$testbankyear,
							'updated_by='.$db->quote($username),
							'updated_at='.$db->quote($time)
						])
						->where('id='.$subjectId);
					$db->setQuery($query);
					if(!$db->execute())
						throw new Exception("{$rowIndex}: Cập nhật môn học {$subjectCode} thất bại");

				}

				//3.3. Insert new subject into database
				else{
					$data = [
						$unitId,
						$db->quote($subjectCode),
						$db->quote($subjectName),
						$degree,
						$credits,
						$finaltesttype,
						$finaltestweight,
						$testbankyear,
						$db->quote($username),
						$db->quote($time)
					];
					$tuple = implode(',',$data);
					$query = $db->getQuery(true)
						->insert('#__eqa_subjects')
						->columns(['unit_id','code','name','degree','credits','finaltesttype','finaltestweight','testbankyear','created_by','created_at'])
						->values($tuple);
					$db->setQuery($query);
					if(!$db->execute())
						throw new Exception("{$rowIndex}: Thêm môn học {$subjectCode} thất bại");
				}
			}

			//4. Commit transaction and send message to user
			$db->transactionCommit();
			$countAdded = count($subjects)-$countExisting;
			$app = Factory::getApplication();
			$app->enqueueMessage("Đã thêm {$countAdded} môn học mới", 'success');
			if($updateExisting)
				$app->enqueueMessage("Cập nhật {$countExisting} môn học", 'info');
			else
				$app->enqueueMessage("Bỏ qua {$countExisting} môn học đã tồn tại", 'warning');
		}
		catch(Exception $e){
			$db->transactionRollback();
			throw $e;
		}
	}
}