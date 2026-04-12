<?php

/**
 * @package     Kma\Component\Eqa\Administrator\Model
 * @since       2.0.9
 */

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Kma\Component\Eqa\Administrator\Enum\MailContextType;
use Kma\Component\Eqa\Administrator\Enum\ObjectType;
use Kma\Component\Eqa\Administrator\Enum\SpecialMark;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;
use Kma\Library\Kma\Enum\MailRecipientType;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Model\MailCampaignsModel as BaseMailCampaignModel;
use Kma\Library\Kma\Service\MailService;

/**
 * Model quản lý chiến dịch email của com_eqa.
 *
 * Kế thừa toàn bộ logic query/filter từ BaseMailCampaignModel.
 * Chỉ cần override các method đặc thù của com_eqa:
 *   - getMailService()          : lấy MailService từ DI Container
 *   - getContextLabel()         : tên môn thi / kỳ thi / lớp / khóa
 *   - resolveRecipients()       : danh sách thí sinh theo ngữ cảnh
 *   - buildPlaceholderResolver(): placeholder đặc thù theo context type
 *
 * @since 2.0.9
 */
class MailCampaignsModel extends BaseMailCampaignModel
{
	private const string TAG_SPAN_BADGE_DANGER = '<span style="display: inline-block; padding: 4px 8px; 
			font-size: 12px; font-weight: 600; line-height: 1; color: #ffffff;
    		background-color: #dc3545; border-radius: 4px; white-space: nowrap;">';
	private const string TAG_SPAN_BADGE_SUCCESS = '<span style="display: inline-block; padding: 4px 8px;
    		font-size: 12px; font-weight: 600; line-height: 1; color: #ffffff;
    		background-color: #198754; border-radius: 4px; white-space: nowrap;">';
    /**
     * Lấy MailService đã được đăng ký trong DI Container của com_eqa.
     *
     * @return MailService
     * @since  2.0.9
     */
    protected function getMailService(): MailService
    {
        return ComponentHelper::getMailService();
    }

    // =========================================================================
    // Overridable — cung cấp context label theo nghiệp vụ com_eqa
    // =========================================================================

    /**
     * Trả về nhãn hiển thị của ngữ cảnh.
     *
     * @param  int  $contextType  Giá trị MailContextType enum
     * @param  int  $contextId    ID đối tượng ngữ cảnh
     *
     * @return string
     * @since  2.0.9
     */
    protected function getContextLabel(int $contextType, int $contextId): string
    {
        $type = MailContextType::tryFrom($contextType);

        if ($type === null) {
            return '#' . $contextId;
        }


        return match ($type) {
            MailContextType::Exam       => DatabaseHelper::getExamInfo($contextId)?->name
                                          ?? ('Môn thi #' . $contextId),
            MailContextType::ExamSeason => DatabaseHelper::getExamseasonInfo($contextId)?->name
                                          ?? ('Kỳ thi #' . $contextId),
            MailContextType::Group      => DatabaseHelper::getGroupInfo($contextId)?->code
                                          ?? ('Lớp #' . $contextId),
            MailContextType::Course     => DatabaseHelper::getCourseInfo($contextId)?->code
                                          ?? ('Khóa #' . $contextId),
            MailContextType::Manual     => 'Danh sách thủ công',
        };
    }

    // =========================================================================
    // Overridable — resolve danh sách người nhận theo ngữ cảnh com_eqa
    // =========================================================================

