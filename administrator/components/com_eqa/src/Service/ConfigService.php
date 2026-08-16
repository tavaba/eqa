<?php
namespace Kma\Component\Eqa\Administrator\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;
use Kma\Component\Eqa\Administrator\Enum\FeeMode;
use Kma\Component\Eqa\Administrator\Enum\ResitMarkLimitMode;
use InvalidArgumentException;
use Kma\Library\Kma\Helper\ComponentHelper as KmaComponentHelper;
use RuntimeException;

/**
 * Service đọc các tham số cấu hình của component com_eqa.
 *
 * Cách dùng:
 *   $config = new ConfigService();
 *   $org    = $config->getOrganization();
 *
 * @since 2.0.5
 */
class ConfigService
{
	/**
	 * Service quản lý cơ sở đào tạo.
	 *
	 * Có thể được inject qua constructor (từ provider.php) hoặc resolve lười
	 * từ component khi ConfigService được khởi tạo trực tiếp bằng `new`.
	 *
	 * @var    CampusService|null
	 * @since  2.1.6
	 */
	private ?CampusService $campusService;

	/**
	 * Tham số cấu hình của component com_eqa.
	 *
	 * @var    Registry
	 * @since  2.0.5
	 */
	private Registry $params;

	/**
	 * Id cơ sở đào tạo mà instance này gắn với.
	 *
	 * Giá trị 0 nghĩa là instance chưa gắn với cơ sở nào — đây là trạng thái
	 * của instance dùng chung trong DI container. Mọi getter đọc tham số riêng
	 * theo cơ sở sẽ ném lỗi khi gặp giá trị này.
	 *
	 * Chỉ được gán qua forCampus(); không có setter, để instance dùng chung
	 * không bao giờ bị thay đổi trạng thái.
	 *
	 * @var    int
	 * @since  2.1.6
	 */
	private int $campusId = 0;

	/**
	 * Constructor — nạp tham số cấu hình dùng chung của com_eqa.
	 *
	 * @param   CampusService|null  $campusService  Tùy chọn; nếu null sẽ được
	 *                                              resolve từ component khi cần.
	 *
	 * @since  2.0.5
	 */
	public function __construct(?CampusService $campusService = null)
	{
		$this->params        = ComponentHelper::getParams('com_eqa');
		$this->campusService = $campusService;
	}

	/**
	 * Trả về một bản sao của service đã gắn với một cơ sở đào tạo cụ thể.
	 *
	 * Instance gốc không bị thay đổi. Bản sao dùng chung tham chiếu tới
	 * CampusService nên không phát sinh truy vấn CSDL mới.
	 *
	 * Cách dùng:
	 *     $config = $configService->forCampus((int) $examseason->campus_id);
	 *     $section->addText($config->getOrganization());
	 *
	 * QUAN TRỌNG: campusId phải lấy từ chính đối tượng dữ liệu đang xử lý
	 * (examseason, assessment, class, group...), KHÔNG lấy từ cơ sở đang làm
	 * việc của người dùng — nếu không, cán bộ cấp Học viện sẽ xuất ra biểu mẫu
	 * mang thông tin sai cơ sở.
	 *
	 * @param   int  $campusId  Id cơ sở đào tạo (> 0)
	 *
	 * @return  self  Bản sao đã gắn cơ sở đào tạo
	 * @since   2.1.6
	 */
	public function forCampus(int $campusId): self
	{
		$clone = clone $this;
		$clone->campusId = $campusId;

		return $clone;
	}

	/**
	 * Id cơ sở đào tạo mà instance này đang gắn với (0 nếu chưa gắn).
	 *
	 * @return  int
	 * @since   2.1.6
	 */
	public function getCampusId(): int
	{
		return $this->campusId;
	}

	/**
	 * Trả về CampusService, resolve lười từ component nếu chưa được inject.
	 *
	 * @return  CampusService
	 * @since   2.1.6
	 */
	private function getCampusService(): CampusService
	{
		if ($this->campusService === null) {
			$this->campusService = KmaComponentHelper::getComponent()->getCampusService();
		}

		return $this->campusService;
	}

