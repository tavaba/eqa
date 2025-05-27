<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;

defined('_JEXEC') or die();

class ClassModel extends EqaAdminModel {
    protected function prepareTable($table)
    {
        $table->size=null;  //Không cho phép người dùng cập nhật trực tiếp sĩ số
	    if(empty($table->lecturer_id))
			$table->lecturer_id=null;
	    if(empty($table->start))
			$table->start=null;
		if(empty($table->finish))
			$table->finish=null;
		if(empty($table->topicdeadline))
			$table->topicdeadline=null;
		if(empty($table->topicdate))
			$table->topicdate=null;
		if(empty($table->thesisdate))
			$table->thesisdate=null;
		if(empty($table->pamdeadline))
			$table->pamdeadline=null;
		if(empty($table->pamdate))
			$table->pamdate=null;
        parent::prepareTable($table);
    }
    public function addLearners(int $classId, array $learnerCodes): void
    {
        $db = $this->getDatabase();

        //Lấy danh sách HVSV các khóa, các lớp đang published
        $query = $db->getQuery(true)
            ->from('#__eqa_learners AS a')
            ->leftJoin('#__eqa_groups AS b', 'a.group_id = b.id')
            ->leftJoin('#__eqa_courses AS c', 'b.course_id = c.id')
            ->select('a.id AS id, a.code AS code')
            ->where('b.published>0 AND c.published>0');
        $db->setQuery($query);
        $learnerIds = $db->loadAssocList('code','id');

        $countAbsence = 0;
        $countError = 0;
        $countSuccess = 0;
        $listAbsence = '';
        $listError = '';
        foreach ($learnerCodes as $learnerCode){
            //Kiểm tra xem learner có tồn tại hay không
            if(!isset($learnerIds[$learnerCode])){
                $countAbsence++;
                $listAbsence .= $learnerCode . '; ';
                continue;
            }
            $db->transactionStart();
            $learnerId = $learnerIds[$learnerCode];
            try {
                //Thêm vào lớp học phần
                $query = $db->getQuery(true)
                    ->insert('#__eqa_class_learner')
                    ->columns('class_id, learner_id')
                    ->values("$classId, $learnerId");
                $db->setQuery($query);
                $db->execute();

                //Cập nhật sĩ số
                $query = $db->getQuery(true)
                    ->update('#__eqa_classes')
                    ->set('`size` = `size` + 1')
                    ->where('id = '.$classId);
                $db->setQuery($query);
                $db->execute();

                $db->transactionCommit();
                $countSuccess++;
            }
            catch (Exception $e){
                $db->transactionRollback();
                $countError++;
                $listError .= $learnerCode . '; ';
            }
        }

        //Set messages
        $app = Factory::getApplication();
        if($countSuccess>0){
            $msg = Text::sprintf('COM_EQA_MSG_CLASS_IMPORT_N_LEARNERS_SUCCESS', $countSuccess);
            $app->enqueueMessage($msg,'success');
        }
        if($countAbsence>0){
            $msg = Text::sprintf('COM_EQA_MSG_CLASS_IMPORT_N_LEARNERS_ABSENT',$countAbsence);
            $msg .= ': ' . $listAbsence;
            $app->enqueueMessage($msg,'error');
        }
        if($countError>0){
            $msg = Text::sprintf('COM_EQA_MSG_CLASS_IMPORT_N_LEARNERS_FAILED',$countError);
            $msg .= ': ' . $listError;
            $app->enqueueMessage($msg,'error');
        }
    }
    public function removeLearner(int $classId, int $learnerId) : bool{
        $app = Factory::getApplication();
        $db = $this->getDatabase();

        //Check if this learner (of this class) present in any exam
        $columns = $db->quoteName(
            array('b.code', 'b.firstname', 'b.lastname'),
            array('code','firstname','lastname')
        );
        $query = $db->getQuery(true)
            ->from('#__eqa_exam_learner AS a')
            ->leftJoin('#__eqa_learners AS b', 'a.learner_id=b.id')
            ->select($columns)
            ->where('a.class_id='.$classId. ' AND a.learner_id='.$learnerId);
        $db->setQuery($query);
        $learner = $db->loadObject();
        if(!empty($learner)){
            $temp = htmlentities("$learner->lastname $learner->firstname ($learner->code)");
            $msg = Text::sprintf('COM_EQA_MSG_CANNOT_DELETE_S_BECAUSE_OF_EXAM', $temp);
            $app->enqueueMessage($msg,'error');
            return false;
        }

        //Try to remove the learner from the class
        $db->transactionStart();
        try
        {
            //Remove the leaner from the class
            $query = $db->getQuery(true)
                ->delete('#__eqa_class_learner')
                ->where('class_id = '.(int)$classId.' AND learner_id = '.(int)$learnerId);
            $db->setQuery($query);
            $db->execute();

            //Decrement the class size
            $query = $db->getQuery(true)
                ->update('#__eqa_classes')
                ->set('`size` = `size`-1')
                ->where('id = '.$classId);
            $db->setQuery($query);
            $db->execute();

            //Inform
            $db->setQuery('SELECT * FROM #__eqa_learners WHERE id='.$learnerId);
            $learner = $db->loadObject();
            $learnerInfo = htmlentities("$learner->lastname $learner->firstname ($learner->code)");
            $msg = Text::sprintf('COM_EQA_MSG_S_REMOVED_FROM_THE_CLASS', $learnerInfo);
            $app->enqueueMessage($msg,'success');

            //Commit
            $db->transactionCommit();
            return true;
        }
        catch (Exception $e){
            $db->transactionRollback();
            $msg = Text::_('COM_EQA_MSG_ERROR_TASK_FAILED');
            $app->enqueueMessage($msg,'error');
            return false;
        }
    }
    public function setAllowed(int $classId, array $learnerIds, bool $allowed):bool
    {
        $app = Factory::getApplication();
        $db = $this->getDatabase();

        //HVSV chỉ có thể được CHO THI hay CẤM THI khi chưa có trong danh sách thi
        //Vì thế, trước hết sẽ lập danh sách HVSV của lớp học này đã có mặt trong các môn thi (1 hoặc nhiều lần)
        $query = $db->getQuery(true)
            ->select('learner_id')
            ->from('#__eqa_exam_learner')
            ->where('class_id=' . $classId . ' AND mark_orig IS NOT NULL');
        $db->setQuery($query);
        $exceptedLearnerIds = $db->loadColumn();

        //Chia danh sách HVSV ban đầu thành 2 phần: đã/chưa có trong danh sách thi
        $acceptedIds = [];
        $rejectedIds = [];
        foreach ($learnerIds as $id){
            if(in_array($id, $exceptedLearnerIds))
                $rejectedIds[] = $id;
            else
                $acceptedIds[] = $id;
        }

        //Xử lý trường hợp không hợp lệ
        if(!empty($rejectedIds)){
            $rejectedIdSet = '(' . implode(',', $rejectedIds) . ')';
            $query = $db->getQuery(true)
                ->from('#__eqa_learners')
                ->select('code')
                ->where('id IN '.$rejectedIdSet);
            $db->setQuery($query);
            $rejectedCodes = implode(', ', $db->loadColumn());
            $msg = Text::sprintf('COM_EQA_MSG_CANNOT_ALLOW_OR_DENY_S_TAKE_EXAM', htmlentities($rejectedCodes));
            $app->enqueueMessage($msg,'error');
        }

        //Thiết lập thuộc tính 'allowed' cho các trươờng hợp hợp lệ
        if(!empty($acceptedIds))
        {
            $acceptedIdSet = '(' . implode(',', $acceptedIds) . ')';

            //Lấy danh sách HVSV để xuất thông báo
            $query = $db->getQuery(true)
                ->from('#__eqa_learners')
                ->select('code')
                ->where('id IN '.$acceptedIdSet);
            $db->setQuery($query);
            $acceptedCodes = implode(', ', $db->loadColumn());

            //Thực hiện thay đổi 'allowed' thì cũng cần thay đổi 'expired'
            $valueAllowed = $allowed ? 1 : 0;
            $valueExpired = $allowed ? 0 : 1;
            $query = $db->getQuery(true)
                ->update('#__eqa_class_learner')
                ->set(array(
                    $db->quoteName('allowed').'='.$valueAllowed,
                    $db->quoteName('expired').'='.$valueExpired
                ))
                ->where('class_id = '. $classId . ' AND learner_id IN '. $acceptedIdSet);
            $db->setQuery($query);
            if($db->execute()) {
                $msg = Text::_('COM_EQA_MSG_TASK_SUCCESS') . ': ' . htmlentities($acceptedCodes);
                $app->enqueueMessage($msg, 'success');
                return true;
            }
            else {
                $msg = Text::_('COM_EQA_MSG_ERROR_TASK_FAILED') . ': ' . htmlentities($acceptedCodes);
                $app->enqueueMessage($msg, 'error');
                return false;
            }
        }

        return true;
    }
}
