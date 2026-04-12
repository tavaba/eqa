<?php
namespace Kma\Component\Eqa\Administrator\View\Examexaminees; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Enum\Anomaly;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Enum\MailContextType;
use Kma\Library\Kma\Enum\MailCampaignStatus;
use Kma\Library\Kma\Helper\DatetimeHelper;

class HtmlView extends ItemsHtmlView {
    protected $exam;

	/**
	 * Lịch sử các chiến dịch email đã gửi cho môn thi này.
	 * Hiển thị ở cuối trang dưới dạng bảng tóm tắt.
	 *
	 * @var object[]
	 * @since 2.0.8
	 */
	protected array $campaignHistory = [];

	protected function configureItemFieldsForLayoutDefault():void{
        $this->itemFields = new ListLayoutItemFields();
        $fields = $this->itemFields;      //Just shorten the name
        $fields->sequence = ListLayoutItemFields::defaultFieldSequence();
        $fields->check = ListLayoutItemFields::defaultFieldCheck();

	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('code','COM_EQA_EXAMINEE_CODE_ABBR', true, false, 'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('learner_code','COM_EQA_LEARNER_CODE', true, false, 'text-center');
        $fields->customFieldset1[] = ListLayoutItemFields::defaultFieldLastname();
        $fields->customFieldset1[] = ListLayoutItemFields::defaultFieldFirstname();
        $f = new ListLayoutItemFieldOption('attempt', 'COM_EQA_EXAM_ATTEMPT_ABBR', true, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_EXAM_ATTEMPT');
        $fields->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('pam1', 'COM_EQA_PAM1_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_PAM1');
        $fields->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('pam2', 'COM_EQA_PAM2_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_PAM2');
        $fields->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('pam', 'COM_EQA_PAM_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_PAM');
        $fields->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('allowed', 'COM_EQA_ALLOWED_TO_TAKE_EXAM_ABBR', true, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_ALLOWED_TO_TAKE_EXAM');
        $fields->customFieldset1[] = $f;
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('debtor','COM_EQA_DEBT',true,false,'text-center');
		$f =new ListLayoutItemFieldOption('stimulation', 'KK', true, false, 'text-center');
		$f->titleDesc = 'Chế độ khuyến khích (nếu có)';
	    $fields->customFieldset1[] = $f;
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('anomaly', 'Bất thường');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('mark_orig', 'Thi', false, false, 'text-center');
	    $f = new ListLayoutItemFieldOption('mark_final', 'COM_EQA_MARK_FINALEXAM_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_MARK_FINALEXAM');
        $fields->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('module_mark', 'COM_EQA_MODULE_MARK_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_MODULE_MARK');
        $fields->customFieldset1[] = $f;
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('module_base4_mark', 'Hệ 4', false, false, 'text-center');
	    $f = new ListLayoutItemFieldOption('module_grade', 'COM_EQA_MODULE_GRADE_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_MODULE_GRADE');
        $fields->customFieldset1[] = $f;
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('conclusion', 'Kết luận');
    }
    protected function prepareDataForLayoutDefault(): void
    {
        //Prepare the model before calling parent
        $examId = Factory::getApplication()->input->get('exam_id');
        $model = $this->getModel();
        $model->setState('filter.exam_id',$examId);
        parent::prepareDataForLayoutDefault();

        //Tham số dưới đây sẽ khiến DisplayController luôn redirect tới view và layout mong muốn
        //giúp cố định 'exam_id'
        $this->layoutData->formActionParams = [
            'view'=>'examexaminees',
            'exam_id'=>$examId
        ];

        //Class Item
        $this->exam = DatabaseHelper::getExamInfo($examId);

        //Layout data preprocessing
        if(!empty($this->layoutData->items))
        {
            foreach ($this->layoutData->items as $item)
            {
                if($item->allowed)
                    $item->allowed = Text::_('JYES');
                else {
                    $item->allowed = Text::_('JNO');
					if($item->stimulation == StimulationHelper::TYPE_TRANS || $item->stimulation==StimulationHelper::TYPE_EXEMPT)
						$item->optionRowCssClass='table-success';
					else
						$item->optionRowCssClass='table-danger';
                }

	            if($item->debtor){
		            $item->debtor = Text::_('JYES');
		            $item->optionRowCssClass='table-danger';
	            }
	            else {
		            $item->debtor = Text::_('JNO');
	            }

				if($item->stimulation !== null)
					$item->stimulation = StimulationHelper::getStimulationType($item->stimulation);

				if($item->anomaly == Anomaly::None->value)
					$item->anomaly = '';
				else
					$item->anomaly = Anomaly::from($item->anomaly)->getLabel();

				if(!is_null($item->conclusion))
					$item->conclusion = Conclusion::from($item->conclusion)->getLabel();

				ExamHelper::normalizeMarks($item);

				if($item->module_base4_mark > 0)
	                $item->module_base4_mark = sprintf('%.1f',$item->module_base4_mark);
            }
        }

		//Inject some hidden form data
	    $this->layoutData->formHiddenFields = array_merge(
		    $this->layoutData->formHiddenFields ?? [],
		    [
			    'context_type' => MailContextType::Exam->value,
			    'context_id'   => $examId,
			    // return URL (base64) để sau khi notify xong redirect về view này
			    'return'       => base64_encode(
				    'index.php?option=com_eqa&view=examexaminees&exam_id=' . $examId
			    ),
		    ]
	    );

	    // Load lịch sử campaign — dùng trong sub-template default_campaign_history.php
	    if ($examId > 0) {
		    $this->campaignHistory = $this->loadCampaignHistory($examId);
	    }


    }
    protected function addToolbarForLayoutDefault(): void
    {
        $option = $this->toolbarOption;
        ToolbarHelper::title($option->title);
		ToolbarHelper::appendGoHome();
		$url = Route::_('index.php?option=com_eqa&view=examseasonExams&examseason_id='.$this->exam->examseasonId,false);
        ToolbarHelper::appendLink(null,$url,'COM_EQA_EXAM', 'arrow-up-2');
	    ToolbarHelper::appendButton('core.create','plus-2','COM_EQA_BUTTON_ADD_EXAMINEES','exam.addExaminees',false,'btn btn-success');
	    ToolbarHelper::appendButton('core.create','plus-2','Thêm HVSV chưa đạt','exam.addFailedExaminees',false,'btn btn-success');
        ToolbarHelper::appendDelete('exam.removeExaminees');
	    ToolbarHelper::appendButton('core.edit', 'loop','Khuyến khích','exam.stimulate',false, 'btn btn-success');
		$msg = 'Điều này có thể làm xáo trộn trạng thái thí sinh môn thi. Bạn có chắc muốn thực hiện?';
	    ToolbarHelper::appendConfirmButton('core.edit',$msg, 'loop','Nợ học phí','exam.updateDebt',false,'btn btn-success');
	    ToolbarHelper::appendConfirmButton('core.edit',$msg, 'loop','Nợ phí thi lại','exam.updateSecondAttemptPaymentStatus',false,'btn btn-success');
	    ToolbarHelper::appendButton('core.edit','smiley-happy','Xóa nợ','exam.clearDebt',true,'btn btn-success');
	    ToolbarHelper::appendButton('core.edit','smiley-sad','Ghi nợ','exam.markDebt',true,'btn btn-danger');
	    ToolbarHelper::appendButton('core.edit','pause','Hoãn thi','exam.delay',true, 'btn btn-danger');
	    ToolbarHelper::appendButton('core.edit','play','Hủy hoãn thi','exam.undoDelay',true, 'btn btn-success');
	    ToolbarHelper::appendButton('core.create','calendar','Chia phòng ngẫu nhiên','exam.distribute',false);
	    ToolbarHelper::appendButton('core.create','calendar','Chia phòng theo lớp','exam.distribute2',false);
	    ToolbarHelper::appendButton(null,'download','Xuất môn thi','exam.export');
	    ToolbarHelper::appendButton(null,'download','Xuất ca iTest','exam.exportitest');
	    ToolbarHelper::appendButton('core.manage','envelope','Gửi email','mailcampaigns.notify',false,'btn btn-success');
    }

	/**
	 * Lấy lịch sử các chiến dịch email đã gửi cho một môn thi.
	 *
	 * Kết quả được gán vào $this->campaignHistory và render trong sub-template
	 * tmpl/examexaminees/default_campaign_history.php thông qua $this->loadTemplate().
	 *
	 * @param  int  $examId
	 * @return object[]
	 * @since  2.0.9
	 */
	private function loadCampaignHistory(int $examId): array
	{
		$db    = \Joomla\CMS\Factory::getDbo();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('mc.id'),
				$db->quoteName('mc.status'),
				$db->quoteName('mc.total_count'),
				$db->quoteName('mc.sent_count'),
				$db->quoteName('mc.failed_count'),
				$db->quoteName('mc.created_at'),
				$db->quoteName('t.title',  'template_title'),
				$db->quoteName('u.name',   'creator_name'),
			])
			->from($db->quoteName('#__eqa_mail_campaigns', 'mc'))
			->leftJoin(
				$db->quoteName('#__eqa_mail_templates', 't') .
				' ON ' . $db->quoteName('t.id') . ' = ' . $db->quoteName('mc.template_id')
			)
			->leftJoin(
				$db->quoteName('#__users', 'u') .
				' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('mc.created_by')
			)
			->where($db->quoteName('mc.context_type') . ' = ' . MailContextType::Exam->value)
			->where($db->quoteName('mc.context_id')   . ' = ' . $examId)
			->order($db->quoteName('mc.created_at') . ' DESC')
			->setLimit(10);

		$db->setQuery($query);
		$items = $db->loadObjectList() ?: [];

		// Preprocessing: bổ sung status_label, status_badge, created_at_local
		foreach ($items as $item) {
			$statusEnum             = MailCampaignStatus::tryFrom((int) $item->status);
			$item->status_label     = $statusEnum?->getLabel()    ?? '?';
			$item->status_badge     = $statusEnum?->getBadgeClass() ?? 'bg-secondary';
			$item->created_at_local = !empty($item->created_at)
				? DatetimeHelper::convertToLocalTime((string) $item->created_at)
				: '—';
		}

		return $items;
	}

}