	/**
	 * Đọc một tham số cấu hình riêng của cơ sở đào tạo mà instance đang gắn với.
	 *
	 * Trong cột `params` của #__eqa_campuses, các key được lưu ở dạng phẳng
	 * (không có tiền tố 'params.' như trong cấu hình của component).
	 *
	 * @param   string  $key      Tên tham số
	 * @param   string  $default  Giá trị mặc định khi cơ sở chưa cấu hình
	 *
	 * @return  string
	 * @throws  RuntimeException  Nếu instance chưa được gắn cơ sở đào tạo
	 * @since   2.1.6
	 */
	private function getCampusParam(string $key, string $default): string
	{
		if ($this->campusId <= 0) {
			throw new RuntimeException(sprintf(
				'ConfigService: tham số "%s" là tham số riêng theo cơ sở đào tạo.'
				. ' Hãy gọi $configService->forCampus($campusId) trước khi đọc,'
				. ' với campusId lấy từ chính đối tượng dữ liệu đang xử lý'
				. ' (examseason, assessment, class, group...),'
				. ' không lấy từ cơ sở đang làm việc của người dùng.',
				$key
			));
		}

		$value = (string) $this->getCampusService()
			->getCampusParams($this->campusId)
			->get($key, '');

		return $value !== '' ? $value : $default;
	}


	/**
	 * Trả về tên tổ chức cấp trên (cơ quan chủ quản) của cơ sở đào tạo.
	 *
	 * @return  string
	 * @throws  RuntimeException  Nếu chưa gọi forCampus()
	 * @since   2.1.6  Chuyển sang tham số riêng theo cơ sở đào tạo
	 */
	public function getParentOrganization(): string
	{
		return $this->getCampusParam('parent_organization', 'Ban Cơ yếu Chính phủ');
	}

	/**
	 * Trả về tên tổ chức (nhà trường) của cơ sở đào tạo.
	 *
	 * @return  string
	 * @throws  RuntimeException  Nếu chưa gọi forCampus()
	 * @since   2.1.6  Chuyển sang tham số riêng theo cơ sở đào tạo
	 */
	public function getOrganization(): string
	{
		return $this->getCampusParam('organization', 'Học viện Kỹ thuật mật mã');
	}

	/**
	 * Trả về tên đơn vị phụ trách tổ chức thi của cơ sở đào tạo.
	 *
	 * @return  string
	 * @throws  RuntimeException  Nếu chưa gọi forCampus()
	 * @since   2.1.6  Chuyển sang tham số riêng theo cơ sở đào tạo
	 */
	public function getExaminationUnit(): string
	{
		return $this->getCampusParam('examination_unit', 'Phòng KT&ĐBCLĐT');
	}

	/**
	 * Trả về địa danh ghi trên văn bản của cơ sở đào tạo.
	 *
	 * @return  string
	 * @throws  RuntimeException  Nếu chưa gọi forCampus()
	 * @since   2.1.6  Chuyển sang tham số riêng theo cơ sở đào tạo
	 */
	public function getCity(): string
	{
		return $this->getCampusParam('city', 'Hà Nội');
	}
	/**
	 * Trả về số năm cộng thêm vào năm hiện tại để xác định năm học cao nhất
	 * trong danh sách chọn của AcademicyearField.
	 *
	 * Ví dụ: offset = 1, năm hiện tại = 2026 → năm học cao nhất = 2027-2028.
	 *
	 * @return  int
	 * @since   2.0.4
	 */
	public function getAcademicYearUpperOffset(): int
	{
		return (int) $this->params->get('params.academicyear_upper_offset', 0);
	}

	/**
	 * Trả về số năm trừ đi từ năm hiện tại để xác định năm học thấp nhất
	 * trong danh sách chọn của AcademicyearField.
	 *
	 * Ví dụ: offset = 5, năm hiện tại = 2026 → năm học thấp nhất = 2020-2021.
	 *
	 * @return  int
	 * @since   2.0.4
	 */
	public function getAcademicYearLowerOffset(): int
	{
		return (int) $this->params->get('params.academicyear_lower_offset', 5);
	}

