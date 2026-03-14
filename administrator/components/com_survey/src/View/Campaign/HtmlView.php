<?php
namespace Kma\Component\Survey\Administrator\View\Campaign;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Survey\Administrator\Base\ItemHtmlView;
use Kma\Component\Survey\Administrator\Model\CampaignModel;
use Kma\Component\Survey\Administrator\Model\ClassesModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ListLayoutData;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

defined('_JEXEC') or die;

class HtmlView extends ItemHtmlView
{
    protected ListLayoutItemFields $itemFields;
    protected ListLayoutData $listLayoutData;
    public function prepareDataForLayoutAddClassSurveys()
    {
        //Determin the campaign ID and load the campaign item
        $campaignId = Factory::getApplication()->input->getInt('campaign_id');
        if(empty($campaignId))
            die('No campaign id provided');
        $this->item = $this->getModel()->getItem($campaignId);

        //Configure item fields for displaying in layout
        $this->itemFields = new ListLayoutItemFields();
        $this->itemFields->sequence = ListLayoutItemFields::defaultFieldSequence();
        $this->itemFields->check = ListLayoutItemFields::defaultFieldCheck();
        $this->itemFields->customFieldset1 = array();
        $this->itemFields->customFieldset1[] = new ListLayoutItemFieldOption('subject', 'Môn học');
        $this->itemFields->customFieldset1[] = new ListLayoutItemFieldOption('code', 'Mã lớp', true);
        $this->itemFields->customFieldset1[] = new ListLayoutItemFieldOption('lecturer', 'Giảng viên');
        $this->itemFields->customFieldset1[] = new ListLayoutItemFieldOption('size', 'Sĩ số',true);
        $this->itemFields->customFieldset1[] = new ListLayoutItemFieldOption('academicyear', 'Năm học',true);
        $this->itemFields->customFieldset1[] = new ListLayoutItemFieldOption('term', 'Học kỳ',true);
        $this->itemFields->customFieldset1[] = new ListLayoutItemFieldOption('startDate', 'Bắt đầu',true);
        $this->itemFields->customFieldset1[] = new ListLayoutItemFieldOption('endDate', 'Kết thúc',true);

	    /**
	     * Set up the list model
	     * @var ClassesModel $listModel
	     */
        $listModel = ComponentHelper::createModel('classes');
        $this->setModel($listModel,true);

        //Prepare data for display in layout
        $this->listLayoutData = new ListLayoutData();
        $this->loadCommonListLayoutData($this->listLayoutData,$listModel);
    }
    public function addToolbarForLayoutAddClassSurveys()
    {
        /**
         * @var CampaignModel $campaignModel
         */
        $campaignModel = ComponentHelper::createModel('Campaign');
        $campaign = $this->item;

        ToolbarHelper::title('Thêm khảo sát lớp học phần');

        if($campaignModel->canEdit($campaign))
            ToolbarHelper::save('campaign.addClassSurveys');

        $cancelUrl = Route::_('index.php?option=com_survey&view=campaignsurveys&campaign_id='.$this->item->id,false);
        ToolbarHelper::appendCancelLink($cancelUrl);
    }
}