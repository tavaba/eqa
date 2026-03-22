<?php
namespace Kma\Component\Eqa\Administrator\Controller;
require_once JPATH_ROOT.'/vendor/autoload.php';

use Joomla\CMS\Factory;
use Joomla\Database\ParameterType;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Extension\EqaComponent;
use Kma\Library\Kma\Controller\FormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Service\LogService;

defined('_JEXEC') or die();

class FixerController extends  FormController
{
	public function testDI()
	{
		echo 'Testing DI...<br>';
		/**
		 * @var EqaComponent $component
		 */
		$otherLogService = Factory::getContainer()->get(LogService::class);
		$component = ComponentHelper::getComponent();
		$logService = $component->getLogService();
		$configService = $component->getConfigService();
		echo 'Parent organization: ' . $configService->getParentOrganization() . '<br>';
		echo 'Organization: ' . $configService->getOrganization() . '<br>';
	}
}