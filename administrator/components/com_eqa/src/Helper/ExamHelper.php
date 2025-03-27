<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Model\ExamModel;

abstract class ExamHelper{
    public const TEST_TYPE_UNKNOWN=0;
    public const TEST_TYPE_PAPER = 10;
    public const TEST_TYPE_PROJECT = 11;
    public const TEST_TYPE_THESIS = 12;
    public const TEST_TYPE_PRACTICE=13;
    public const TEST_TYPE_DIALOG = 14;
    public const TEST_TYPE_MACHINE_OBJECTIVE = 20;
    public const TEST_TYPE_MACHINE_HYBRID = 21;
    public const TEST_TYPE_COMBO_OBJECTIVE_PRACTICE = 30;

    public const EXAM_TYPE_OTHER = 0;                   //Thi khác
    public const EXAM_TYPE_SUBJECT_FINAL_TEST = 1;      //Thi kết thúc học phần
    public const EXAM_TYPE_CERTIFICATION = 2;           //Thi sát hạch (đầu vào, đầu ra,...)
    public const EXAM_TYPE_GRADUATION = 3;              //Thi tốt nghiệp

    public const EXAM_STATUS_UNKNOWN = 0;               //Chưa xác định
    public const EXAM_STATUS_QUESTION_BUT_PAM = 10;     //Đã có đề thi, Chưa có điểm quá trình
    public const EXAM_STATUS_PAM_BUT_QUESTION = 11;     //Đã có điểm quá trình, Chưa có đề thi
    public const EXAM_STATUS_QUESTION_AND_PAM = 12;     //Đã có đề thi và điểm quá trình
    public const EXAM_STATUS_READY_TO_EXAM = 20;        //Đã chia phòng thi
	public const EXAM_STATUS_EXAM_CONDUCTED = 21;       //Đã tổ chức thi
	public const EXAM_STATUS_PAPER_INFO_PARTIAL = 22;          //Đã bắt đầu nhập biên bản thi viết
	public const EXAM_STATUS_PAPER_INFO_FULL = 23;             //Đã hoàn thành nhập biên bản thi viết
	public const EXAM_STATUS_MASKING_DONE = 25;                //Đã làm phách, dồn túi
	public const EXAM_STATUS_EXAMINER_ASSIGNED = 26;           //Đã phân công chấm thi viết
	public const EXAM_STATUS_ANOMALY_INPUTTED = 30;           //Đã nhập thông tin bất thường phòng thi
    public const EXAM_STATUS_MARKING_STARTED = 50;      //Đã bắt đầu chấm thi
	public const EXAM_STATUS_MARK_PARTIAL = 51;         //Đã có một phần điểm
	public const EXAM_STATUS_MARK_FULL = 52;            //Đã có đủ điểm
    public const EXAM_STATUS_COMPLETED = 100;           //Đã hoàn tất

    public const EXAM_ANOMALY_NONE=0;           //Không có
    public const EXAM_ANOMALY_SUB25=11;        //Trừ 25%
    public const EXAM_ANOMALY_SUB50=12;        //Trừ 50%
    public const EXAM_ANOMALY_BAN=13;           //Đình chỉ thi
    public const EXAM_ANOMALY_ABSENT=20;        //Vắng thi (không lý do)
    public const EXAM_ANOMALY_DELAY=30;         //Hoãn thi (vắng có lý do)
    public const EXAM_ANOMALY_REDO=40;          //Hủy bài thi và làm lại bài thi vào kỳ thi sau

    public const EXAM_PPAA_NONE=0;
    public const EXAM_PPAA_REVIEW=10;
    public const EXAM_PPAA_CORRECTION=20;
	public const EXAM_PPAA_STATUS_INIT=0;
	public const EXAM_PPAA_STATUS_ACCEPTED=20;
	public const EXAM_PPAA_STATUS_REJECTED=30;
	public const EXAM_PPAA_STATUS_DONE=40;

	public const CONCLUSION_PASSED = 10;            //Qua môn, hết lượt thi
	public const CONCLUSION_FAILED = 20;            //Không qua môn, thi lại
	public const CONCLUSION_FAILED_EXPIRED = 21;    //Không qua môn, hết lượt thi
	public const CONCLUSION_RESERVED = 30;          //Bảo lưu lượt thi