    /**
     * Resolve danh sách người nhận theo ngữ cảnh nghiệp vụ của com_eqa.
     *
     * Mỗi phần tử trả về là một object có:
     *   ->learner (object): id, code, lastname, firstname
     *   + các thuộc tính context data tương ứng với context type
     *     (dùng bởi buildPlaceholderResolver bên dưới)
     *
     * @param  int          $contextType
     * @param  int          $contextId
     * @param  string|null  $recipientFilter  JSON filter bổ sung
     *
     * @return array
     * @since  2.0.9
     */
    protected function resolveRecipients(
        int     $contextType,
        int     $contextId,
        ?string $recipientFilter
    ): array {
        $type = MailContextType::tryFrom($contextType);

        if ($type === null) {
            return [];
        }

        return match ($type) {
            MailContextType::Exam       => $this->resolveExamRecipients($contextId, $recipientFilter),
            MailContextType::ExamSeason => $this->resolveExamSeasonRecipients($contextId, $recipientFilter),
            MailContextType::Group      => $this->resolveGroupRecipients($contextId),
            MailContextType::Course     => $this->resolveCourseRecipients($contextId),
            MailContextType::Manual     => $this->resolveManualRecipients($recipientFilter),
        };
    }


    // =========================================================================
    // Private — resolve recipients theo từng context type
    // =========================================================================

