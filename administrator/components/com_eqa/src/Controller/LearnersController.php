<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xls;

class LearnersController extends EqaAdminController{
    public function import(): void
    {
        $fileFormField = 'file_learners';
        // Check for request forgeries.
        $this->checkToken();

        //Set redirect to list view in any case
        $this->setRedirect(
            JRoute::_(
                'index.php?option=' . $this->option . '&view=' . $this->view_list
                . $this->getRedirectToListAppend(),
                false
            )
        );

        $app = Factory::getApplication();
        $model = $this->getModel();
        $files = $this->input->files;
        $file = $files->get($fileFormField);

        if(empty($file['tmp_name'])){
            $this->setMessage(Text::_('COM_EQA_MSG_ERROR_NO_FILE_UPLOADED'), 'error');
            return;
        }

        //Access Check
        if(!$app->getIdentity()->authorise('core.create','com_eqa'))
        {
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_NOT_PERMITTED'), 'error');
            return;
        }

        //3. Attempt to import data (courses)

        //3.0. Lấy danh sách lớp học
        $db = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->from('#__eqa_groups')
            ->select('id, code');
        $db->setQuery($query);
        $groups = $db->loadAssocList('code','id');

        //3.1. Lấy danh sách người học
        $query = $db->getQuery(true)
            ->from('#__eqa_learners')
            ->select('id, code');
        $db->setQuery($query);
        $learners = $db->loadAssocList('code','id');

        //3.1. Import data
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $now = date('Y-m-d H:i:s');
        $learner = new Learner();
        $learner->created_by = GeneralHelper::getCurrentUsername();
        $learner->created_at = $now;

        // Check if the file is Excel 97 (.xls)
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);

        try {
            if ($fileExtension === 'xls') {
                $reader = new Xls();
            } else {
                // Assume it's an Excel 2007 or later (.xlsx)
                $reader = IOFactory::createReader('Xlsx');
            }
            $spreadsheet = $reader->load($file['tmp_name']);
        }
        catch (Exception $e){
            $msg = '<b>' . htmlentities($file['name']) . '</b>: ' . $e->getMessage();
            $app->enqueueMessage($msg,'error');
        }