	public const SECOND_ATTEMPT_LIMIT_NONE = 0;
	public const SECOND_ATTEMPT_LIMIT_EXAM = 1;
	public const SECOND_ATTEMPT_LIMIT_MODULE=2;

	public const SPECIAL_MARK_N25=-25;
	public const SPECIAL_MARK_N100=-100;
	public const SPECIAL_MARK_TKD=-10;

	public const MARK_CONSTITUENT_PAM1=10;
	public const MARK_CONSTITUENT_PAM2=20;
	public const MARK_CONSTITUENT_FINAL_EXAM=100;

	public const REGRADING_FEE_MODE_BY_WORK=10;
	public const REGRADING_FEE_MODE_BY_CREDIT=20;

	static public function decodeRegradingFeeMode(int $code): string|null
	{
		return match ($code){
			self::REGRADING_FEE_MODE_BY_WORK => 'Tính theo bài thi',
			self::REGRADING_FEE_MODE_BY_CREDIT => 'Tính theo số tín chỉ',
			default => null
		};
	}
	static public function getRegradingFeeModes(): array
	{
		return [
			self::REGRADING_FEE_MODE_BY_WORK => self::decodeRegradingFeeMode(self::REGRADING_FEE_MODE_BY_WORK),
			self::REGRADING_FEE_MODE_BY_CREDIT => self::decodeRegradingFeeMode(self::REGRADING_FEE_MODE_BY_CREDIT)
		];
	}

	static public function decodePpaaStatus(int $code):string|null
	{
		return match ($code){
			self::EXAM_PPAA_STATUS_INIT => 'Đã gửi yêu cầu',
			self::EXAM_PPAA_STATUS_REJECTED => 'Yêu cầu bị từ chối',
			self::EXAM_PPAA_STATUS_ACCEPTED => 'Yêu cầu đã được chấp nhận',
			self::EXAM_PPAA_STATUS_DONE => 'Đã xử lý xong',
			default => null
		};
	}

	static public function markToText($mark):string
	{
		if(is_null($mark))
			return '';
		if($mark == ExamHelper::SPECIAL_MARK_N25)
			return 'N25';
		if($mark == ExamHelper::SPECIAL_MARK_N100)
			return 'N100';
		if($mark == ExamHelper::SPECIAL_MARK_TKD)
			return 'TKD';
		return (string)$mark;
	}


    /**
     * Hàm này dịch từ MÃ HÌNH THỨC THI thành HÌNH THỨC THI
     * @param int $testTypeCode   Hằng số quy ước cho mã hình thức thi
     * @return string|null  Tên loại kỳ thi (dịch từ tập tin language) tương ứng với hằng số
     * @since 1.0
     */
    static public function getTestType(int $testTypeCode): string|null
    {
        return match ($testTypeCode) {
            self::TEST_TYPE_UNKNOWN => "Chưa xác định",
            self::TEST_TYPE_PAPER => "Tự luận",
            self::TEST_TYPE_PROJECT => "Đồ án",
            self::TEST_TYPE_THESIS => "Tiểu luận",
            self::TEST_TYPE_PRACTICE => "Thực hành",
            self::TEST_TYPE_DIALOG => "Vấn đáp",
            self::TEST_TYPE_MACHINE_OBJECTIVE => "Trắc nghiệm (máy)",
            self::TEST_TYPE_MACHINE_HYBRID => "Hỗn hợp (máy)",
            self::TEST_TYPE_COMBO_OBJECTIVE_PRACTICE => "Trắc nghiệm + Thực hành",
            default => null,
        };
    }

    /**
     * Hàm này dịch từ MÃ HÌNH THỨC THI thành TÊN VIẾT TẮT CỦA HÌNH THỨC THI
     * @param int $testTypeCode   Hằng số quy ước cho mã hình thức thi
     * @return string|null  Tên loại kỳ thi (dịch từ tập tin language) tương ứng với hằng số
     * @since 1.0
     */
    static public function getTestTypeAbbr(int $testTypeCode): string|null
    {
        return match ($testTypeCode) {
            self::TEST_TYPE_UNKNOWN => "NA",
            self::TEST_TYPE_PAPER => "TL",
            self::TEST_TYPE_PROJECT => "ĐA",
            self::TEST_TYPE_THESIS => "TiL",
            self::TEST_TYPE_PRACTICE => "TH",
            self::TEST_TYPE_DIALOG => "VĐ",
            self::TEST_TYPE_MACHINE_OBJECTIVE => "TN",
            self::TEST_TYPE_MACHINE_HYBRID => "TN+",
            self::TEST_TYPE_COMBO_OBJECTIVE_PRACTICE => "TN+TH",
            default => null,
        };
    }

