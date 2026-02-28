<?php

/**
 * @package     Kma.Component.Eqa
 * @subpackage  Administrator.Service
 *
 * @copyright   (C) 2025 KMA. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Kma\Component\Eqa\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

/**
 * Service đọc các tham số cấu hình của component com_eqa.
 *
 * Cách dùng:
 *   $config = new ConfigService();
 *   $org    = $config->getOrganization();
 *
 * @since 2.1.0
 */
class ConfigService
{
	/**
	 * Tham số cấu hình của component com_eqa.
	 *
	 * @var    Registry
	 * @since  2.1.0
	 */
	private Registry $params;

	/**
	 * Constructor — tự động nạp tham số cấu hình của com_eqa.
	 *
	 * @since  2.1.0
	 */
	public function __construct()
	{
		$this->params = ComponentHelper::getParams('com_eqa');
	}

	/**
	 * Trả về tên tổ chức cấp trên (cơ quan chủ quản).
	 *
	 * @return  string
	 * @since   2.1.0
	 */
	public function getParentOrganization(): string
	{
		return $this->params->get('params.parent_organization', 'Ban Cơ yếu Chính phủ');
	}

	/**
	 * Trả về tên tổ chức (nhà trường).
	 *
	 * @return  string
	 * @since   2.1.0
	 */
	public function getOrganization(): string
	{
		return $this->params->get('params.organization', 'Học viện Kỹ thuật mật mã');
	}

	/**
	 * Trả về tên đơn vị phụ trách tổ chức thi.
	 *
	 * @return  string
	 * @since   2.1.0
	 */
	public function getExaminationUnit(): string
	{
		return $this->params->get('params.examination_unit', 'Phòng KT&ĐBCLĐT');
	}

	/**
	 * Trả về tên thành phố/địa điểm.
	 *
	 * @return  string
	 * @since   2.1.0
	 */
	public function getCity(): string
	{
		return $this->params->get('params.city', 'Hà Nội');
	}

	/**
	 * Trả về số lần dự thi tối đa cho phép đối với một môn thi.
	 *
	 * @return  int
	 * @since   2.1.0
	 */
	public function getMaxExamAttempts(): int
	{
		return (int) $this->params->get('params.max_exam_attempts', 2);
	}

	/**
	 * Trả về số chữ số thập phân dùng khi làm tròn điểm quá trình (PAM).
	 *
	 * @return  int
	 * @since   2.1.0
	 */
	public function getProgressMarkPrecision(): int
	{
		return (int) $this->params->get('params.precision_progress', 1);
	}

	/**
	 * Trả về số chữ số thập phân dùng khi làm tròn điểm thi (exam mark).
	 *
	 * @return  int
	 * @since   2.1.0
	 */
	public function getExamMarkPrecision(): int
	{
		return (int) $this->params->get('params.precision_exam', 1);
	}

	/**
	 * Trả về số chữ số thập phân dùng khi làm tròn điểm học phần (module mark).
	 *
	 * @return  int
	 * @since   2.1.0
	 */
	public function getModuleMarkPrecision(): int
	{
		return (int) $this->params->get('params.precision_module', 1);
	}

	/**
	 * Trả về kiểu giới hạn điểm thi lần 2.
	 * Giá trị tương ứng với các hằng số SECOND_ATTEMPT_LIMIT_* của ExamHelper.
	 *
	 * @return  int
	 * @since   2.1.0
	 */
	public function getSecondAttemptLimit(): int
	{
		return (int) $this->params->get('params.second_attempt_limit', ExamHelper::SECOND_ATTEMPT_LIMIT_EXAM);
	}

	/**
	 * Trả về số bắt đầu của dãy số báo danh thí sinh.
	 *
	 * @return  int
	 * @since   2.1.0
	 */
	public function getExamineeCodeStart(): int
	{
		return (int) $this->params->get('params.examinee_code_start', 100);
	}

	/**
	 * Trả về số bắt đầu của dãy số phách thí sinh.
	 *
	 * @return  int
	 * @since   2.1.0
	 */
	public function getExamineeMaskStart(): int
	{
		return (int) $this->params->get('params.examinee_mask_start', 100);
	}

	/**
	 * Trả về khoảng cách giữa các số phách liên tiếp.
	 *
	 * @return  int
	 * @since   2.1.0
	 */
	public function getExamineeMaskInterval(): int
	{
		return (int) $this->params->get('params.examinee_mask_interval', 5);
	}

	/**
	 * Trả về ngưỡng điểm đạt của thành phần 1 (PAM1) để được dự thi.
	 *
	 * @return  float
	 * @since   2.1.0
	 */
	public function getThresholdForPam1(): float
	{
		return (float) $this->params->get('params.threshold_pam1', 4.0);
	}

	/**
	 * Trả về ngưỡng điểm đạt của thành phần 2 (PAM2) để được dự thi.
	 *
	 * @return  float
	 * @since   2.1.0
	 */
	public function getThresholdForPam2(): float
	{
		return (float) $this->params->get('params.threshold_pam2', 4.0);
	}

	/**
	 * Trả về ngưỡng điểm đạt của điểm quá trình (PAM) để được dự thi.
	 *
	 * @return  float
	 * @since   2.1.0
	 */
	public function getThresholdForPam(): float
	{
		return (float) $this->params->get('params.threshold_pam', 4.0);
	}

	/**
	 * Trả về ngưỡng điểm thi tối thiểu để được cộng điểm khuyến khích.
	 *
	 * @return  float
	 * @since   2.1.0
	 */
	public function getThresholdForFinalExamMark(): float
	{
		return (float) $this->params->get('params.threshold_final_exam_mark', 4.0);
	}

	/**
	 * Trả về ngưỡng điểm đạt áp dụng cho môn học dạng điều kiện (pass/fail).
	 *
	 * @return  float
	 * @since   2.1.0
	 */
	public function getThresholdForPassFailSubject(): float
	{
		return (float) $this->params->get('params.threshold_pass_fail', 4.0);
	}

	/**
	 * Trả về hệ số nhân khi tính thù lao coi thi vào ngày cuối tuần.
	 *
	 * @return  float
	 * @since   2.1.0
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
	 * @since   2.1.0
	 */
	public function getRegradingFeeMode(): int
	{
		return (int) $this->params->get('params.regrading_fee_mode', ExamHelper::REGRADING_FEE_MODE_BY_WORK);
	}

	/**
	 * Trả về mức phí phúc khảo (đơn vị: VNĐ).
	 *
	 * @return  float
	 * @since   2.1.0
	 */
	public function getRegradingFeeRate(): float
	{
		return (float) $this->params->get('params.regrading_fee_rate', 30000);
	}
}
