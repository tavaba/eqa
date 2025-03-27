<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;

defined('_JEXEC') or die();

class MmproductionModel extends EqaAdminModel {
	public function importMmp(int $examId, array $examinerProductions, int $role): bool
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$app = Factory::getApplication();
		$columns = $db->quoteName(array('exam_id', 'examiner_id','role','quantity'));
		$valueSets = [];
		$count=0;
		foreach ($examinerProductions as $examiner=>$quantity){
			$examinerId = EmployeeHelper::getId($examiner);
			if(empty($examinerId))
			{
				$msg = Text::sprintf('Không tìm thấy <b>%s</b> trong CSDL', $examiner);
				$app->enqueueMessage($msg,'error');
				return false;
			}
			$values = [$examId, $examinerId, $role, $quantity];
			$valueSets[] = implode(',', $values);
			$count += $quantity;
		}

		$query = $db->getQuery(true)
			->insert('#__eqa_mmproductions')
			->columns($columns)
			->values($valueSets);
		$db->setQuery($query);
		if(!$db->execute())
		{
			$app->enqueueMessage('Lỗi truy vấn CSDL', 'error');
			return false;
		}
		$msg = Text::sprintf('Đã ghi nhận %d bài thi cho %d CBChT%d', $count, sizeof($examinerProductions), $role);
		$app->enqueueMessage($msg, 'success');
		return true;
	}
}