    /**
     * Hàm này trả về mảng HÌNH THỨC THI trong đó $key là mã hình thức thi,
     * còn $value là tên hình thức thi được dịch từ tập tin ngôn ngữ.
     * @return array    Mỗi phần tử $key=>$value ứng với $key là mã hình thức thi, $value là tên hình thức thi
     * @since 1.0
     */
    static public function getTestTypes(): array
    {
        $testtypes = array();
        $testtypes[self::TEST_TYPE_UNKNOWN] = self::getTestType(self::TEST_TYPE_UNKNOWN);
        $testtypes[self::TEST_TYPE_PAPER] = self::getTestType(self::TEST_TYPE_PAPER);
        $testtypes[self::TEST_TYPE_PROJECT] = self::getTestType(self::TEST_TYPE_PROJECT);
        $testtypes[self::TEST_TYPE_THESIS] = self::getTestType(self::TEST_TYPE_THESIS);
        $testtypes[self::TEST_TYPE_PRACTICE] = self::getTestType(self::TEST_TYPE_PRACTICE);
        $testtypes[self::TEST_TYPE_DIALOG] = self::getTestType(self::TEST_TYPE_DIALOG);
        $testtypes[self::TEST_TYPE_MACHINE_OBJECTIVE] = self::getTestType(self::TEST_TYPE_MACHINE_OBJECTIVE);
        $testtypes[self::TEST_TYPE_MACHINE_HYBRID] = self::getTestType(self::TEST_TYPE_MACHINE_HYBRID);
        $testtypes[self::TEST_TYPE_COMBO_OBJECTIVE_PRACTICE] = self::getTestType(self::TEST_TYPE_COMBO_OBJECTIVE_PRACTICE);
        return $testtypes;
    }

    /**
     * Hàm này dịch từ MÃ LOẠI KỲ THI thành LOẠI KỲ THI
     * @param int $typeCode   Hằng số quy ước cho mã loại kỳ thi
     * @return string|null  Tên loại kỳ thi (dịch từ tập tin language) tương ứng với hằng số
     * @since 1.0
     */
    static public function ExamType(int $typeCode): string|null
    {
        return match ($typeCode) {
            self::EXAM_TYPE_OTHER => "Khác",
            self::EXAM_TYPE_SUBJECT_FINAL_TEST => "KTHP",
            self::EXAM_TYPE_CERTIFICATION => "Sát hạch",
            self::EXAM_TYPE_GRADUATION => "Tốt nghiệp",
            default => null,
        };
    }

    /**
     * Hàm này trả về mảng thông tin loại kỳ thi trong đó $key là mã loại kỳ thi,
     * còn $value là tên loại kỳ thi được dịch từ tập tin ngôn ngữ.
     * @return array    Mỗi phần tử $key=>$value ứng với $key là mã loại kỳ thi, $value là tên loại kỳ thi
     * @since 1.0
     */
    static public function ExamTypes(): array
    {
        $types = array();
        $types[self::EXAM_TYPE_SUBJECT_FINAL_TEST] = self::ExamType(self::EXAM_TYPE_SUBJECT_FINAL_TEST);
        $types[self::EXAM_TYPE_CERTIFICATION] = self::ExamType(self::EXAM_TYPE_CERTIFICATION);
        $types[self::EXAM_TYPE_GRADUATION] = self::ExamType(self::EXAM_TYPE_GRADUATION);
        $types[self::EXAM_TYPE_OTHER] = self::ExamType(self::EXAM_TYPE_OTHER);
        return $types;
    }

