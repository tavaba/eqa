<?php

/**
 * @package     Kma.Component.Eqa
 * @subpackage  Administrator.Traits
 *
 * @copyright   (C) 2026 KMA. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Kma\Component\Eqa\Administrator\Traits;

defined('_JEXEC') or die;

use Joomla\Database\ParameterType;
use Kma\Component\Eqa\Administrator\Service\CampusService;
use Kma\Library\Kma\Helper\ComponentHelper;
use RuntimeException;

/**
 * Chốt chặn quyền theo cơ sở đào tạo cho các thực thể thuộc CÂY TỔ CHỨC THI,
 * nơi cơ sở được suy diễn qua examseason_id (hoặc assessment_id).
 *
 * Dùng cho các View/Model con nhận examseason_id / examsession_id / exam_id
 * trực tiếp từ URL: ExamseasonField chỉ giới hạn danh sách hiển thị, còn khi
 * người dùng gọi thẳng URL với một id thuộc cơ sở khác thì cần chốt chặn này.
 *
 * Cách dùng điển hình trong View::prepareDataForLayoutDefault():
 *
 *     $examseasonId = Factory::getApplication()->input->getInt('examseason_id');
 *     $this->assertExamseasonInCampusScope($examseasonId);   // ném lỗi nếu chéo cơ sở
 *
 * @since 2.1.6
 */
trait CampusScopedByExamseason
{
    /**
     * @var CampusService|null
     * @since 2.1.6
     */
    private ?CampusService $campusServiceForExamseason = null;

    /**
     * @return  CampusService
     * @since   2.1.6
     */
    protected function getCampusServiceForExamseason(): CampusService
    {
        if ($this->campusServiceForExamseason === null) {
            $this->campusServiceForExamseason = ComponentHelper::getComponent()->getCampusService();
        }

        return $this->campusServiceForExamseason;
    }

    /**
     * Khẳng định một kỳ thi thuộc phạm vi cơ sở đào tạo mà người dùng được phép.
     *
     * @param   int  $examseasonId
     *
     * @return  void
     * @throws  RuntimeException  Nếu kỳ thi thuộc cơ sở khác hoặc không tồn tại
     * @since   2.1.6
     */
    protected function assertExamseasonInCampusScope(int $examseasonId): void
    {
        $this->assertCampusAllowed(
            $this->fetchCampusIdOfExamseason($examseasonId),
            'kỳ thi'
        );
    }