	/**
	 * Trả về số lần dự thi tối đa cho phép đối với một môn thi.
	 *
	 * @return  int
	 * @since   2.0.5
	 */
	public function getMaxExamAttempts(): int
	{
		return (int) $this->params->get('params.max_exam_attempts', 2);
	}

	/**
	 * Trả về số chữ số thập phân dùng khi làm tròn điểm quá trình (PAM).
	 *
	 * @return  int
	 * @since   2.0.5
	 */
	public function getProgressMarkPrecision(): int
	{
		return (int) $this->params->get('params.precision_progress', 1);
	}

	/**
	 * Trả về số chữ số thập phân dùng khi làm tròn điểm thi (exam mark).
	 *
	 * @return  int
	 * @since   2.0.5
	 */
	public function getExamMarkPrecision(): int
	{
		return (int) $this->params->get('params.precision_exam', 1);
	}

	/**
	 * Trả về số chữ số thập phân dùng khi làm tròn điểm học phần (module mark).
	 *
	 * @return  int
	 * @since   2.0.5
	 */
	public function getModuleMarkPrecision(): int
	{
		return (int) $this->params->get('params.precision_module', 1);
	}

	/**
	 * Chế độ giới hạn điểm thi lần 2.
	 *
	 * @return  ResitMarkLimitMode
	 * @since   2.0.5
	 */
	public function getResitMarkLimitMode(): ResitMarkLimitMode
	{
		$value = (int) $this->params->get('params.resit_limit', ResitMarkLimitMode::OnExamMark->value);
		return ResitMarkLimitMode::from($value);
	}

	/**
	 * Trả về số bắt đầu của dãy số báo danh thí sinh.
	 *
	 * @return  int
	 * @since   2.0.5
	 */
	public function getExamineeCodeStart(): int
	{
		return (int) $this->params->get('params.examinee_code_start', 100);
	}

	/**
	 * Trả về số bắt đầu của dãy số phách thí sinh.
	 *
	 * @return  int
	 * @since   2.0.5
	 */
	public function getExamineeMaskStart(): int
	{
		return (int) $this->params->get('params.examinee_mask_start', 100);
	}

	/**
	 * Trả về khoảng cách giữa các số phách liên tiếp.
	 *
	 * @return  int
	 * @since   2.0.5
	 */
	public function getExamineeMaskInterval(): int
	{
		return (int) $this->params->get('params.examinee_mask_interval', 5);
	}

	/**
	 * Trả về ngưỡng điểm đạt của thành phần 1 (PAM1) để được dự thi.
	 *
	 * @return  float
	 * @since   2.0.5
	 */
	public function getThresholdForPam1(): float
	{
		return (float) $this->params->get('params.threshold_pam1', 4.0);
	}

	/**
	 * Trả về ngưỡng điểm đạt của thành phần 2 (PAM2) để được dự thi.
	 *
	 * @return  float
	 * @since   2.0.5
	 */
	public function getThresholdForPam2(): float
	{
		return (float) $this->params->get('params.threshold_pam2', 4.0);
	}

	/**
	 * Trả về ngưỡng điểm đạt của điểm quá trình (PAM) để được dự thi.
	 *
	 * @return  float
	 * @since   2.0.5
	 */
	public function getThresholdForPam(): float
	{
		return (float) $this->params->get('params.threshold_pam', 4.0);
	}

	/**
	 * Trả về ngưỡng điểm thi tối thiểu.
	 *
	 * @return  float
	 * @since   2.0.5
	 */
	public function getThresholdForFinalExamMark(): float
	{
		return (float) $this->params->get('params.threshold_final_exam_mark', 4.0);
	}

