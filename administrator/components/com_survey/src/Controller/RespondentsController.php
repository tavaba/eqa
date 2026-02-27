<?php
namespace Kma\Component\Survey\Administrator\Controller;
defined('_JEXEC') or die('');
use Exception;
use Joomla\CMS\Router\Route;
use Kma\Component\Survey\Administrator\Helper\ExternalDataHelper;
use Kma\Component\Survey\Administrator\Model\RespondentsModel;
use Kma\Library\Kma\Controller\AdminController;


class RespondentsController extends AdminController
{
    protected function allowSync():bool
    {
        /**
         * @var RespondentsModel $model
         */
        $model = $this->getModel('respondents');
        return $model->canSync();
    }
    public function syncLearners()
    {
        $this->setRedirect(Route::_('index.php?option=com_survey&view=respondents', false));
        try
        {
            //1. Check token
            $this->checkToken();

            //2. Check permissions
            if(!$this->allowSync())
                throw new Exception('Bạn không có quyền thực hiện thao tác này');

            //3. Get the earliest last sync time
            $model = $this->getModel('respondents');

            //4. Iterate over courses (groupes in fact) and update learners in database
            $sizeBefore = $model->getCurrentSize();
            $countTotal = 0;
            $countUpdated=0;
            foreach (ExternalDataHelper::iterateCourseLearners() as $data)
            {
                $courseCode = $data['course_code'];
                $learners = $data['learners'];
                $countTotal += count($learners);
                $countUpdated += $model->updateLearners($courseCode, $learners);
            }
            $sizeAfter = $model->getCurrentSize();
            $countInserted = $sizeAfter - $sizeBefore;

            //5. Show message and redirect
            $msg = sprintf('%d bản ghi được xử lý, %d HVSV được thêm mới, %d HVSV được cập nhật.',
                $countTotal, $countInserted, $countUpdated);
            $this->setMessage($msg);
        }
        catch (Exception $e)
        {
            $this->setMessage($e->getMessage(), 'error');
        }
    }
    public function syncEmployees()
    {
        $this->setRedirect(Route::_('index.php?option=com_survey&view=respondents', false));
        try
        {
            //1. Check token
            $this->checkToken();

            //2. Check permissions
            if(!$this->allowSync())
                throw new Exception('Bạn không có quyền thực hiện thao tác này');

            //3. Get the earliest last sync time
            $model = $this->getModel('respondents');

            //4. Iterate over courses (groupes in fact) and update learners in database
            $sizeBefore = $model->getCurrentSize();
            $employees = ExternalDataHelper::fetchEmployees();
            $model->updateEmployees($employees);
            $sizeAfter = $model->getCurrentSize();
            $countInserted = $sizeAfter - $sizeBefore;

            //5. Show message and redirect
            $msg = sprintf('%d bản ghi được xử lý, %d CB-GV-NV được thêm mới.',
                count($employees), $countInserted);
            $this->setMessage($msg);
        }
        catch (Exception $e)
        {
            $this->setMessage($e->getMessage(), 'error');
        }
    }
    public function syncVisitingTeachers()
    {
        $this->setRedirect(Route::_('index.php?option=com_survey&view=respondents', false));
        try
        {
            //1. Check token
            $this->checkToken();

            //2. Check permissions
            if(!$this->allowSync())
                throw new Exception('Bạn không có quyền thực hiện thao tác này');

            //3. Get the earliest last sync time
            $model = $this->getModel('respondents');

            //4. Iterate over courses (groupes in fact) and update learners in database
            $sizeBefore = $model->getCurrentSize();
            $visitingLecturers = ExternalDataHelper::fetchVisitingLecturers();
            $model->updateVisitingTeachers($visitingLecturers);
            $sizeAfter = $model->getCurrentSize();
            $countInserted = $sizeAfter - $sizeBefore;

            //5. Show message and redirect
            $msg = sprintf('%d bản ghi được xử lý, %d GV thỉnh giảng được thêm mới.',
                count($visitingLecturers), $countInserted);
            $this->setMessage($msg);
        }
        catch (Exception $e)
        {
            $this->setMessage($e->getMessage(), 'error');
        }
    }
}
