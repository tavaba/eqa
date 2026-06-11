<?php
namespace Kma\Component\Eqa\Administrator\View\Examroom; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\DataObject\ExamroomInfo;
use Kma\Component\Eqa\Administrator\Model\ExamroomModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Component\Eqa\Administrator\Base\ItemHtmlView;
use Kma\Library\Kma\Helper\FormHelper;
use Kma\Library\Kma\View\ListLayoutData;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Field\ExamineeanomalyField;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Model\ExamroomExamineesModel;

class HtmlView extends ItemHtmlView {
    protected ?ExamroomInfo $examroom;
	protected $examineeAnomalies;
	protected ?ExamineeanomalyField $anomalyField;
	protected string $anomalyType = 'exam';
    protected function prepareDataForLayoutExaminees() : void
    {
        $examroomId = Factory::getApplication()->input->getInt('examroom_id');

        //Toolbar
        $this->toolbarOption->clearAllTask();
        $this->toolbarOption->title = Text::_('COM_EQA_EXAMINEES_OF_EXAMROOM');
        $this->toolbarOption->taskCancel = true;

        /**
         * Prepare model
         * @var ExamroomExamineesModel $model
         */
        $model = ComponentHelper::createModel('examroomexaminees');
        $this->setModel($model,true);
        $model->setState('filter.examroom_id',$examroomId);   //Để dùng trong $model->getsListQuery()

        //Exam Item
        $this->examroom = DatabaseHelper::getExamroomInfo($examroomId);

        //List layout data
        $this->listLayoutData = new ListLayoutData();
        $this->loadCommonListLayoutData($this->listLayoutData, $model);
        $this->listLayoutData->formActionParams = [
            'view' => 'examroom',
            'layout' => 'examinees',
            'examroom_id' => $examroomId
        ];

        //Clear model state after successful retrieving data
        $model->setState('filter.examroom_id',null);   //Để dùng trong $model->getsListQuery()


        //Layout data preprocessing
        if(!empty($this->listLayoutData->items))
        {
            foreach ($this->listLayoutData->items as $item)
            {
                if($item->allowed)
                    $item->allowed = Text::_('JYES');
                else
                    $item->allowed = Text::_('JNO');
            }
        }


        //List Item Fields
        $this->listLayoutItemFields = new ListLayoutItemFields();
        $fields = $this->listLayoutItemFields;      //Just shorten the name
        $fields->sequence = ListLayoutItemFields::defaultFieldSequence();
        $fields->check = ListLayoutItemFields::defaultFieldCheck();

        $fields->customFieldset1[] = new ListLayoutItemFieldOption('examinee_code','COM_EQA_EXAMINEE_CODE_ABBR', true, false, 'text-center');
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

    }
	protected function addToolbarForLayoutExaminees(): void
	{
		ToolbarHelper::title($this->toolbarOption->title);
		ToolbarHelper::appendGoHome();
		ToolbarHelper::appendButton(null,'arrow-up-2','COM_EQA_EXAMSESSION','examsession.cancel');
		ToolbarHelper::appendButton(null,'arrow-up-2','COM_EQA_EXAM','exam.cancel');
		ToolbarHelper::appendButton(null,'arrow-up-2','COM_EQA_EXAMROOM','examroom.cancel');
		ToolbarHelper::appendButton('core.edit', 'plus-circle','COM_EQA_ADD_EXAMINEES','examroom.addExaminees',false,'btn btn-success');
		ToolbarHelper::appendButton('core.edit','shuffle','COM_EQA_CHANGE_EXAMROOM', 'examroom.change',true);
		ToolbarHelper::appendDelete('examroom.removeExaminees','COM_EQA_REMOVE_FROM_EXAMROOM','COM_EQA_MSG_CONFIRM_REMOVE_FROM_EXAMROOM');
	}

