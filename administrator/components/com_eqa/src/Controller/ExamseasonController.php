<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExamseasonController extends EqaFormController
{
	public function addExams()
	{
		$examseasonId = $this->app->input->getInt('examseason_id');

		// Access check
		if (!$this->app->getIdentity()->authorise('core.create', $this->option)) {
			// Set the internal error and also the redirect error.
			$this->setMessage(Text::_('JLIB_APPLICATION_ERROR_BATCH_CANNOT_CREATE'), 'error');
			$this->setRedirect(
				Route::_(
					'index.php?option=com_eqa&view=exams&filter[examseason_id]=' . $examseasonId,
					false
				)
			);
			return;
		}

		//Xác định pha của nhiệm vụ
		$phase = $this->app->input->getAlnum('phase', '');
		if ($phase !== 'getdata') {
			// Redirect to the 'add learners' layout (with a form)
			$this->setRedirect(
				Route::_(
					'index.php?option=com_eqa&view=examseason&layout=addexams&examseason_id=' . $examseasonId,
					false
				)
			);
		} else    //$phase == 'getdata'
		{
			//Pha này thì cần check token
			$this->checkToken();

			// Get exams (subjects) to add from the request.
			$cid = (array)$this->input->get('cid', [], 'int');

			// Remove zero values resulting from input filter
			$cid = array_filter($cid);

			if (empty($cid)) {
				$this->app->getLogger()->warning(Text::_($this->text_prefix . '_NO_ITEM_SELECTED'), ['category' => 'jerror']);
			} else {
				// Get the model and add exams
				$model = $this->getModel();
				$model->addExams($examseasonId, $cid);
			}

			//Add xong thì redirect về trang xem danh sách môn thi
			$this->setRedirect(
				Route::_(
					'index.php?option=com_eqa&view=exams&filter[examseason_id]=' . $examseasonId,
					false
				)
			);
		}
	}
	public function addRetakeExams()
	{
		//Xác định kỳ thi
		$examseasonId = $this->app->input->getInt('examseason_id');
		if(!$examseasonId)
		{
			$this->setMessage('Không xác định được kỳ thi.', 'error');
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=examseasons',false));
			return;
		}

		//Set redirect in any case
		$this->setRedirect(
			Route::_(
				'index.php?option=com_eqa&view=exams&filter[examseason_id]=' . $examseasonId,
				false
			)
		);
		$this->setMessage("Chức năng này chưa hoàn thiện. Cần phải giới hạn năm học, học kỳ để không bị loạn", 'error');
		return;


		// Access check
		if (!$this->app->getIdentity()->authorise('core.create', $this->option)) {
			// Set the internal error and also the redirect error.
			$this->setMessage(Text::_('JLIB_APPLICATION_ERROR_BATCH_CANNOT_CREATE'), 'error');
			return;
		}

		//Thêm môn thi lại vào kỳ thi
		$model = $this->getModel();
		$model->addRetakeExams($examseasonId);

	}
	protected function setPpaaReqStatus(bool $status){
		//Check token
		$this->checkToken();

		//Redirect in any case
		$this->setRedirect(\JRoute::_('index.php?option=com_eqa&view=examseasons',false));

		//Check permission
		if(!$this->app->getIdentity()->authorise('core.edit',$this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'),'error');
			return;
		}

		//Get data
		$cid = $this->input->post->get('cid',[],'int');
		if(empty($cid)){
			$this->setMessage(Text::_('COM_EQA_MSG_NO_ITEM_SPECIFIED'),'error');
			return;
		}
		$examseasonId = $cid[0];
		$model = $this->getModel();

		if($status)
			$model->enablePpaaReq($examseasonId);
		else
			$model->disablePpaaReq($examseasonId);
	}
	public function enablePpaaReq()
	{
		$this->setPpaaReqStatus(true);
	}
	public function disablePpaaReq()
	{
		$this->setPpaaReqStatus(false);
	}
	public function exportExaminees()
	{
		//Check token
		$this->checkToken();

		//Redirect in any case
		$this->setRedirect(\JRoute::_('index.php?option=com_eqa&view=examseasons',false));

		//Check permission
		if(!$this->app->getIdentity()->authorise('core.manage',$this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'),'error');
			return;
		}

		//Get data
		$cid = $this->input->post->get('cid',[],'int');
		if(empty($cid)){
			$this->setMessage(Text::_('COM_EQA_MSG_NO_ITEM_SPECIFIED'),'error');
			return;
		}
		$examseasonId = $cid[0];
		$model = $this->getModel();
		$examinees = $model->getExaminees($examseasonId);

		//Initialize a spreadsheet
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getSheet(0);
		IOHelper::writeExamseasonExaminees($sheet, $examinees);

		//Send file
		IOHelper::sendHttpXlsx($spreadsheet,'Danh sách thí sinh kỳ thi.xlsx');
		exit();
	}
}
