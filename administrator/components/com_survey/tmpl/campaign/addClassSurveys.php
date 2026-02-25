<?php
defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Helper\ViewHelper;
HTMLHelper::_('behavior.formvalidator');
$xml = <<<XML
<form>
    <fieldset name="options" label="Dynamic Fieldset">
        <field
            name="min_class_size"
            type="number"
            label="Sĩ số tối thiểu"
            description="Chỉ tạo cuộc khảo sát cho những lớp đạt sĩ số tối thiểu này."
            default="0"
            required="true"
        />
        <field
                name="skip_late_classes"
                type="radio"
                default="0"
                label="Bỏ qua lớp muộn"
                description="Nếu chọn NO (mặc định) mà có lớp kết thúc sau thời điểm kết thúc
                đợt khảo sát thì sẽ báo lỗi. Nếu chọn YES, các lớp kết thúc muộn hơn thời điểm kết thúc 
                đợt khảo sát sẽ được tự động bỏ qua."
                class="btn-group btn-group-yesno"
        >
            <option value="0">JNO</option>
            <option value="1">JYES</option>
        </field>
        <field
                name="skip_existing_surveys"
                type="radio"
                default="1"
                label="Bỏ qua lớp đã tạo khảo sát"
                description="Nếu chọn YES (mặc định), hệ thống sẽ tự động bỏ qua những lớp học phần
                mà đã được tạo khảo sát (căn cứ theo tên cuộc khảo sát). Trong trường hợp chọn NO, 
                sẽ phát sinh thông báo lỗi nếu một lớp nào đó đã được tạo khảo sát."
                class="btn-group btn-group-yesno"
        >
            <option value="0">JNO</option>
            <option value="1">JYES</option>
        </field>
        <field
                name="respect_class_end"
                type="radio"
                default="1"
                label="Bắt đầu sau khi lớp học kết thúc"
                description="Nếu chọn YES (mặc định), chỉ bắt đầu cuộc khảo sát một lớp học phần
                khi lớp học đó đã kết thúc. Nếu chọn NO, thời điểm bắt đầu mọi cuộc khảo sát sẽ là
                thời điểm bắt đầu mặc định của đợt khảo sát, kể cả khi lớp học phần chưa kết thúc. 
                "
                class="btn-group btn-group-yesno"
        >
            <option value="0">JNO</option>
            <option value="1">JYES</option>
        </field>
        <field
                name="respect_campaign_start"
                type="radio"
                default="1"
                label="Không bắt đầu sớm hơn thời điểm mặc định"
                description="Nếu chọn YES thì kể cả khi lớp học phần kết thúc rất sớm,
                thời điểm bắt đầu khảo sát cũng không sớm hơn thời điểm bắt đầu mặc định 
                của đợt khảo sát. Nếu chọn NO, thời điểm bắt đầu khảo sát của lớp học phần
                sẽ là ngày ngay sau ngày kết thúc lớp học phần đó."
                class="btn-group btn-group-yesno"
                showon="respect_class_end:1"
        >
            <option value="0">JNO</option>
            <option value="1">JYES</option>
        </field>
    </fieldset>
</form>
XML;
$tempForm = new Form('com_survey.campaign.add_class_survey_options');
$tempForm->load($xml);

$layoutData = $this->listLayoutData;
$itemFields = $this->itemFields;
$campaign = $this->item;
$url = Route::_('index.php?option=com_survey&view=campaign&layout=addClassSurveys&campaign_id='.$campaign->id,false);
?>
<div>
    Hãy chọn các lớp học phần cần tạo cuộc khảo sát trong
    Đợt khảo sát &quot;<b><?php echo htmlspecialchars($campaign->title);?></b>&quot;.<br/>
    <ul>
        <li>Thời gian bắt đầu (mặc định): <?php echo $campaign->start_time;?></li>
        <li>Thời gian kết thúc (mặc định): <?php echo $campaign->end_time;?></li>
    </ul>
</div>
<form action="<?php echo $url; ?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="boxchecked" value="0"/>
    <input type="hidden" name="filter_order" value="<?php echo $layoutData->listOrderingField;?>"/>
    <input type="hidden" name="filter_order_Dir" value="<?php echo $layoutData->listOrderingDirection; ?>"/>
    <input type="hidden" name="campaign_id" value="<?php echo $campaign->id; ?>"/>
    <?php
    echo $tempForm->renderFieldset('options');
    echo HTMLHelper::_('form.token');
    if(!empty($layoutData->filterForm))
        echo LayoutHelper::render('joomla.searchtools.default', array('view'=>$layoutData));
    ViewHelper::printTableOfItems($this->listLayoutData, $this->itemFields);
    if(isset($layoutData->pagination))
        ViewHelper::printPaginationFooter($layoutData->pagination, $layoutData->showPaginationLimitBox);
    ?>
</form>