	/**
	 * Chuẩn bị dữ liệu cho layout danh sách thí sinh sát hạch của một phòng thi.
	 *
	 * @return void
	 * @since 2.0.6
	 */
	protected function prepareDataForLayoutAssessmentexaminees(): void
	{
		$app        = Factory::getApplication();
		$examroomId = $app->input->getInt('examroom_id');

		// Lấy thông tin phòng thi + kỳ sát hạch cho header
		$this->examroom   = DatabaseHelper::getExamroomInfo($examroomId);

		/**
		 * Chuẩn bị model
		 * @var \Kma\Component\Eqa\Administrator\Model\AssessmentLearnersModel $model
		 */
		$model = ComponentHelper::createModel('AssessmentLearners');
		$this->setModel($model, true);
		$model->setState('filter.assessment_id', (int) ($this->assessment->id ?? 0));
		$model->setState('filter.examroom_id',   $examroomId);

		// List layout data
		$this->listLayoutData = new ListLayoutData();
		$this->loadCommonListLayoutData($this->listLayoutData, $model);
		$this->listLayoutData->formActionParams = [
			'view'       => 'examroom',
			'layout'     => 'assessmentexaminees',
			'examroom_id' => $examroomId,
		];

		// Xóa filter state sau khi lấy dữ liệu
		$model->setState('filter.assessment_id', null);
		$model->setState('filter.examroom_id',   null);

		// Preprocessing từng item
		if (!empty($this->listLayoutData->items)) {
			foreach ($this->listLayoutData->items as &$item) {
				// Bất thường
				$item->anomaly_label = ($item->anomaly != \Kma\Component\Eqa\Administrator\Enum\Anomaly::None->value)
					? \Kma\Component\Eqa\Administrator\Enum\Anomaly::from($item->anomaly)->getLabel()
					: '—';

				// Kết quả điểm
				$item->score_display = isset($item->score) && $item->score !== null
					? number_format((float) $item->score, 2)
					: '—';

				// Trạng thái đạt/không đạt
				if ($item->passed === null) {
					$item->passed_html = '<span class="badge bg-secondary">Chưa có</span>';
				} elseif ($item->passed) {
					$item->passed_html = '<span class="badge bg-success">Đạt</span>';
				} else {
					$item->passed_html = '<span class="badge bg-danger">Không đạt</span>';
				}
			}
		}

		// Cấu hình cột hiển thị
		$fields = new ListLayoutItemFields();
		$fields->sequence = ListLayoutItemFields::defaultFieldSequence();

		$fields->customFieldset1[] = new ListLayoutItemFieldOption(
			'examinee_code', 'SBD', true, false, 'text-center'
		);
		$fields->customFieldset1[] = new ListLayoutItemFieldOption(
			'learner_code', 'Mã HVSV', true, false, 'text-center'
		);
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('learner_lastname','Họ đệm');
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('learner_firstname','Tên');
		$fields->customFieldset1[] = new ListLayoutItemFieldOption(
			'anomaly_label', 'Bất thường', false, false, 'text-center'
		);
		$fields->customFieldset1[] = new ListLayoutItemFieldOption(
			'score_display', 'Điểm', false, false, 'text-center'
		);
		$f           = new ListLayoutItemFieldOption('passed_html', 'Kết quả', false, false, 'text-center');
		$f->printRaw = true;
		$fields->customFieldset1[] = $f;

		$this->listLayoutItemFields = $fields;
	}