	/**
	 * Trả về ngưỡng điểm thi tối thiểu.
	 *
	 * @return  float
	 * @since   2.0.5
	 */
	public function getThresholdForModuleMark(): float
	{
		return (float) $this->params->get('params.threshold_module_mark', 4.0);
	}

	/**
	 * Trả về ngưỡng điểm đạt áp dụng cho môn học dạng điều kiện (pass/fail).
	 *
	 * @return  float
	 * @since   2.0.5
	 */
	public function getThresholdForPassFailSubject(): float
	{
		return (float) $this->params->get('params.threshold_pass_fail', 4.0);
	}

	// ----------------------------------------------
	// GIÁ TRỊ NGƯỠNG CHO CÁC KỲ THI KHÁC
	// ----------------------------------------------
	/**
	 * Trả về ngưỡng điểm đạt thành phần 1 (PAM1) trong thi tốt nghiệp.
	 *
	 * @return  float
	 * @since   2.0.5
	 */
	public function getThresholdForGraduationPam1(): float
	{
		return (float) $this->params->get('params.threshold_graduation_pam1', 0.0);
	}

	/**
	 * Trả về ngưỡng điểm đạt thành phần 2 (PAM2) trong thi tốt nghiệp.
	 *
	 * @return  float
	 * @since   2.0.5
	 */
	public function getThresholdForGraduationPam2(): float
	{
		return (float) $this->params->get('params.threshold_graduation_pam2', 0.0);
	}

	/**
	 * Trả về ngưỡng điểm đạt quá trình (PAM) trong thi tốt nghiệp.
	 *
	 * @return  float
	 * @since   2.0.5
	 */
	public function getThresholdForGraduationPam(): float
	{
		return (float) $this->params->get('params.threshold_graduation_pam', 0.0);
	}

	/**
	 * Trả về ngưỡng điểm đạt bài thi (final exam mark) trong thi tốt nghiệp.
	 *
	 * @return  float
	 * @since   2.0.5
	 */
	public function getThresholdForGraduationFinalExamMark(): float
	{
		return (float) $this->params->get('params.threshold_graduation_final_exam_mark', 5.0);
	}

	/**
	 * Trả về ngưỡng điểm đạt môn thi tốt nghiệp (module mark).
	 *
	 * @return  float
	 * @since   2.0.5
	 */
	public function getThresholdForGraduationModuleMark(): float
	{
		return (float) $this->params->get('params.threshold_graduation_module_mark', 5.0);
	}

	/**
	 * Trả về ngưỡng điểm đạt đầu ra TOEIC.
	 *
	 * @return  int
	 * @since   2.0.5
	 */
	public function getThresholdForToeic(): int
	{
		return (int) $this->params->get('params.threshold_toeic', 450);
	}

	/**
	 * Trả về hệ số nhân khi tính thù lao coi thi vào ngày cuối tuần.
	 *
	 * @return  float
	 * @since   2.0.5
	 */
	public function getKWeekendMonitoring(): float
	{
		return (float) $this->params->get('params.kweekend_monitoring', 1.5);
	}

	/**
	 * Trả về chế độ tính phí phúc khảo (regrading).
	 * Giá trị tương ứng với các hằng số REGRADING_FEE_MODE_* của ExamHelper.
	 *
	 * @return  int
	 * @since   2.0.5
	 */
	public function getRegradingFeeMode(): FeeMode
	{
		$value = (int) $this->params->get('params.regrading_fee_mode', FeeMode::PerExam->value);
		return FeeMode::from($value);
	}

	/**
	 * Trả về mức phí phúc khảo (đơn vị: VNĐ).
	 *
	 * @return  float
	 * @since   2.0.5
	 */
	public function getRegradingFeeRate(): float
	{
		return (float) $this->params->get('params.regrading_fee_rate', 30000);
	}

	public function getResitFeeMode(): FeeMode
	{
		$value = (int) $this->params->get('params.resit_fee_mode', FeeMode::PerExam->value);
		return FeeMode::from($value);
	}

	public function getResitFeeRate(): float
	{
		return (float) $this->params->get('params.resit_fee_rate', 90000);
	}

