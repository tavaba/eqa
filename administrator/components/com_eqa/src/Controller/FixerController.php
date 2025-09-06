<?php
namespace Kma\Component\Eqa\Administrator\Controller;
require_once JPATH_ROOT.'/vendor/autoload.php';
use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use stdClass;

defined('_JEXEC') or die();

class FixerController extends  EqaFormController
{
	public function recalc()
	{
		$input = $this->app->input;
		$examId = $input->getInt('exam_id');
		$learnerId= $input->getInt('learner_id');

		$db = DatabaseHelper::getDatabaseDriver();
		$db->setQuery("SELECT class_id FROM #__eqa_exam_learner WHERE exam_id=$examId AND learner_id=$learnerId");
		$classId = (int)$db->loadResult();


	}
}