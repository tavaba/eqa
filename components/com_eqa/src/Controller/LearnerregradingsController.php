<?php
namespace Kma\Component\Eqa\Site\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class LearnerregradingsController extends EqaAdminController
{
	public function getModel($name = '', $prefix = '', $config = [])
	{
		return parent::getModel('Regrading', '', []);
	}
}

