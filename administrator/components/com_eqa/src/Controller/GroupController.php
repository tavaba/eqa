<?php
namespace Kma\Component\Eqa\Administrator\Controller;
use Exception;
use Joomla\CMS\Response\JsonResponse;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Model\GroupModel;

defined('_JEXEC') or die();

class GroupController extends  EqaFormController {
	public function jsonGetLearners()
	{
		try {
			$groupId = $this->input->getInt('group_id');
			if (empty($groupId))
				throw new Exception('Không xác định được lớp học');
			$this->app->setHeader('Content-Type', 'application/json');

			/**
			 * @var GroupModel $model
			 */
			$model = $this->getModel();
			$groupLearners = $model->getLearners($groupId);
			if(empty($groupLearners))
				throw new Exception('Không có HVSV nào trong lớp này');

			$data = [];
			foreach ($groupLearners as $item) {
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