	/**
	 * Lấy tham số thứ tự sắp xếp khi xuất danh sách người học, cán bộ.
	 *
	 * @return string 'name' (sắp theo tên rồi họ đệm) hoặc 'code' (sắp theo mã)
	 */
	public function getPersonSortOrder(): string
	{
		return (float) $this->params->get('params.person_sort_order', 'name');
	}


	public function getMailBatchSize():int
	{
		return (int) $this->params->get('params.mail_batch_size', 50);
	}
	public function getMailMaxAttempts():int
	{
		return (int) $this->params->get('params.mail_max_attempts', 3);
	}

	public function getMailRetryIntervalMinutes(): int
	{
		return (int) $this->params->get('params.mail_retry_interval_minutes', 5);
	}

	// =====================================================================
	// Nhóm tham số: Đánh giá rèn luyện (riêng theo từng cơ sở đào tạo)
	// Yêu cầu gọi forCampus() trước khi sử dụng.
	// =====================================================================

	/**
	 * @return  string
	 * @throws  RuntimeException  Nếu chưa gọi forCampus()
	 * @since   2.1.6  Chuyển sang tham số riêng theo cơ sở đào tạo
	 */
	public function getConductTrainingUnit(): string
	{
		return $this->getCampusParam('conduct_training_unit', 'PHÒNG ĐÀO TẠO');
	}

	/**
	 * @return  string
	 * @throws  RuntimeException  Nếu chưa gọi forCampus()
	 * @since   2.1.6  Chuyển sang tham số riêng theo cơ sở đào tạo
	 */
	public function getConductTrainingUnitLeaderTitle(): string
	{
		return $this->getCampusParam('conduct_training_unit_leader_title', 'P. TRƯỞNG PHÒNG');
	}

	/**
	 * @return  string
	 * @throws  RuntimeException  Nếu chưa gọi forCampus()
	 * @since   2.1.6  Chuyển sang tham số riêng theo cơ sở đào tạo
	 */
	public function getConductTrainingUnitLeaderName(): string
	{
		return $this->getCampusParam('conduct_training_unit_leader_name', '');
	}

	/**
	 * @return  string
	 * @throws  RuntimeException  Nếu chưa gọi forCampus()
	 * @since   2.1.6  Chuyển sang tham số riêng theo cơ sở đào tạo
	 */
	public function getConductLearnerUnit(): string
	{
		return $this->getCampusParam('conduct_learner_unit', 'HỆ QUẢN LÝ HVSV');
	}

	/**
	 * @return  string
	 * @throws  RuntimeException  Nếu chưa gọi forCampus()
	 * @since   2.1.6  Chuyển sang tham số riêng theo cơ sở đào tạo
	 */
	public function getConductLearnerUnitLeaderTitle(): string
	{
		return $this->getCampusParam('conduct_learner_unit_leader_title', 'P. HỆ TRƯỞNG');
	}

	/**
	 * @return  string
	 * @throws  RuntimeException  Nếu chưa gọi forCampus()
	 * @since   2.1.6  Chuyển sang tham số riêng theo cơ sở đào tạo
	 */
	public function getConductLearnerUnitLeaderName(): string
	{
		return $this->getCampusParam('conduct_learner_unit_leader_name', '');
	}

	/**
	 * @return  string
	 * @throws  RuntimeException  Nếu chưa gọi forCampus()
	 * @since   2.1.6  Chuyển sang tham số riêng theo cơ sở đào tạo
	 */
	public function getConductPreparerTitle(): string
	{
		return $this->getCampusParam('conduct_preparer_title', 'TRỢ LÝ QUẢN LÝ HVSV');
	}

	/**
	 * @return  string
	 * @throws  RuntimeException  Nếu chưa gọi forCampus()
	 * @since   2.1.6  Chuyển sang tham số riêng theo cơ sở đào tạo
	 */
	public function getConductPreparerName(): string
	{
		return $this->getCampusParam('conduct_preparer_name', '');
	}
}
