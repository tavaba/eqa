<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;
use Kma\Component\Eqa\Administrator\Base\CampusListModel;

class ExamsModel extends CampusListModel{

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('nexaminee','nexamroom','testtype','duration','kmonitor','kassess','status','examseason_id','campus_id','campus_name');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'id', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }

    /**
     * @since 2.1.6
     */
    protected function getCampusColumn(): string
    {
        return 'b.campus_id';
    }

    public function getListQuery()
    {
        $db = $this->getDatabase();
	    $subExamineeCount = 'SELECT COUNT(learner_id) FROM #__eqa_exam_learner WHERE exam_id=a.id';
	    $subExamroomCount = 'SELECT COUNT(DISTINCT examroom_id) FROM #__eqa_exam_learner WHERE examroom_id IS NOT NULL AND exam_id=a.id';

	    // Số thí sinh có quyền dự thi thực sự (loại trừ: không được phép thi,
	    // nợ học phí, được miễn thi hoặc quy đổi điểm)
	    $subEligibleCount = 'SELECT COUNT(1)'
		    . ' FROM #__eqa_exam_learner AS el'
		    . ' INNER JOIN #__eqa_class_learner AS cl'
		    .   ' ON cl.class_id = el.class_id AND cl.learner_id = el.learner_id'
		    . ' LEFT JOIN #__eqa_stimulations AS st'
		    .   ' ON st.id = el.stimulation_id'
		    . ' WHERE el.exam_id = a.id'
		    .   ' AND cl.allowed <> 0'
		    .   ' AND el.debtor = 0'
		    .   ' AND (el.stimulation_id IS NULL'
		    .     ' OR st.type NOT IN ('
		    .       StimulationHelper::TYPE_EXEMPT . ',' . StimulationHelper::TYPE_TRANS
		    .     '))';


	    $columns = $db->quoteName(
            array('a.id','b.name',   'a.code', 'a.name','a.testtype','a.status', 'a.usetestbank', 'a.questiondeadline',  'a.description'),
            array('id', 'examseason','code',   'name',  'testtype',  'status',   'usetestbank',  'questiondeadline',    'description')
        );
        $query =  parent::getListQuery();
        $query->from('#__eqa_exams AS a')
            ->leftJoin('#__eqa_examseasons AS b', 'a.examseason_id=b.id')
            ->select($columns)
	        ->select('('.$subExamineeCount.') AS nexaminee')
	        ->select('('.$subEligibleCount.') AS neligible')
	        ->select('('.$subExamroomCount.') AS nexamroom');

        // Lọc cứng theo cơ sở đào tạo, suy diễn qua kỳ thi (2.1.6).
        // Áp cho MỌI trường hợp, kể cả khi người dùng chưa chọn kỳ thi.
        $this->applyCampusScope($query);

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
            $query->where('a.name LIKE '.$like);
        }

        $examseasonId = $this->getState('filter.examseason_id');
        if(is_numeric($examseasonId))
            $query->where('a.examseason_id = '.(int)$examseasonId);

	    $academicyear = $this->getState('filter.academicyear');
	    if (is_numeric($academicyear)) {
		    $query->where('b.academicyear = ' . (int) $academicyear);
	    }

        $term = $this->getState('filter.term');
        if(is_numeric($term)){
            $query->where('b.term = '.(int)$term);
        }

		$subjectId = $this->getState('filter.subject_id');
		if(is_numeric($subjectId))
			$query->where('a.subject_id = '.(int)$subjectId);

        $testtype = $this->getState('filter.testtype');
        if(is_numeric($testtype)){
            $query->where('a.testtype = '.(int)$testtype);
        }

        $usetestbank = $this->getState('filter.usetestbank');
        if(is_numeric($usetestbank)){
            $query->where('a.usetestbank = '.(int)$usetestbank);
        }

        $status = $this->getState('filter.status');
        if(is_numeric($status)){
            $query->where('a.status = '.(int)$status);
        }

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','id'));
        $orderingDir = $query->db->escape($this->getState('list.direction','desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }

    /**
     * Bắt buộc gọi parent::getStoreId() để khóa cache chứa cơ sở đào tạo.
     *
     * @since 2.1.6
     */
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.examseason_id');
        $id .= ':' . $this->getState('filter.subject_id');
        $id .= ':' . $this->getState('filter.testtype');
        $id .= ':' . $this->getState('filter.status');

        return parent::getStoreId($id);
    }
}