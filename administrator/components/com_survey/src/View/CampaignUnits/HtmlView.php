<?php
namespace Kma\Component\Survey\Administrator\View\CampaignUnits;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Survey\Administrator\Base\ItemsHtmlView;
use Kma\Component\Survey\Administrator\Model\CampaignModel;
use Kma\Component\Survey\Administrator\Model\CampaignUnitsModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\Service\InlineProgressBar;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new ListLayoutItemFieldOption('code','Mã đơn vị',true);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('name','Tên đơn vị',true);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('countRespondents','Tổng lượt người',true,false, 'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('countResponded','Tổng lượt phản hồi', true, false, 'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('progress','Tỉ lệ phản hồi', true, false, 'text-center');
        $f = new ListLayoutItemFieldOption('progressBar','');
        $f->columnCssClasses = 'w-15';
        $f->printRaw = true;
        $option->customFieldset1[] = $f;

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        //Determine the id of the current campaign
        $campaignId = Factory::getApplication()->input->getInt('campaign_id');
        if(empty($campaignId))
            die('Invalid request');

        /**
         * Load the campaign object
         * @var CampaignModel $itemModel
         */
        $mvcFactory = ComponentHelper::getMVCFactory();
        $itemModel = $mvcFactory->createModel('Campaign');
        $this->item = $itemModel->getItem($campaignId);
        if(empty($this->item))
            die('Cannot load campaign');

        /*
         * Check permissions on monitoring the campaign
         */
        if(!$itemModel->canMonitor($this->item))
            die('You do not have permission to monitor this campaign');

        /**
         * Prepare the list model
         * @var CampaignUnitsModel $model
         */
        $model = $this->getModel();
        $model->setState('filter.campaign_id',$campaignId);

        //Get data from the list model
        parent::prepareDataForLayoutDefault();

        //Data preprocessing
        if(!empty($this->layoutData->items))
        {
            $progressBarService = new InlineProgressBar();
            foreach ($this->layoutData->items as &$item) {
                if($item->countRespondents>0)
                {
                    $item->progress = round($item->progress) . '%';
                    $item->progressBar = $progressBarService->render(floatval($item->progress),$item->countResponded);
                }
                else
                {
                    $item->progress = 'N/A';
                    $item->progressBar = '';
                }
            }
        }

        //Set the form params for the layout
        $this->layoutData->formActionParams = [
            'view'=>'campaignunits',
            'campaign_id'=>$campaignId
        ];

        /**
         * Load custum styles
         * There are style definitions that used to set column with for displaying
         * the progress bar.
         */
        $this->wa->useStyle('com_survey.style');
    }

    protected function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title('Tiến độ phản hồi theo đơn vị');
        ToolbarHelper::appendGoHome();
        $url = Route::_('index.php?option=com_survey&view=campaigns',false);
        ToolbarHelper::appendCancelLink($url,'JTOOLBAR_CLOSE');
    }
}