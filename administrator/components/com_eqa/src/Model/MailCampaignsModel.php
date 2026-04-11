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
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
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
    // =========================================================================
    // Abstract override — bắt buộc
    // =========================================================================

    /**
     * Lấy MailService đã được đăng ký trong DI Container của com_eqa.
     *
     * @return MailService
     * @since  2.0.9
     */
    protected function getMailService(): MailService
    {
        return Factory::getContainer()->get(MailService::class);
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
    // Overridable — placeholder resolver theo context type
    // =========================================================================

    /**
     * Trả về callback resolver placeholder phù hợp với context type.
     *
     * @param  int  $contextType
     *
     * @return callable  fn(object $recipient): array<string, string>
     * @since  2.0.9
     */
    protected function buildPlaceholderResolver(int $contextType): callable
    {
        $type = MailContextType::tryFrom($contextType);

        return match ($type) {
            MailContextType::Exam => static function (object $r): array {
                return array_merge(
                    MailService::buildCommonPlaceholders($r->learner),
                    [
                        '{exam_name}' => $r->exam_name  ?? '',
                        '{exam_date}' => $r->exam_date  ?? '',
                        '{exam_time}' => $r->exam_time  ?? '',
                        '{room_name}' => $r->room_name  ?? '',
                    ]
                );
            },
            MailContextType::ExamSeason => static function (object $r): array {
                return array_merge(
                    MailService::buildCommonPlaceholders($r->learner),
                    [
                        '{examseason_name}' => $r->examseason_name ?? '',
                    ]
                );
            },
            // Group, Course, Manual: chỉ cần common placeholders
            default => static fn(object $r): array
                => MailService::buildCommonPlaceholders($r->learner),
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
        $db     = $this->getDatabase();
        $filter = $recipientFilter !== null ? json_decode($recipientFilter, true) : [];

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('lr.id',         'learner_id'),
                $db->quoteName('lr.code',        'learner_code'),
                $db->quoteName('lr.lastname'),
                $db->quoteName('lr.firstname'),
                $db->quoteName('ex.name',        'exam_name'),
                $db->quoteName('es.start',       'exam_start'),  // UTC DATETIME
                $db->quoteName('rm.name',        'room_name'),
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
                $db->quoteName('#__eqa_examrooms', 'er') .
                ' ON ' . $db->quoteName('er.id') . ' = ' . $db->quoteName('el.examroom_id')
            )
            ->leftJoin(
                $db->quoteName('#__eqa_examsessions', 'es') .
                ' ON ' . $db->quoteName('es.id') . ' = ' . $db->quoteName('er.examsession_id')
            )
            ->leftJoin(
                $db->quoteName('#__eqa_rooms', 'rm') .
                ' ON ' . $db->quoteName('rm.id') . ' = ' . $db->quoteName('er.room_id')
            )
            ->where($db->quoteName('el.exam_id') . ' = ' . (int) $examId);

        // Áp dụng filter bổ sung từ recipientFilter JSON
        if (!empty($filter['has_room'])) {
            $query->where($db->quoteName('el.examroom_id') . ' > 0');
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        // Chuyển sang cấu trúc $recipient->learner + context data
        $recipients = [];
        foreach ($rows as $row) {
            $learner            = new \stdClass();
            $learner->id        = (int) $row->learner_id;
            $learner->code      = $row->learner_code;
            $learner->lastname  = $row->lastname;
            $learner->firstname = $row->firstname;

            $recipient            = new \stdClass();
            $recipient->learner   = $learner;
            $recipient->exam_name = $row->exam_name ?? '';

            // exam_start lưu UTC → convert sang Local Time cho placeholder hiển thị
            if (!empty($row->exam_start)) {
                $recipient->exam_date = \Kma\Library\Kma\Helper\DatetimeHelper::getFullDate($row->exam_start);
                $recipient->exam_time = \Kma\Library\Kma\Helper\DatetimeHelper::getHourAndMinute($row->exam_start);
            }
            else {
                $recipient->exam_date = '';
                $recipient->exam_time = '';
            }

            $recipient->room_name = $row->room_name ?? '';
            $recipients[]         = $recipient;
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
