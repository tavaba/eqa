<?php
namespace Kma\Component\Survey\Administrator\Controller;
defined('_JEXEC') or die('');

use Exception;
use Joomla\CMS\Router\Route;
use Kma\Component\Survey\Administrator\Helper\ExternalDataHelper;
use Kma\Component\Survey\Administrator\Model\ClassesModel;
use Kma\Component\Survey\Administrator\Model\ClassModel;
use Kma\Component\Survey\Administrator\Model\RespondentsModel;
use Kma\Library\Kma\Controller\AdminController;
use Kma\Library\Kma\Helper\DatetimeHelper;


class ClassesController extends AdminController
{
    protected function allowSync():bool
    {
        /**
         * @var ClassesModel $model
         */
        $model = $this->getModel('Classes');
        return $model->canSync();
    }
    public function sync():void
    {
        try
        {
            //Check token
            $this->checkToken();

            //Check permissions
            if(!$this->allowSync())
                throw new Exception('Bạn không có quyền thực hiện thao tác này');

            //Determine the last term of the last academic year in the #__survey_classes table.
            $model = $this->getModel('classes');
            [$lastAcademicyear, $lastTerm] = $model->getLastAcademicyearAndTerm();

            //Determin the max academic year for searching classes from the API.
            $currentYear = (int)date("Y");
            $temp = sprintf('%04d-%04d',$currentYear, $currentYear+1);
            $maxAcademicYear = DatetimeHelper::encodeAcademicYear($temp);
            $maxTerm = 3;
            if($lastAcademicyear==0)
            {
                $lastAcademicyear = $maxAcademicYear-1;
                $lastTerm = 1;
            }

            //Pull data from API and save to database.
            $countTotal = 0;
            $countAdded = 0;
            $countUpdated = 0;
            for($year = $lastAcademicyear; $year <= $maxAcademicYear; $year++)
            {
                for($term=($year == $lastAcademicyear?$lastTerm:1);$term<=$maxTerm;$term++)
                {
                    $academicyear = DatetimeHelper::decodeAcademicYear($year,'_');
                    $classes = ExternalDataHelper::fetchClasses($academicyear,$term);
                    if(empty($classes))
                        continue;
                    [$a, $u] = $model->updateClasses($classes);
                    $countTotal += count($classes);
                    $countAdded += $a;
                    $countUpdated += $u;
                }
            }

            //Show message and redirect back to list view.
            $msg = sprintf('Đã xử lý %d lớp học: %d được thêm mới, %d được cập nhật',
                $countTotal, $countAdded, $countUpdated);
            $this->setMessage($msg);
            $this->setRedirect(Route::_('index.php?option=com_survey&view=classes', false));
            return;
        }
        catch(Exception $e)
        {
            $this->setMessage('Error: '.$e->getMessage(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_survey&view=classes', false));
            return;
        }
    }
    public function syncLearners():void
    {
        $redirectUrl = Route::_('index.php?option=com_survey&view=classes',false);
        $this->setRedirect($redirectUrl);
        try {
            //Check token
            $this->checkToken();

            //Check permissions
            if(!$this->allowSync())
                throw new Exception('Bạn không có quyền thực hiện thao tác này');

            //Get class id from request
            $classIds = $this->input->get('cid',[], 'array');
            $classIds = array_filter($classIds,'intval');
            $classIds = array_unique($classIds);
            if(count($classIds)==0)
                throw new Exception('Bạn cần chọn lớp để cập nhật.');

            /**
             * Load model
             * @var ClassModel $model
             */
            $model = $this->getModel('class');

            //Iterate over each class id, load learners and update them into database.
            $count = 0;
            foreach ($classIds as $classId)
            {
                $class = $model->getItem($classId);
                $learners = ExternalDataHelper::fetchClassLearners($class->code);
                $class->size = count($learners);
                $class->learners = implode(',',$learners);

                //Convert $class item to array and save it to database.
                $model->save((array)$class);

                $count+=$class->size;
            }


            //Show message and redirect back to list view.
            $msg = sprintf('Đã cập nhật %d sinh viên cho %d lớp đã chọn.',$count, count($classIds));
            $this->setMessage($msg);

        }
        catch (Exception $e)
        {
            $this->setMessage('Error: '.$e->getMessage(),'error');
        }
    }

}
