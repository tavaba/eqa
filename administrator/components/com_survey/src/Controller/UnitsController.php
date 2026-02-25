<?php
namespace Kma\Component\Survey\Administrator\Controller;
defined('_JEXEC') or die('');
use Exception;
use Joomla\CMS\Router\Route;
use Kma\Component\Survey\Administrator\Helper\ExternalDataHelper;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;
use Kma\Component\Survey\Administrator\Model\ClassesModel;
use Kma\Component\Survey\Administrator\Model\UnitsModel;
use Kma\Library\Kma\Controller\AdminController;


class UnitsController extends AdminController
{
    protected function allowSync():bool
    {
        /**
         * @var UnitsModel $model
         */
        $model = $this->getModel('Units');
        return $model->canSync();
    }
    public function syncCourses()
    {
        $this->setRedirect(Route::_('index.php?option=com_survey&view=units', false));
        try
        {
            //1. Check token
            $this->checkToken();

            //2. Check permissions
            if(!$this->allowSync())
                throw new Exception('Bạn không có quyền thực hiện thao tác này');

            //3. Retrieve update info from an external source
            $courses = ExternalDataHelper::fetchCourses();
            if(empty($courses))
            {
                $this->setMessage('Không có dữ liệu để cập nhật', 'info');
                return;
            }

            //5. Update units in database
            $model = $this->getModel('units');
            $count = $model->updateUnits(RespondentHelper::RESPONDENT_UNIT_TYPE_COURSE, $courses);

            //6. Show message and redirect
            $msg = sprintf('%d khóa học được thêm vào danh sách', $count);
            $this->setMessage($msg);
        }
        catch (Exception $e)
        {
            $this->setMessage($e->getMessage(), 'error');
        }
    }
    public function syncDepartments()
    {
        $this->setRedirect(Route::_('index.php?option=com_survey&view=units', false));
        try
        {
            //1. Check token
            $this->checkToken();

            //2. Check permissions
            if(!$this->allowSync())
                throw new Exception('Bạn không có quyền thực hiện thao tác này');

            //3. Retrieve update info from an external source
            $departments = ExternalDataHelper::fetchDepartments();
            if(empty($departments))
            {
                $this->setMessage('Không có dữ liệu để cập nhật', 'info');
                return;
            }

            //5. Update units in database
            $model = $this->getModel('units');
            $count = $model->updateUnits(RespondentHelper::RESPONDENT_UNIT_TYPE_DEPARTMENT, $departments);

            //6. Show message and redirect
            $msg = sprintf('%d phòng/khoa/ban được thêm vào danh sách', $count);
            $this->setMessage($msg);
        }
        catch (Exception $e)
        {
            $this->setMessage($e->getMessage(), 'error');
        }
    }
}