    /**
     * Resolve danh sách thí sinh của một môn thi.
     *
     * Mỗi phần tử trả về có thêm: exam_name, exam_date, exam_time, room_name
     * (dùng cho placeholder {exam_name}, {exam_date}, {exam_time}, {room_name}).
     *
     * exam_date và exam_time được convert từ UTC sang Local Time.
     *
     * @param  int          $examId
     * @param  string|null  $recipientFilter  JSON, ví dụ {"has_room":true}
     *
     * @return array
     * @since  2.0.9
     */
    private function resolveExamRecipients(int $examId, ?string $recipientFilter): array
    {
        $db     = DatabaseHelper::getDatabaseDriver();
		$mailService = $this->getMailService();
        $filter = $recipientFilter !== null ? json_decode($recipientFilter, true) : [];

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('lr.id',         'id'),
	            $db->quoteName('lr.lastname',   'lastname'),
	            $db->quoteName('lr.firstname',  'firstname'),
	            $db->quoteName('lr.code',       'learner_code'),
	            $db->quoteName('ex.code',       'exam_code'),
	            $db->quoteName('ex.name',       'exam_name'),
	            $db->quoteName('el.debtor',     'is_debtor'),
	            $db->quoteName('cl.allowed',    'allowed'),
	            $db->quoteName('cl.pam',        'pam'),
	            $db->quoteName('ex.name',       'exam_name'),
	            $db->quoteName('st.type',       'stimulation_type'),
	            $db->quoteName('st.value',      'stimulation_value'),
	            $db->quoteName('es.name',       'examsession_name'),  // UTC DATETIME
	            $db->quoteName('es.start',      'examsession_start'),  // UTC DATETIME
	            $db->quoteName('er.name',       'examroom_name'),
	            $db->quoteName('el.code',       'examinee_code'),
            ])
            ->from('#__eqa_exam_learner AS el')
	        ->leftJoin('#__eqa_class_learner AS cl', 'cl.learner_id = el.learner_id AND cl.class_id=el.class_id')
	        ->leftJoin('#__eqa_learners AS lr', 'lr.id = el.learner_id')
            ->leftJoin('#__eqa_exams AS ex', 'ex.id = el.exam_id')
            ->leftJoin('#__eqa_examrooms AS er', 'er.id = el.examroom_id')
            ->leftJoin('#__eqa_examsessions AS es', 'es.id = er.examsession_id')
	        ->leftJoin('#__eqa_stimulations AS st', 'st.id = el.stimulation_id')
            ->where('el.exam_id = ' . $examId);

        // Áp dụng filter bổ sung từ recipientFilter JSON
        if (!empty($filter['has_room'])) {
            $query->where($db->quoteName('el.examroom_id') . ' > 0');
        }

        $db->setQuery($query);
        $recipients = $db->loadObjectList();

        // Bổ sung thêm các thuộc tính còn thiếu:
	    // Thuộc tính buộc phải có: type, email,
	    // Thuộc tính cho placeholder: fullname, examsession, allowed_to_exam
        foreach ($recipients as &$recipient) {
			$recipient->type = MailRecipientType::Learner->value;
			$recipient->email = $mailService->resolveLearnerEmail($recipient->learner_code);
			$recipient->fullname = implode(' ', [$recipient->lastname, $recipient->firstname]);

			//TODO: Remove this line for production environment
	        $recipient->email = 'testkt02@actvn.edu.vn';

	        $allowed = $recipient->allowed && !$recipient->is_debtor;
	        if($recipient->stimulation_type == StimulationHelper::TYPE_TRANS)
			{
				$recipient->allowed_to_exam = self::TAG_SPAN_BADGE_SUCCESS . 'QUY ĐỔI ĐIỂM</span>'
					. ' (' . $recipient->stimulation_value . ' điểm)';
				$recipient->examsession = '';
			}
			elseif($allowed && $recipient->stimulation_type == StimulationHelper::TYPE_EXEMPT)
			{
				$recipient->allowed_to_exam = self::TAG_SPAN_BADGE_SUCCESS . 'MIỄN THI</span>'
					. ' (' . $recipient->stimulation_value . ' điểm)';
				$recipient->examsession = '';
			}
			elseif($allowed)
	        {
		        $recipient->allowed_to_exam = self::TAG_SPAN_BADGE_SUCCESS . 'ĐƯỢC THI</span>';

		        //examsession
		        $startTime = DatetimeHelper::convertToLocalTime($recipient->examsession_start);
		        $recipient->examsession = sprintf("%s (%s ngày %s)",
			        $recipient->examsession_name,
			        DatetimeHelper::getHourAndMinute($startTime),
			        DatetimeHelper::getFullDate($startTime)
		        );
	        }
			else
			{
				//allowed_to_exam
				$reasonPam = $recipient->pam<0 ? SpecialMark::from($recipient->pam)->getLabel() : null;
				$reasonDebt = $recipient->is_debtor ? 'Nợ phí' : null;
				$reasons = [$reasonPam, $reasonDebt];
				$reasons = array_filter($reasons);
				$reasons = implode(', ', $reasons);
				$recipient->allowed_to_exam = self::TAG_SPAN_BADGE_DANGER . 'KHÔNG ĐƯỢC THI</span>';
				$recipient->allowed_to_exam .= ' (' . $reasons . ')';

				//examsession
				$recipient->examsession = '';
			}
		}

        return $recipients;
    }

    /**
     * Resolve danh sách thí sinh (distinct) của một kỳ thi.
     *
     * Mỗi thí sinh chỉ xuất hiện một lần dù thi nhiều môn.
     * Thêm context data: examseason_name.
     *
     * @param  int          $examSeasonId
     * @param  string|null  $recipientFilter
     *
     * @return array
     * @since  2.0.9
     */
    private function resolveExamSeasonRecipients(int $examSeasonId, ?string $recipientFilter): array
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('lr.id',    'learner_id'),
                $db->quoteName('lr.code',  'learner_code'),
                $db->quoteName('lr.lastname'),
                $db->quoteName('lr.firstname'),
                $db->quoteName('ks.name',  'examseason_name'),
            ])
            ->from($db->quoteName('#__eqa_exam_learner', 'el'))
            ->leftJoin(
                $db->quoteName('#__eqa_learners', 'lr') .
                ' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('el.learner_id')
            )
            ->leftJoin(
                $db->quoteName('#__eqa_exams', 'ex') .
                ' ON ' . $db->quoteName('ex.id') . ' = ' . $db->quoteName('el.exam_id')
            )
            ->leftJoin(
                $db->quoteName('#__eqa_examseasons', 'ks') .
                ' ON ' . $db->quoteName('ks.id') . ' = ' . $db->quoteName('ex.examseason_id')
            )
            ->where($db->quoteName('ex.examseason_id') . ' = ' . (int) $examSeasonId)
            ->group([
                $db->quoteName('lr.id'),
                $db->quoteName('lr.code'),
                $db->quoteName('lr.lastname'),
                $db->quoteName('lr.firstname'),
                $db->quoteName('ks.name'),
            ]);

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        $recipients = [];
        foreach ($rows as $row) {
            $learner            = new \stdClass();
            $learner->id        = (int) $row->learner_id;
            $learner->code      = $row->learner_code;
            $learner->lastname  = $row->lastname;
            $learner->firstname = $row->firstname;

            $recipient                   = new \stdClass();
            $recipient->learner          = $learner;
            $recipient->examseason_name  = $row->examseason_name ?? '';
            $recipients[]                = $recipient;
        }

        return $recipients;
    }

    /**
     * Resolve danh sách người học thuộc một lớp hành chính.
     *
     * @param  int  $groupId
     *
     * @return array
     * @since  2.0.9
     */
    private function resolveGroupRecipients(int $groupId): array
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('lr.id',        'learner_id'),
                $db->quoteName('lr.code',       'learner_code'),
                $db->quoteName('lr.lastname'),
                $db->quoteName('lr.firstname'),
            ])
            ->from($db->quoteName('#__eqa_learners', 'lr'))
            ->where($db->quoteName('lr.group_id') . ' = ' . (int) $groupId);

        $db->setQuery($query);

        return $this->rowsToRecipients($db->loadObjectList());
    }

    /**
     * Resolve danh sách người học thuộc một khóa học.
     *
     * @param  int  $courseId
     *
     * @return array
     * @since  2.0.9
     */
    private function resolveCourseRecipients(int $courseId): array
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('lr.id',        'learner_id'),
                $db->quoteName('lr.code',       'learner_code'),
                $db->quoteName('lr.lastname'),
                $db->quoteName('lr.firstname'),
            ])
            ->from($db->quoteName('#__eqa_learners', 'lr'))
            ->leftJoin(
                $db->quoteName('#__eqa_groups', 'g') .
                ' ON ' . $db->quoteName('g.id') . ' = ' . $db->quoteName('lr.group_id')
            )
            ->where($db->quoteName('g.course_id') . ' = ' . (int) $courseId);

        $db->setQuery($query);

        return $this->rowsToRecipients($db->loadObjectList());
    }

    /**
     * Resolve danh sách người học từ JSON thủ công.
     *
     * Định dạng JSON: {"learner_ids": [1, 2, 3, ...]}
     *
     * @param  string|null  $recipientFilter
     *
     * @return array
     * @since  2.0.9
     */
    private function resolveManualRecipients(?string $recipientFilter): array
    {
        if (empty($recipientFilter)) {
            return [];
        }

        $data       = json_decode($recipientFilter, true);
        $learnerIds = array_filter(
            array_map('intval', $data['learner_ids'] ?? [])
        );

        if (empty($learnerIds)) {
            return [];
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id',        'learner_id'),
                $db->quoteName('code',       'learner_code'),
                $db->quoteName('lastname'),
                $db->quoteName('firstname'),
            ])
            ->from($db->quoteName('#__eqa_learners'))
            ->where($db->quoteName('id') . ' IN (' . implode(',', $learnerIds) . ')');

        $db->setQuery($query);

        return $this->rowsToRecipients($db->loadObjectList());
    }

    /**
     * Chuyển mảng rows DB (chỉ có thông tin learner cơ bản) sang cấu trúc $recipient->learner.
     *
     * Dùng cho các context không cần context data bổ sung (Group, Course, Manual).
     *
     * @param  object[]  $rows
     *
     * @return array
     * @since  2.0.9
     */
    private function rowsToRecipients(array $rows): array
    {
        $recipients = [];

        foreach ($rows as $row) {
            $learner            = new \stdClass();
            $learner->id        = (int) $row->learner_id;
            $learner->code      = $row->learner_code;
            $learner->lastname  = $row->lastname;
            $learner->firstname = $row->firstname;

            $recipient          = new \stdClass();
            $recipient->learner = $learner;
            $recipients[]       = $recipient;
        }

        return $recipients;
    }

	protected function getLogObjectType(): int
	{
		return ObjectType::MailCampaign->value;
	}
}