        $sheetNumber = $spreadsheet->getSheetCount();
        for($sh=0; $sh<$sheetNumber; $sh++){
            $worksheet = $spreadsheet->getSheet($sh);
            $groupCode = trim($worksheet->getTitle());    //Worksheet's title must be identical to group's code.
            if(empty($groups[$groupCode])){
                $msg = Text::sprintf('COM_EQA_MSG_GROUP_S_DOES_NOT_EXIST',htmlentities($groupCode));
                $app->enqueueMessage($msg,'warning');
                continue;
            }

            $db->transactionStart(); //Start transaction for every sheet (group)
            try
            {
                $learner->group_id = $groups[$groupCode];
                $data = $worksheet->toArray('');
                $countTotal = sizeof($data)-1;              //Dòng đầu tiên là tiêu đề
                $countExisting=0;
                $countFailure=0;
                $countSuccess=0;
                $failedLearners='';
                for($i=1; $i<=$countTotal; $i++)           //Dòng 0 là tiêu đề ==> Bắt đầu từ dòng 1
                {
                    $learner->code = $data[$i][1];          //Cột B: Mã HVSV (Cột A là số thứ tự ==> bỏ qua)
                    $learner->lastname = $data[$i][2];      //Cột C: Họ đệm
                    $learner->firstname = $data[$i][3];     //Cột D: Tên

                    if(isset($learners[$learner->code])){
                        $countExisting++;
                        continue;
                    }

                    $table = $model->getTable();
                    if($table->save($learner))
                        $countSuccess++;
                    else {
                        $countFailure++;
                        $failedLearners = $failedLearners . $learner->code . ', ';
                    }
                }

                //Update sĩ số của lớp học
                $query = $db->getQuery(true)
                    ->update('#__eqa_groups')
                    ->set('size = size + '.(int)$countSuccess)
                    ->where('id = '.(int)$groups[$groupCode]);
                $db->setQuery($query);
                $db->execute();

                //Inform about this group import
                $msg = '<b>' . $groupCode . '</b>: ';
                $msg .= Text::sprintf('COM_EQA_MSG_IMPORT_N_TOTAL_N_SUCCESS_N_EXIST_N_FAILED',
                    $countTotal, $countSuccess, $countExisting, $countFailure);
                if($countFailure>0){
                    $msg .= ' ';
                    $msg .= Text::_('COM_EQA_MSG_LIST_OF_ITEMS_FAILED');
                    $msg .= ': ';
                    $msg .= substr($failedLearners,0,strlen($failedLearners)-2);
                }
                if($countSuccess == $countTotal)
                    $type='success';
                else if($countFailure>0)
                    $type='error';
                else
                    $type='warning';
                $app->enqueueMessage($msg,$type);
                $db->transactionCommit(); //Commit transaction for every sheet (group)
            }
            catch (Exception $e)
            {
                $db->transactionRollback();
                throw $e;
            }
        }
    }
	public function addDebtors()
	{
		// Check for request forgeries.
		$this->checkToken();

		//Set redirect to list view in any case
		$this->setRedirect(
			JRoute::_(
				'index.php?option=' . $this->option . '&view=' . $this->view_list
				. $this->getRedirectToListAppend(),
				false
			)
		);

		//Access Check
		$app = Factory::getApplication();
		if(!$app->getIdentity()->authorise('core.edit','com_eqa'))
		{
			$this->setMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_NOT_PERMITTED'), 'error');
			return;
		}

		//Get options
		$ignoreAbsentees = $this->app->input->getInt('ignore_absentees');
		$allMustExist = !$ignoreAbsentees;

		// Get learner ids
		$inputLearners = $this->app->input->getString('learners');
		$normalizedLearners = preg_replace('/[\s,;]+/', ' ', $inputLearners);
		$normalizedLearners = trim($normalizedLearners);
		$learnerCodes = explode(' ', $normalizedLearners);
		$learnerCodes = array_unique($learnerCodes);
		$learnerIds = DatabaseHelper::getLearnerIds($learnerCodes, $allMustExist);
		if(empty($learnerIds)){
			$this->setMessage('Không có HVSV nào có tên trong hệ thống','error');
			return;
		}

		// Get the model and do the job
		$model = $this->getModel();
		if($model->markDebt($learnerIds, 1))
		{
			$this->setMessage(Text::sprintf('COM_EQA_MSG_DEBT_SET_FOR_N_LEARNERS',sizeof($learnerIds)),'success');
		}
		else
		{
			$this->setMessage(Text::_('COM_EQA_DATABASE_ERROR'),'error');
		}
	}
	public function resetDebt()
	{
		// Check for request forgeries.
		$this->checkToken();

		//Set redirect to list view in any case
		$this->setRedirect(
			JRoute::_(
				'index.php?option=' . $this->option . '&view=' . $this->view_list
				. $this->getRedirectToListAppend(),
				false
			)
		);

		//Access Check
		$app = Factory::getApplication();
		if(!$app->getIdentity()->authorise('core.edit','com_eqa'))
		{
			$this->setMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_NOT_PERMITTED'), 'error');
			return;
		}

		// Get learner ids
		$learnerIds = (array) $this->input->get('cid', [], 'int');
		$learnerIds = array_filter($learnerIds);

		if (empty($learnerIds)) {
			$this->app->enqueueMessage(Text::_('COM_EQA_NO_ITEM_SELECTED'));
			return;
		}

		// Get the model and do the job
		$model = $this->getModel();
		if($model->markDebt($learnerIds, 0))
		{
			$this->setMessage(Text::sprintf('COM_EQA_MSG_DEBT_RESET_FOR_N_LEARNERS',sizeof($learnerIds)),'success');
		}
		else
		{
			$this->setMessage(Text::_('COM_EQA_DATABASE_ERROR'),'error');
		}
	}
	public function setDebt()
	{
		// Check for request forgeries.
		$this->checkToken();

		//Set redirect to list view in any case
		$this->setRedirect(
			JRoute::_(
				'index.php?option=' . $this->option . '&view=' . $this->view_list
				. $this->getRedirectToListAppend(),
				false
			)
		);

		//Access Check
		$app = Factory::getApplication();
		if(!$app->getIdentity()->authorise('core.edit','com_eqa'))
		{
			$this->setMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_NOT_PERMITTED'), 'error');
			return;
		}

		// Get learner ids
		$learnerIds = (array) $this->input->get('cid', [], 'int');
		$learnerIds = array_filter($learnerIds);

		if (empty($learnerIds)) {
			$this->app->enqueueMessage(Text::_('COM_EQA_NO_ITEM_SELECTED'));
			return;
		}

		// Get the model and do the job
		$model = $this->getModel();
		if($model->markDebt($learnerIds, 1))
		{
			$this->setMessage(Text::sprintf('COM_EQA_MSG_DEBT_SET_FOR_N_LEARNERS',sizeof($learnerIds)),'success');
		}
		else
		{
			$this->setMessage(Text::_('COM_EQA_DATABASE_ERROR'),'error');
		}
	}
}
class Learner{
    public string $code;
    public string $firstname;
    public string $lastname;
    public int $group_id;
    public string $created_by;
    public string $created_at;
}
