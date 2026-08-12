<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Enum\Action;
use Kma\Component\Eqa\Administrator\Enum\ObjectType;
use Kma\Library\Kma\Controller\FormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Library\Kma\DataObject\LogEntry;

class SubjectController extends  FormController {
	public function stimulate()
	{
		//Redirect in any case
		$this->setRedirect(Route::_('index.php?option=com_eqa&view=stimulations',false));

		//Check token
		if(!$this->checkToken('post', false))
			return;

		//Check privilege
		if(!$this->app->getIdentity()->authorise('core.create', $this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'),'error');
			$this->writeLog(new LogEntry(
				action: Action::STIMULATE,
				objectType: ObjectType::Subject->value,
				isSuccess: false,
				objectId: $this->input->getInt('subject_id') ?: null,
				errorMessage: Text::_('COM_EQA_MSG_UNAUTHORISED'),
			));
			return;
		}

		//Get data
		$subjectId = $this->input->getInt('subject_id');
		$stimulType = $this->input->getInt('type');
		$stimulValue = $this->input->getFloat('value');
		$stimulReason = $this->input->getString('reason');
		$learnerCodes = $this->input->getString('learnercodelist');
		$learnerCodes = preg_replace('/[\s,;]+/', ' ', $learnerCodes);
		$learnerCodes = trim($learnerCodes);
		$learnerCodes = explode(' ',  $learnerCodes);
		$learnerIds = DatabaseHelper::getLearnerIds($learnerCodes);

		//Check data validity
		if(empty($subjectId))
		{
			$this->setMessage('Chưa chỉ định môn học', 'error');
			$this->writeLog(new LogEntry(
				action: Action::STIMULATE,
				objectType: ObjectType::Subject->value,
				isSuccess: false,
				errorMessage: 'Chưa chỉ định môn học',
			));
			return;
		}
		if(empty($learnerIds))
		{
			$this->setMessage('Không tìm thấy HVSV', 'error');
			$this->writeLog(new LogEntry(
				action: Action::STIMULATE,
				objectType: ObjectType::Subject->value,
				isSuccess: false,
				objectId: $subjectId,
				errorMessage: 'Không tìm thấy HVSV. Danh sách mã đã nhập: ' . implode(', ', $learnerCodes),
			));
			return;
		}
		if(empty($stimulValue) || $stimulValue<0 || $stimulValue>10)
		{
			$this->setMessage('Giá trị điểm khuyến khích không hợp lệ', 'error');
			$this->writeLog(new LogEntry(
				action: Action::STIMULATE,
				objectType: ObjectType::Subject->value,
				isSuccess: false,
				objectId: $subjectId,
				errorMessage: 'Giá trị điểm khuyến khích không hợp lệ: ' . $stimulValue,
			));
			return;
		}
		if(empty($stimulReason))
		{
			$this->setMessage('Cần có thông tin về lý do khuyến khích','error');
			$this->writeLog(new LogEntry(
				action: Action::STIMULATE,
				objectType: ObjectType::Subject->value,
				isSuccess: false,
				objectId: $subjectId,
				errorMessage: 'Cần có thông tin về lý do khuyến khích',
			));
			return;
		}

		//Process
		$timeStamp = date('Y-m-d H:i:s');
		$username = GeneralHelper::getCurrentUsername();
		$model = $this->createModel('stimulation');
		$model->stimulate($subjectId, $learnerIds, $stimulType, $stimulValue, $stimulReason, $timeStamp, $username);
	}
	public function clearStimulations()
	{
		//Redirect in any case
		$this->setRedirect(Route::_('index.php?option=com_eqa&view=stimulations',false));

		//Check token
		if(!$this->checkToken('post', false))
			return;

		//Check privilege
		if(!$this->app->getIdentity()->authorise('core.delete', $this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'),'error');
			$this->writeLog(new LogEntry(
				action: Action::CLEAR_STIMULATIONS,
				objectType: ObjectType::Subject->value,
				isSuccess: false,
				errorMessage: Text::_('COM_EQA_MSG_UNAUTHORISED'),
			));
			return;
		}

		//Get data
		$stimulIds = (array) $this->input->get('cid', [], 'int');

		if (empty($stimulIds))
		{
			$this->app->enqueueMessage('COM_EQA_NO_ITEM_SELECTED', 'warning');
			return;
		}

		// Process
		$model = $this->createModel('stimulation');
		$model->clear($stimulIds);
	}
}

