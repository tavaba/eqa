<?php
namespace Kma\Component\Survey\Administrator\View\CampaignRespondents;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Survey\Administrator\Base\ItemsHtmlView;
use Kma\Component\Survey\Administrator\Model\CampaignModel;
use Kma\Component\Survey\Administrator\Model\CampaignRespondentsModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\Service\InlineProgressBar;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault(): void
    {
        /**
         * @var CampaignRespondentsModel $model
         */
        $model = $this->getModel();
        $isPersonList = $model->getState('filter.is_person');

        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new ListLayoutItemFieldOption('code', 'Mã',true,false,'text-center');
        if($isPersonList)
        {
            $option->customFieldset1[] = new ListLayoutItemFieldOption('lastname', 'Họ đệm');
            $option->customFieldset1[] = new ListLayoutItemFieldOption('firstname', 'Tên', true);
        }
        else
        {
            $option->customFieldset1[] = new ListLayoutItemFieldOption('name', 'Tên gọi');
        }

        $option->customFieldset1[] = new ListLayoutItemFieldOption('countSurveys', 'Số khảo sát', true, false, 'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('countResponded', 'Số phản hồi', true, false, 'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('progress', 'Tiến độ', true, false, 'text-center');

        $f = new ListLayoutItemFieldOption('progressBar', '');
        $f->printRaw = true;
        $f->columnCssClasses = 'w-25';
        $option->customFieldset1[] = $f;

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        //Prepare the model
        $campaignId = Factory::getApplication()->input->getInt('campaign_id');
        if(empty($campaignId))
            die('Invalid request');
        $model = $this->getModel();
        $model->setState('filter.campaign_id',$campaignId);

        /**
         * Get campaign item
         * @var CampaignModel $itemModel
         */
        $itemModel = ComponentHelper::createModel('Campaign');
        $this->item = $itemModel->getItem($campaignId);
        if(empty($this->item))
            die('Invalid request');

        //Check access permission
        if(!$itemModel->canMonitor($this->item))
            die('Bạn không có quyền truy cập trang này');

        //Prepare data for layout
        parent::prepareDataForLayoutDefault();

        //Data preprocessing
        $isPersonList = $model->getState('filter.is_person');
        if(!empty($this->layoutData->items))
        {
            $progressBarService = new InlineProgressBar();
            foreach ($this->layoutData->items as &$item)
            {
                if(!$isPersonList && $item->isPerson)
                    $item->name = implode(' ',[$item->lastname,$item->firstname]);
                $item->progressBar = $progressBarService->render(round($item->progress),$item->countResponded);
                $item->progress = round($item->progress).'%';
            }
        }

        //Add action params to keep URL parameters when click on actions
        $this->layoutData->formActionParams = [
            'view' => 'campaignrespondents',
            'campaign_id'=>$campaignId,
        ];
    }
    protected function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title('Người tham gia khảo sát');
        ToolbarHelper::appendGoHome();
        ToolbarHelper::appendCancelLink(Route::_('index.php?option=com_survey&view=campaigns'), 'JTOOLBAR_CLOSE');
    }
}