	/**
	 * Toolbar cho layout assessmentexaminees.
	 *
	 * @return void
	 * @since 2.0.6
	 */
	protected function addToolbarForLayoutAssessmentexaminees(): void
	{
		$examroomId   = Factory::getApplication()->input->getInt('examroom_id');

		$title = 'Danh sách thí sinh sát hạch';
		if (!empty($this->examroom->assessmentTitle)) {
			$title .= ' — ' . $this->examroom->assessmentTitle;
		}
		ToolbarHelper::title($title);

		// Nút quay lại danh sách phòng thi
		ToolbarHelper::appendGoHome();

		//Nút quay lại danh sách phòng thi
		$backUrl = Route::_('index.php?option=com_eqa&view=examrooms',false);
		ToolbarHelper::appendLink('core.manage', $backUrl, 'Phòng thi', 'arrow-up-2');

		// Nút quay lại danh sách thí sinh của kỳ sát hạch
		if(!empty($this->examroom->assessmentId))
		{
			$backUrl = Route::_(
				'index.php?option=com_eqa&view=assessmentlearners&assessment_id=' . $this->examroom->assessmentId,
				false
			);
			ToolbarHelper::appendLink('core.manage', $backUrl, 'Danh sách kỳ sát hạch', 'arrow-up-2');
		}
	}

    protected function prepareDataForLayoutAddexaminees(): void
    {
        //Init
        $app = Factory::getApplication();

        //Toolbar
        $this->toolbarOption->clearAllTask();
        $this->toolbarOption->title = Text::_('COM_EQA_ADD_EXAMINEES_TO_EXAMROOM');

        //Determine the exam id and get the exam
        $examroomId = $app->input->getInt('examroom_id');
        $this->examroom = DatabaseHelper::getExamroomInfo($examroomId);
	    $this->form = FormHelper::getBackendForm('com_eqa.addExamroomExaminees','addexamroomexaminees.xml', []);
    }
    protected function addToolbarForLayoutAddexaminees() : void
    {
        ToolbarHelper::title($this->toolbarOption->title);
        ToolbarHelper::appendButton('core.edit','save','JTOOLBAR_SAVE','examroom.addExaminees');
        $url = Route::_('index.php?option=com_eqa&view=examroom&layout=examinees&examroom_id='.$this->examroom->id,false);
        ToolbarHelper::appendLink(null,$url, 'JTOOLBAR_CANCEL', 'delete','btn btn-danger');
    }

	protected function prepareDataForLayoutAnomaly_bak(): void
	{
		//Init
		$app = Factory::getApplication();

		/**
		 * Get data
		 * @var ExamroomModel $model
		 */
		$examroomId = $app->input->getInt('examroom_id',0);
		$model = $this->getModel();
		$this->examroom = DatabaseHelper::getExamroomInfo($examroomId);
		$this->examineeAnomalies = $model->getExamineeAnomalies($examroomId);
		$this->anomalyField = new ExamineeanomalyField();
	}

	/**
	 * Chuẩn bị dữ liệu cho layout nhập thông tin bất thường.
	 * Phân nhánh theo tham số 'type' trong URL (exam | assessment).
	 *
	 * @return void
	 * @since 1.0
	 */
	protected function prepareDataForLayoutAnomaly(): void
	{
		$app        = Factory::getApplication();
		$examroomId = $app->input->getInt('examroom_id', 0);
		$type       = $app->input->getAlpha('type', 'exam'); // 'exam' | 'assessment'

		/** @var ExamroomModel $model */
		$model = $this->getModel();

		$this->examroom        = DatabaseHelper::getExamroomInfo($examroomId);
		$this->anomalyField    = new ExamineeanomalyField();
		$this->anomalyType     = $type; // truyền xuống template

		if ($type === 'assessment') {
			$this->examineeAnomalies = $model->getAssessmentExamineeAnomalies($examroomId);
		} else {
			$this->examineeAnomalies = $model->getExamineeAnomalies($examroomId);
		}
	}
	protected function addToolbarForLayoutAnomaly() : void
	{
		ToolbarHelper::title('Thông tin bất thường phòng thi');
		ToolbarHelper::appendButton('core.edit','save','JTOOLBAR_APPLY','examroom.applyAnomaly');
		ToolbarHelper::appendButton('core.edit','save','JTOOLBAR_SAVE','examroom.saveAnomaly');
		ToolbarHelper::appendCancelLink(Route::_('index.php?option=com_eqa&view=examrooms',false));
	}
}
