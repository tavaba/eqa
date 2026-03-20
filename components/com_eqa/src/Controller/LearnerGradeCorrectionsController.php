<?php
namespace Kma\Component\Eqa\Site\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use JRoute;
use Kma\Library\Kma\Controller\AdminController;

class LearnerGradeCorrectionsController extends AdminController
{
	public function getModel($name = '', $prefix = '', $config = [])
	{
		return parent::getModel('GradeCorrection', '', []);
	}
}