    /**
     * Hàm này dịch từ MÃ TRẠNG THÁI MÔN THI thành TRẠNG THÁI MÔN THI
     * @param int $statusCode   Hằng số quy ước cho mã trạng thái
     * @return string|null  Tên trạng thái tương ứng với hằng số
     * @since 1.0
     */
    static public function ExamStatus(int $statusCode): string|null
    {
        return match ($statusCode) {
            self::EXAM_STATUS_UNKNOWN => 'Chưa biết',
            self::EXAM_STATUS_QUESTION_BUT_PAM => 'Đã có đề thi, chưa có điểm quá trình',
            self::EXAM_STATUS_PAM_BUT_QUESTION => 'Đã có điểm quá trình, chưa có đề thi',
            self::EXAM_STATUS_QUESTION_AND_PAM => 'Đã có đề thi và điểm quá trình',
            self::EXAM_STATUS_READY_TO_EXAM => 'Đã sẵn sàng để thi',
	        self::EXAM_STATUS_EXAM_CONDUCTED => 'Đã thi xong',
	        self::EXAM_STATUS_PAPER_INFO_PARTIAL => 'Đã bắt đầu nhập thông tin bài thi viết',
	        self::EXAM_STATUS_PAPER_INFO_FULL => 'Đã nhập xong thông tin bài thi viết',
	        self::EXAM_STATUS_MASKING_DONE => 'Đã đánh phách, dồn túi',
	        self::EXAM_STATUS_EXAMINER_ASSIGNED => 'Đã phân công chấm thi viết',
	        self::EXAM_STATUS_ANOMALY_INPUTTED => 'Đã nhập thông tin bất thường phòng thi',
            self::EXAM_STATUS_MARKING_STARTED => 'Đã giao bài thi cho CBChT',
	        self::EXAM_STATUS_MARK_PARTIAL => 'Đã có một phần điểm thi',
	        self::EXAM_STATUS_MARK_FULL => 'Đã có đủ điểm thi',
            self::EXAM_STATUS_COMPLETED => 'Đã hoàn tất',
            default => null,
        };
    }

    /**
     * Hàm này trả về mảng TRẠNG THÁI MÔN THI trong đó $key là mã trạng thái,
     * còn $value là tên trạng thái được dịch từ tập tin ngôn ngữ.
     * @return array    Mỗi phần tử $key=>$value ứng với $key là mã trạng thái, $value là tên trạng thái
     * @since 1.0
     */
    static public function ExamStatuses(): array
    {
        $statuses = array();
        $statuses[self::EXAM_STATUS_UNKNOWN]            = self::ExamStatus(self::EXAM_STATUS_UNKNOWN);
        $statuses[self::EXAM_STATUS_QUESTION_BUT_PAM]   = self::ExamStatus(self::EXAM_STATUS_QUESTION_BUT_PAM);
        $statuses[self::EXAM_STATUS_PAM_BUT_QUESTION]   = self::ExamStatus(self::EXAM_STATUS_PAM_BUT_QUESTION);
        $statuses[self::EXAM_STATUS_QUESTION_AND_PAM]   = self::ExamStatus(self::EXAM_STATUS_QUESTION_AND_PAM);
        $statuses[self::EXAM_STATUS_READY_TO_EXAM]      = self::ExamStatus(self::EXAM_STATUS_READY_TO_EXAM);
	    $statuses[self::EXAM_STATUS_EXAM_CONDUCTED]     = self::ExamStatus(self::EXAM_STATUS_EXAM_CONDUCTED);
	    $statuses[self::EXAM_STATUS_PAPER_INFO_PARTIAL] = self::ExamStatus(self::EXAM_STATUS_PAPER_INFO_PARTIAL);
	    $statuses[self::EXAM_STATUS_PAPER_INFO_FULL]    = self::ExamStatus(self::EXAM_STATUS_PAPER_INFO_FULL);
	    $statuses[self::EXAM_STATUS_MASKING_DONE]       = self::ExamStatus(self::EXAM_STATUS_MASKING_DONE);
	    $statuses[self::EXAM_STATUS_EXAMINER_ASSIGNED]  = self::ExamStatus(self::EXAM_STATUS_EXAMINER_ASSIGNED);
	    $statuses[self::EXAM_STATUS_ANOMALY_INPUTTED]   = self::ExamStatus(self::EXAM_STATUS_ANOMALY_INPUTTED);
        $statuses[self::EXAM_STATUS_MARKING_STARTED]    = self::ExamStatus(self::EXAM_STATUS_MARKING_STARTED);
	    $statuses[self::EXAM_STATUS_MARK_PARTIAL]       = self::ExamStatus(self::EXAM_STATUS_MARK_PARTIAL);
	    $statuses[self::EXAM_STATUS_MARK_FULL]          = self::ExamStatus(self::EXAM_STATUS_MARK_FULL);
        $statuses[self::EXAM_STATUS_COMPLETED]          = self::ExamStatus(self::EXAM_STATUS_COMPLETED);
        return $statuses;
    }