    /**
     * Khẳng định một ca thi thuộc phạm vi cơ sở đào tạo được phép.
     *
     * Ca thi có thể thuộc kỳ thi (examseason_id) hoặc kỳ sát hạch (assessment_id).
     *
     * @param   int  $examsessionId
     *
     * @return  void
     * @throws  RuntimeException
     * @since   2.1.6
     */
    protected function assertExamsessionInCampusScope(int $examsessionId): void
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('es.campus_id', 'season_campus'),
                $db->quoteName('a.campus_id', 'assessment_campus'),
            ])
            ->from($db->quoteName('#__eqa_examsessions', 's'))
            ->leftJoin($db->quoteName('#__eqa_examseasons', 'es') . ' ON ' . $db->quoteName('es.id') . ' = ' . $db->quoteName('s.examseason_id'))
            ->leftJoin($db->quoteName('#__eqa_assessments', 'a') . ' ON ' . $db->quoteName('a.id') . ' = ' . $db->quoteName('s.assessment_id'))
            ->where($db->quoteName('s.id') . ' = :sid')
            ->bind(':sid', $examsessionId, ParameterType::INTEGER);

        $row = $db->setQuery($query)->loadObject();

        $campusId = (int) ($row->season_campus ?? $row->assessment_campus ?? 0);

        $this->assertCampusAllowed($campusId, 'ca thi');
    }

    /**
     * Khẳng định một kỳ sát hạch thuộc phạm vi cơ sở đào tạo được phép.
     *
     * @param   int  $assessmentId
     *
     * @return  void
     * @throws  RuntimeException
     * @since   2.1.6
     */
    protected function assertAssessmentInCampusScope(int $assessmentId): void
    {
        $this->assertCampusAllowed(
            $this->fetchCampusIdOfAssessment($assessmentId),
            'kỳ sát hạch'
        );
    }

    /**
     * Đọc campus_id của một kỳ sát hạch.
     *
     * @param   int  $assessmentId
     *
     * @return  int  0 nếu không tồn tại
     * @since   2.1.6
     */
    protected function fetchCampusIdOfAssessment(int $assessmentId): int
    {
        if ($assessmentId <= 0) {
            return 0;
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select($db->quoteName('campus_id'))
            ->from($db->quoteName('#__eqa_assessments'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $assessmentId, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * Khẳng định một môn thi thuộc phạm vi cơ sở đào tạo được phép.
     *
     * @param   int  $examId
     *
     * @return  void
     * @throws  RuntimeException
     * @since   2.1.6
     */
    protected function assertExamInCampusScope(int $examId): void
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select($db->quoteName('es.campus_id'))
            ->from($db->quoteName('#__eqa_exams', 'e'))
            ->leftJoin($db->quoteName('#__eqa_examseasons', 'es') . ' ON ' . $db->quoteName('es.id') . ' = ' . $db->quoteName('e.examseason_id'))
            ->where($db->quoteName('e.id') . ' = :eid')
            ->bind(':eid', $examId, ParameterType::INTEGER);

        $campusId = (int) $db->setQuery($query)->loadResult();

        $this->assertCampusAllowed($campusId, 'môn thi');
    }

    /**
     * Khẳng định một phòng thi thuộc phạm vi cơ sở đào tạo được phép.
     *
     * Phòng thi (examroom) thuộc một ca thi (examsession); ca thi thuộc kỳ thi
     * HOẶC kỳ sát hạch. Campus được suy theo đường đó.
     *
     * @param   int  $examroomId
     *
     * @return  void
     * @throws  RuntimeException
     * @since   2.1.6
     */
    protected function assertExamroomInCampusScope(int $examroomId): void
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('es.campus_id', 'season_campus'),
                $db->quoteName('a.campus_id', 'assessment_campus'),
            ])
            ->from($db->quoteName('#__eqa_examrooms', 'er'))
            ->leftJoin($db->quoteName('#__eqa_examsessions', 's') . ' ON ' . $db->quoteName('s.id') . ' = ' . $db->quoteName('er.examsession_id'))
            ->leftJoin($db->quoteName('#__eqa_examseasons', 'es') . ' ON ' . $db->quoteName('es.id') . ' = ' . $db->quoteName('s.examseason_id'))
            ->leftJoin($db->quoteName('#__eqa_assessments', 'a') . ' ON ' . $db->quoteName('a.id') . ' = ' . $db->quoteName('s.assessment_id'))
            ->where($db->quoteName('er.id') . ' = :erid')
            ->bind(':erid', $examroomId, ParameterType::INTEGER);

        $row = $db->setQuery($query)->loadObject();

        $campusId = (int) ($row->season_campus ?? $row->assessment_campus ?? 0);

        $this->assertCampusAllowed($campusId, 'phòng thi');
    }

    /**
     * Khẳng định một yêu cầu phúc khảo (regrading) thuộc phạm vi cơ sở được phép.
     *
     * regrading → exam → examseason → campus.
     *
     * @param   int  $regradingId
     *
     * @return  void
     * @throws  RuntimeException
     * @since   2.1.6
     */
    protected function assertRegradingInCampusScope(int $regradingId): void
    {
        $this->assertCampusAllowed(
            $this->fetchCampusIdViaExamJoin('#__eqa_regradings', $regradingId),
            'yêu cầu phúc khảo'
        );
    }

    /**
     * Khẳng định một yêu cầu sửa điểm (gradecorrection) thuộc phạm vi được phép.
     *
     * gradecorrection → exam → examseason → campus.
     *
     * @param   int  $gradecorrectionId
     *
     * @return  void
     * @throws  RuntimeException
     * @since   2.1.6
     */
    protected function assertGradecorrectionInCampusScope(int $gradecorrectionId): void
    {
        $this->assertCampusAllowed(
            $this->fetchCampusIdViaExamJoin('#__eqa_gradecorrections', $gradecorrectionId),
            'yêu cầu sửa điểm'
        );
    }

    /**
     * Suy campus_id của một bản ghi có cột exam_id, qua exam → examseason.
     *
     * @param   string  $tableName  Bảng có cột exam_id (ví dụ '#__eqa_regradings')
     * @param   int     $recordId
     *
     * @return  int  0 nếu không xác định được
     * @since   2.1.6
     */
    protected function fetchCampusIdViaExamJoin(string $tableName, int $recordId): int
    {
        if ($recordId <= 0) {
            return 0;
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select($db->quoteName('es.campus_id'))
            ->from($db->quoteName($tableName, 'r'))
            ->leftJoin($db->quoteName('#__eqa_exams', 'e') . ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('r.exam_id'))
            ->leftJoin($db->quoteName('#__eqa_examseasons', 'es') . ' ON ' . $db->quoteName('es.id') . ' = ' . $db->quoteName('e.examseason_id'))
            ->where($db->quoteName('r.id') . ' = :rid')
            ->bind(':rid', $recordId, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * Khẳng định một lớp học phần thuộc phạm vi cơ sở đào tạo được phép.
     *
     * Lớp học phần có cột campus_id trực tiếp.
     *
     * @param   int  $classId
     *
     * @return  void
     * @throws  RuntimeException
     * @since   2.1.6
     */
    protected function assertClassInCampusScope(int $classId): void
    {
        if ($classId <= 0) {
            $this->assertCampusAllowed(0, 'lớp học phần');
            return;
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select($db->quoteName('campus_id'))
            ->from($db->quoteName('#__eqa_classes'))
            ->where($db->quoteName('id') . ' = :cid')
            ->bind(':cid', $classId, ParameterType::INTEGER);

        $campusId = (int) $db->setQuery($query)->loadResult();

        $this->assertCampusAllowed($campusId, 'lớp học phần');
    }

    /**
     * Đọc campus_id của một kỳ thi.
     *
     * @param   int  $examseasonId
     *
     * @return  int  0 nếu không tồn tại
     * @since   2.1.6
     */
    protected function fetchCampusIdOfExamseason(int $examseasonId): int
    {
        if ($examseasonId <= 0) {
            return 0;
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select($db->quoteName('campus_id'))
            ->from($db->quoteName('#__eqa_examseasons'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $examseasonId, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * Kiểm tra chung: campus có nằm trong danh sách được phép không.
     *
     * @param   int     $campusId
     * @param   string  $entityLabel  Nhãn để ghép vào thông báo lỗi
     *
     * @return  void
     * @throws  RuntimeException
     * @since   2.1.6
     */
    private function assertCampusAllowed(int $campusId, string $entityLabel): void
    {
        if ($campusId <= 0) {
            throw new RuntimeException(sprintf(
                'Không xác định được cơ sở đào tạo của %s. Thao tác bị từ chối.',
                $entityLabel
            ));
        }

        if (!$this->getCampusServiceForExamseason()->canManageCampus($campusId)) {
            throw new RuntimeException(sprintf(
                'Bạn không có quyền làm việc với %s thuộc cơ sở đào tạo "%s".',
                $entityLabel,
                $this->getCampusServiceForExamseason()->getCampusName($campusId) ?: ('#' . $campusId)
            ));
        }
    }
}
