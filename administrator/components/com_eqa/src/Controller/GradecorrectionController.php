<?php
namespace Kma\Component\Eqa\Administrator\Controller;
require_once JPATH_ROOT.'/vendor/autoload.php';
use Exception;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Paragraph;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Style\Tab;

defined('_JEXEC') or die();

class GradecorrectionController extends  EqaFormController {
	public function accept()
	{
		try
		{
			//1. Check token
			$this->checkToken();

			//2. Check permission
			if(!$this->app->getIdentity()->authorise('core.edit', $this->option))
				throw new Exception('Bạn không có quyền truy cập chức năng này');

			//3. Get item id from the post data
			$post = $this->input->post;
			$cids = $post->get('cid', []);
			if(!count($cids))
				throw new Exception("Chưa chọn yêu cầu đính chính nào");
			$itemId = $cids[0];

			//4. Accept the grade correction request
			$currentUsername = $this->app->getIdentity()->username;
			$currentTime = date('Y-m-d H:i:s');
			$model = $this->getModel('gradecorrection');
			$model->accept($itemId, $currentUsername, $currentTime);

			//5. Redirect back
			//   (The success message should be sent by the model)
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=gradecorrections', false));
		}
		catch (Exception $e)
		{
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=gradecorrections', false));
			$this->setMessage($e->getMessage(), 'error');
		}
	}
	public function reject()
	{
		/**
		 * Việc từ chối một yêu cầu được thực hiện qua 2 giai đoạn
		 * Pha 1: Hiển thị form để người dùng nhập lý do từ chối. Cách nhận biết pha 1
		 *        là trong post data sẽ có 'cid'
		 * Pha 2: Thực hiện việc từ chối và lưu lại thông tin vào CSDL. Cách nhận biết pha 2
		 *        là trong post data sẽ có 'description'
		 */

		try
		{
			//1. Check token
			$this->checkToken();

			//2. Check permission
			if(!$this->app->getIdentity()->authorise('core.edit', $this->option))
				throw new Exception('Bạn không có quyền truy cập chức năng này');

			//Phase 1. Get item id from the post data and show the rejection form
			$post = $this->input->post;
			$cids = $post->get('cid', []);
			if(!empty($cids))
			{
				$itemId = $cids[0];
				$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=gradecorrection&layout=reject&id='.$itemId, false));
				return;
			}

			//Phase 2. Reject the grade correction request
			$itemId = $post->get('id', '');
			$description = $post->getString('description');
			if(empty($itemId) || empty($description))
				throw new Exception('Dữ liệu không hợp lệ');
			$currentUsername = $this->app->getIdentity()->username;
			$currentTime = date('Y-m-d H:i:s');
			$model = $this->getModel('gradecorrection');
			$model->reject($itemId, $description, $currentUsername, $currentTime);
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=gradecorrections', false));
		}
		catch(Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=gradecorrections', false));
		}
	}
	public function downloadReviewForm()
	{
		try
		{
			//1. Check token
			$this->checkToken();

			//2. Check permission
			if(!$this->app->getIdentity()->authorise('core.manage', $this->option))
				throw new Exception('Bạn không có quyền truy cập chức năng này');

			//3. Retrieve item id from post data
			$cids = $this->input->post->get('cid', [],'int');
			if(count($cids)==0)
				throw new Exception('Không tìm thấy yêu cầu đính chính');
			$itemId = $cids[0];

			//4. Load item info
			$model = $this->getModel('gradecorrection');
			$request = $model->getRequest($itemId);
			if(is_null($request))
				throw new Exception('Không tìm thấy yêu cầu đính chính');
			$learner = implode(' ', [$request->learnerLastname, $request->learnerFirstname]) . ' (' . $request->learnerCode . ')';

			if($request->status == ExamHelper::EXAM_PPAA_STATUS_INIT)
				throw new Exception('Yêu cầu đính chính của <b>'. htmlspecialchars($learner).'</b> chưa được phê duyệt');
			if($request->status == ExamHelper::EXAM_PPAA_STATUS_REJECTED)
				throw new Exception('Yêu cầu đính chính của <b>'. htmlspecialchars($learner).'</b> đã bị từ chối');

			//5. Create document
			$phpWord = new PhpWord();
			IOHelper::writeGradeCorrectionForm($phpWord, $request);

			//6. Let user download the file
			$fileName = "Phiếu đính chính điểm. $learner.docx";
			IOHelper::sendHttpDocx($phpWord, $fileName);
			jexit();
		}
		catch(Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=gradecorrections', false));
			return;
		}
	}

	public function correct()
	{
		/**
		 * Việc đính chính được thực hiện qua 2 giai đoạn
		 * Pha 1: Hiển thị form để người dùng nhập thông tin (kết quả xử lý). Cách nhận biết pha 1
		 *        là trong post data sẽ có 'cid'
		 * Pha 2: Thực hiện việc đính chính và lưu lại thông tin vào CSDL. Cách nhận biết pha 2
		 *        là trong post data sẽ có 'description'
		 */
		try
		{
			//1. Check token
			$this->checkToken();

			//2. Check permission
			if(!$this->app->getIdentity()->authorise('core.edit', $this->option))
				throw new Exception('Bạn không có quyền truy cập chức năng này');

			//Phase 1. Get item id from the post data and show the correction form
			$post = $this->input->post;
			$cids = $post->get('cid', []);
			if(!empty($cids))
			{
				$itemId = $cids[0];
				$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=gradecorrection&layout=correct&id='.$itemId, false));
				return;
			}

			//Phase 2. Correct the grade correction request
			//Get jform data
			$formData = $post->get('jform', [], 'array');
			$currentUsername = $this->app->getIdentity()->username;
			$currentTime = date('Y-m-d H:i:s');
			$model = $this->getModel('gradecorrection');
			$model->correct($formData, $currentUsername, $currentTime);

			//Redirect back
			$this->setMessage('Đã lưu kết quả xử lý cho yêu cầu đính chính', 'success');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=gradecorrections', false));
			return;
		}
		catch(Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=gradecorrections', false));
			return;
		}

	}
}