    static public function getAnomaly(int $anomalyCode){
        return match ($anomalyCode)
        {
            self::EXAM_ANOMALY_NONE => "Không",
            self::EXAM_ANOMALY_SUB25 => "Kỷ luật, trừ 25%",
            self::EXAM_ANOMALY_SUB50 => "Kỷ luật, trừ 50%",
            self::EXAM_ANOMALY_BAN => "Đình chỉ thi",
            self::EXAM_ANOMALY_ABSENT => "Vắng thi (không lý do)",
            self::EXAM_ANOMALY_DELAY => "Hoãn thi (có lý do)",
            self::EXAM_ANOMALY_REDO => "Dừng thi, bảo lưu lượt thi"
        };
    }
    static public function getAnomalies(){
        $anomalies = array();
        $anomalies[self::EXAM_ANOMALY_NONE] = self::getAnomaly(self::EXAM_ANOMALY_NONE);
        $anomalies[self::EXAM_ANOMALY_SUB25] = self::getAnomaly(self::EXAM_ANOMALY_SUB25);
        $anomalies[self::EXAM_ANOMALY_SUB50] = self::getAnomaly(self::EXAM_ANOMALY_SUB50);
        $anomalies[self::EXAM_ANOMALY_BAN] = self::getAnomaly(self::EXAM_ANOMALY_BAN);
        $anomalies[self::EXAM_ANOMALY_ABSENT] = self::getAnomaly(self::EXAM_ANOMALY_ABSENT);
        $anomalies[self::EXAM_ANOMALY_DELAY] = self::getAnomaly(self::EXAM_ANOMALY_DELAY);
        $anomalies[self::EXAM_ANOMALY_REDO] = self::getAnomaly(self::EXAM_ANOMALY_REDO);
        return $anomalies;
    }

	static public function getPostPrimaryAssessmentAction(int $pppaCode){
        return match ($pppaCode)
        {
            self::EXAM_PPAA_NONE => "Không",
            self::EXAM_PPAA_REVIEW => "Phúc khảo",
            self::EXAM_PPAA_CORRECTION => "Sửa sai điểm",
            default => false
        };
    }

    static public function getPostPrimaryAssessmentActions(){
        $ppaa = array();
        $ppaa[self::EXAM_PPAA_NONE] = self::getPostPrimaryAssessmentAction(self::EXAM_PPAA_NONE);
        $ppaa[self::EXAM_PPAA_REVIEW] = self::getPostPrimaryAssessmentAction(self::EXAM_PPAA_REVIEW);
        $ppaa[self::EXAM_PPAA_CORRECTION] = self::getPostPrimaryAssessmentAction(self::EXAM_PPAA_CORRECTION);
        return $ppaa;
    }

	static public function getSecondAttemptLimit($code){
		return match ($code)
		{
			self::SECOND_ATTEMPT_LIMIT_NONE => Text::_('Không giới hạn'),
			self::SECOND_ATTEMPT_LIMIT_EXAM => Text::_('Giới hạn điểm thi KTHP bằng 6.9'),
			self::SECOND_ATTEMPT_LIMIT_MODULE => Text::_('Giới hạn điểm học phần bằng 6.9'),
			default => false
		};
	}

	static public function getSecondAttemptLimits(){
		$limits = array();
		$limits[self::SECOND_ATTEMPT_LIMIT_NONE] = self::getSecondAttemptLimit(self::SECOND_ATTEMPT_LIMIT_NONE);
		$limits[self::SECOND_ATTEMPT_LIMIT_EXAM] = self::getSecondAttemptLimit(self::SECOND_ATTEMPT_LIMIT_EXAM);
		$limits[self::SECOND_ATTEMPT_LIMIT_MODULE] = self::getSecondAttemptLimit(self::SECOND_ATTEMPT_LIMIT_MODULE);
		return $limits;
	}

