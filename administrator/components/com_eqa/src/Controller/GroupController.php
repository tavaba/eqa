<?php
namespace Kma\Component\Eqa\Administrator\Controller;
use Exception;
use Joomla\CMS\Response\JsonResponse;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

defined('_JEXEC') or die();

class GroupController extends  EqaFormController {
	public function jsonGetLearners()
	{
		try {
			$groupId = $this->input->getInt('group_id');
			if (empty($groupId))
				throw new Exception('Không xác định được lớp học');
			$this->app->setHeader('Content-Type', 'application/json');
			$model = $this->getModel();
			$learners = $model->getLearners($groupId);
			if(empty($learners))
				throw new Exception('Không có HVSV nào trong lớp này');

			$data = [];
			foreach ($learners as $item) {
				$data[] = [
					'value'=>$item->id,
					'name'=>$item->code . ' - ' . $item->lastname.' '.$item->firstname
				];
			}
		} catch (Exception $e) {
			$data = [
				'value'=>null,
				'name' => $e->getMessage()
			];
		}
		$json = new JsonResponse($data);
		echo $json;
		$this->app->close();
	}
}