	static public function getConclusion(int $conclusionCode)
	{
		return match ($conclusionCode)
		{
			self::CONCLUSION_PASSED => 'Đạt',
			self::CONCLUSION_FAILED => 'Không đạt',
			self::CONCLUSION_FAILED_EXPIRED => 'Học lại',
			self::CONCLUSION_RESERVED => 'Bảo lưu'
		};
	}
	static public function getConclusions()
	{
		$conclusions = array();
		$conclusions[self::CONCLUSION_PASSED] = self::getConclusion(self::CONCLUSION_PASSED);
		$conclusions[self::CONCLUSION_FAILED] = self::getConclusion(self::CONCLUSION_FAILED);
		$conclusions[self::CONCLUSION_FAILED_EXPIRED] = self::getConclusion(self::CONCLUSION_FAILED_EXPIRED);
		$conclusions[self::CONCLUSION_RESERVED] = self::getConclusion(self::CONCLUSION_RESERVED);
		return $conclusions;
	}

	static public function decodeMarkConstituent(int $constituent):string|null
	{
		return match ($constituent){
			self::MARK_CONSTITUENT_PAM1 => 'Điểm TP1',
			self::MARK_CONSTITUENT_PAM2 => 'Điểm TP2',
			self::MARK_CONSTITUENT_FINAL_EXAM => 'Điểm thi KTHP',
			default => null
		};
	}
	static public function getMarkConstituents(){
		return [
			self::MARK_CONSTITUENT_PAM1 => self::decodeMarkConstituent(self::MARK_CONSTITUENT_PAM1),
			self::MARK_CONSTITUENT_PAM2 => self::decodeMarkConstituent(self::MARK_CONSTITUENT_PAM2),
			self::MARK_CONSTITUENT_FINAL_EXAM => self::decodeMarkConstituent(self::MARK_CONSTITUENT_FINAL_EXAM)
		];
	}

	static public function getAnomalyFromDescription(string|null $description): int|false
	{
		if(empty($description))
			return self::EXAM_ANOMALY_NONE;

		return match ($description){
			'K25', 'Khiển trách' => self::EXAM_ANOMALY_SUB25,
			'K50', 'Cảnh cáo' => self::EXAM_ANOMALY_SUB50,
			'ĐC', 'DC', 'Đình chỉ', 'Đình chỉ thi' => self::EXAM_ANOMALY_BAN,
			'Vắng thi' => self::EXAM_ANOMALY_ABSENT,
			'Hoãn thi' => self::EXAM_ANOMALY_DELAY,
			'Dừng thi' => self::EXAM_ANOMALY_REDO,
			default => false
		};
	}
	static public function isAllowedToFinalExam(float $pam1, float $pam2, float $pam): bool
	{
		$thresholdPam1 = ConfigHelper::getThresholdForPam1();
		$thresholdPam2 = ConfigHelper::getThresholdForPam2();
		$thresholdPam = ConfigHelper::getThresholdForPam();
		if($pam1<$thresholdPam1 || $pam2<$thresholdPam2 || $pam<$thresholdPam)
			return false;
		return  true;
	}
	static public function toPam($value, string|null $description=null): float|bool
	{
		//Prepare
		if(!empty($description))
			$description = mb_strtolower(trim($description));
		if(is_string($value)){
			$value = str_replace(',','.',trim($value));
		}

		//If $value is NOT a numeric value
		if(!is_numeric($value)){
			if($value==='N25')
				return ExamHelper::SPECIAL_MARK_N25;
			if($value==='N100')
				return ExamHelper::SPECIAL_MARK_N100;
			if ($value==='TKD' || $value==='TKĐ')
				return ExamHelper::SPECIAL_MARK_TKD;
			return false;
		}

		//If $value is of some cases of "empty" (not exactly is_empty() function)
		if($value==0 || $value=='' || is_null($value))
		{
			switch ($description){
				case 'n25':
					return ExamHelper::SPECIAL_MARK_N25;
				case 'n100':
				case 'nghỉ học':
				case 'nghi hoc':
					return ExamHelper::SPECIAL_MARK_N100;
				case 'tkd':
				case 'tkđ':
				case 'trượt gk':
					return ExamHelper::SPECIAL_MARK_TKD;
				default:
					if($value==0)
						return 0;
					return false;
			}
		}

		//$value is a number
		$value = (float)$value;
		if($value<0 || $value>10)
			return false;
		return $value;
	}
    static public function calculatePamForDefaultFormular(float $pam1, float $pam2)
    {
        $pam = 0.7*$pam1 + 0.3*$pam2;
        return $pam;
    }
	static public function calculateFinalMark(float $originalMark, int $anomaly, int $attempt, float $addValue)
	{
		$precision = ConfigHelper::getExamMarkPrecision();

		//Xử lý kỷ luật, nếu có
		$finalMark = match ($anomaly)
		{
			self::EXAM_ANOMALY_NONE => $originalMark,
			self::EXAM_ANOMALY_SUB25 => round(0.75 * $originalMark, $precision),
			self::EXAM_ANOMALY_SUB50 => round(0.5 * $originalMark, $precision),
			default => 0,
		};

		//Cộng điểm khuyến khích, nếu có
		//(Chỉ cộng nếu điểm thi đạt ngưỡng)
		$threshold = ConfigHelper::getThresholdForFinalExamMark();
		if($finalMark >= $threshold)
			$finalMark = min([10, $finalMark+$addValue]);

		//Giới hạn điểm thi lần 2
		if($attempt>1 && ConfigHelper::getSecondAttemptLimit()==self::SECOND_ATTEMPT_LIMIT_EXAM)
			$finalMark = max([$finalMark, 6.9]);

		return $finalMark;
	}
	static public function calculateModuleMark(int $subjectId, float $pam, float $examMark, int $attempt)
	{
		$precision = ConfigHelper::getModuleMarkPrecision();
		$limit = ConfigHelper::getSecondAttemptLimit();
		$moduleMark = 0.3*$pam + 0.7*$examMark;
		$moduleMark = round($moduleMark, $precision);
		if($attempt>1 && $limit==self::SECOND_ATTEMPT_LIMIT_MODULE)
			$moduleMark = max([$moduleMark, 6.9]);
		return $moduleMark;
	}
	static public function calculateModuleGrade(float $moduleMark, int $conclusion)
	{
		if($conclusion == ExamHelper::CONCLUSION_RESERVED)
			return 'I';
		if($conclusion == self::CONCLUSION_FAILED || $conclusion == self::CONCLUSION_FAILED_EXPIRED)
			return 'F';

		if($moduleMark <= 4.7)
			return 'D';
		if($moduleMark <= 5.4)
			return 'D+';
		if($moduleMark <= 6.2)
			return 'C';
		if($moduleMark <= 6.9)
			return 'C+';
		if($moduleMark <= 7.7)
			return 'B';
		if($moduleMark <= 8.4)
			return 'B+';
		if($moduleMark <= 8.9)
			return 'A';
		return 'A+';
	}
	static public function specialMarkToText(int $specialMark)
	{
		return match ($specialMark)
		{
			self::SPECIAL_MARK_TKD => 'TKD',
			self::SPECIAL_MARK_N25 => 'N25',
			self::SPECIAL_MARK_N100 => 'N100'
		};
	}
	static public function normalizeMarks(object &$learner):void
	{
		if(!empty($learner->pam1) && $learner->pam1<0)
			$learner->pam1 = self::specialMarkToText($learner->pam1);
		if(!empty($learner->pam2) && $learner->pam2<0)
			$learner->pam2 = self::specialMarkToText($learner->pam2);
		if(!empty($learner->pam) && $learner->pam<0)
			$learner->pam = self::specialMarkToText($learner->pam);
	}
	static public function conclude($moduleMark, $finalExamMark, $anomaly, $attempt)
	{
		if($anomaly == ExamHelper::EXAM_ANOMALY_BAN)
			return self::CONCLUSION_FAILED_EXPIRED;

		if($anomaly == self::EXAM_ANOMALY_DELAY || $anomaly == self::EXAM_ANOMALY_REDO)
			return self::CONCLUSION_RESERVED;

		if ($finalExamMark < 4.0 || $moduleMark < 4.0)
		{
			$maxAttempts = ConfigHelper::getMaxExamAttempts();
			if($attempt>=$maxAttempts)
				return self::CONCLUSION_FAILED_EXPIRED;
			return self::CONCLUSION_FAILED;
		}

		return self::CONCLUSION_PASSED;
	}
}

