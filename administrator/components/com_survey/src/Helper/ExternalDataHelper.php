<?php

namespace Kma\Component\Survey\Administrator\Helper;
use Kma\Library\Kma\Enum\Gender;
use Kma\Library\Kma\Helper\DatabaseHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\StringHelper;

abstract class ExternalDataHelper
{
    public static function fetchCourses(): array
    {
        $groups = ApiHelper::getStudentClasses();
        if(empty($groups))
            return [];
        $courses = [];
        foreach ($groups as $group){
            $course = $group['STUDENTYEAR'];
            if(key_exists($course, $courses)){
                continue;
            }
            $courses[$course] = [
                'code'=>$course,
                'name'=>$course,
                'note'=>$group['YEAR']
            ];
        }
        return $courses;
    }
    public static function fetchDepartments(): array
    {
        $teachers = ApiHelper::getTeachers();
        if(empty($teachers))
            return [];
        $departments = [];
        foreach ($teachers as $teacher){
            $departmentCode = static::mapEmployeeCodeToDepartmentCode($teacher['STAFFCODE']);
            $departmentName = $teacher['DEPARTMENTID'];
            if(key_exists($departmentCode, $departments)){
                continue;
            }
            $departments[$departmentCode] = [
                'code'=>$departmentCode,
                'name'=>$departmentName,
                'note'=>''
            ];
        }
        return $departments;
    }
    public static function iterateCourseLearners(int $admissionYearFrom=0)
    {
        $studentClasses = ApiHelper::getStudentClasses();
        if(empty($studentClasses))
            return;

        foreach ($studentClasses as $studentClass){
            $admissionYear = $studentClass['YEAR'];
            if($admissionYear < $admissionYearFrom)
                continue;   //Skip admission years before the given threshold

            $courseCode = mb_strtoupper($studentClass['STUDENTYEAR']);
            $classStudents = ApiHelper::getStudentsByStudentClassId($studentClass['NAME']);
            if(empty($classStudents))
                continue;

            $learners = [];
            foreach ($classStudents as $student){
                if(empty($student['STUDENTID']))      //Yes, it's very strangery possible that some students have no STUDENTID!
                    continue;

                $fullName = trim($student['FULLNAME']);
                [$lastName,$firstName] = StringHelper::parseVietnameseFullName($fullName);
	            $gender = match ($student['GENDER']) {
		            'Nam'      => Gender::Male->value,
		            'Nu','Nữ'  => Gender::Female->value,
		            default    => null
	            };
				$learners[] = [
                    'firstname'=>$firstName,
                    'lastname'=>$lastName,
                    'gender'=>$gender,
                    'code'=>mb_strtoupper(preg_replace('/ /','',$student['STUDENTID'])),
                    'phone'=>$student['PHONENUMBER'],
                    'email'=>$student['EMAIL'],
                ];
            }
            yield ['course_code'=>$courseCode,'learners'=>$learners];
        }
    }
    static protected function mapEmployeeCodeToDepartmentCode(string $employeeCode): string
    {
        $prefix = mb_substr($employeeCode, 0, 3); // Lấy 3 ký tự đầu tiên của mã nhân viên
        return match ($prefix) {
            'GVM', 'MAT', 'MCB','MĐT','MCT', 'MDT','MLC','MLL','MQG' => 'GVM',      //Giảng viên mời
            'KCB', 'KQG','KLC', 'TDK' => 'KCB',                         //Khoa Cơ bản mới
            'KMM', 'TTH' => 'KMM',                                      //Khoa Mật mã mới
            default => $prefix
        };
    }
    public static function fetchEmployees(): array
    {
        $teachers = ApiHelper::getTeachers();
        if(empty($teachers))
            return [];

        $employees = [];
        foreach ($teachers as $teacher){
            $code = mb_strtoupper($teacher['STAFFCODE']);
            $unitCode = static::mapEmployeeCodeToDepartmentCode($code);
            if($unitCode==='GVM')
                continue;       //Skip visiting lecturers
            $fullName = trim($teacher['FULLNAME']);
            [$lastName,$firstName] = StringHelper::parseVietnameseFullName($fullName);
            $gender = match ($teacher['GENDER']) {
	            'Nam'      => Gender::Male->value,
	            'Nu','Nữ'  => Gender::Female->value,
	            default => null
            };

            $employees[] = [
                'code' => $code,
                'firstname'=>$firstName,
                'lastname'=>$lastName,
                'unit_code' => $unitCode,
                'gender'=>$gender,
                'phone'=>$teacher['PHONE'],
                'email'=>$teacher['WORKEMAIL'],
            ];
        }
        return $employees;
    }
    public static function fetchVisitingLecturers(): array
    {
        $teachers = ApiHelper::getTeachers();
        if(empty($teachers))
            return [];

        $employees = [];
        foreach ($teachers as $teacher){
            $code = mb_strtoupper($teacher['STAFFCODE']);
            $unitCode = static::mapEmployeeCodeToDepartmentCode($code);
            if($unitCode !== 'GVM')
                continue;       //Keep only visiting lecturers
            $fullName = trim($teacher['FULLNAME']);
            $fullName = trim(preg_replace('/\s*\(GVM\)$/u', '', $fullName));
            [$lastName,$firstName] = StringHelper::parseVietnameseFullName($fullName);
            $gender = match ($teacher['GENDER']) {
	            'Nam'      => Gender::Male->value,
	            'Nu','Nữ'  => Gender::Female->value,
                default => null
            };

            $employees[] = [
                'code' => $code,
                'firstname'=>$firstName,
                'lastname'=>$lastName,
                'unit_code' => $unitCode,
                'gender'=>$gender,
                'phone'=>$teacher['PHONE'],
                'email'=>$teacher['WORKEMAIL'],
            ];
        }
        return $employees;
    }

    public static function fetchClasses(string $academicyear, int $term): array
    {
        $schedules = ApiHelper::getSchedules($academicyear, $term);
        if(empty($schedules))
            return [];

        $classes = [];
        foreach ($schedules as $item){
            $class=[];
            $class['code'] = $item['classId'];
            $class['term'] = $term;
            $class['academicyear'] = DatetimeHelper::encodeAcademicYear($academicyear);
            $class['subject'] = $item['courseName'];
            $class['size'] = $item['studentNum'];
            $schedulePeriods = $item['SCHEDULE'];
            if(count($schedulePeriods)>0)
            {
                $class['lecturer'] = $schedulePeriods[0]['tenGiangVien']??'';
                $class['start_date'] = DatetimeHelper::getFirstEventDate(
                    $schedulePeriods[0]['tuNgay'],
                    $schedulePeriods[0]['denNgay'],
                    DatetimeHelper::toEnglishDayOfWeek($schedulePeriods[0]['thu'])
                )->format('Y-m-d');
                $class['end_date'] = DatetimeHelper::getLastEventDate(
                    $schedulePeriods[count($schedulePeriods)-1]['tuNgay'],
                    $schedulePeriods[count($schedulePeriods)-1]['denNgay'],
                    DatetimeHelper::toEnglishDayOfWeek($schedulePeriods[0]['thu'])
                )->format('Y-m-d');
            }
            else{
                $class['lecturer'] = '';
                $class['start_date'] = null;
                $class['end_date'] = null;
            }
            $classes[] = $class;
        }
        return $classes;
    }

    public static function fetchClassLearners(string $externalClassId):array
    {
        $students = ApiHelper::getStudentsByClassId($externalClassId);
        if(empty($students))
            return [];

        return array_column($students,'STUDENTID');
    }

    /**
     * Fetch all examseasons in the given academic year and term.
     * @param int $max The maximum number of examseasons to retrieve; 0 to retrieve all.
     * @return array An associative array['id'=>'name'] containing examseasons information.
     * @since 1.0.0
     */
    public static function fetchExamseasons(int $max=20):array
    {
        $db = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select('id, name')
            ->from('#__eqa_examseasons')
            ->order('id DESC');
        if($max)
            $query->setLimit($max);
        $db->setQuery($query);
        return $db->loadAssocList('id','name');
    }

    public static function fetchExamseasonExaminees(int $examSeasonId):array
    {
        $db = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select('DISTINCT(c.code)')
            ->from('#__eqa_exam_learner AS a')
            ->leftJoin('#__eqa_exams AS b','b.id=a.exam_id')
            ->leftJoin('#__eqa_learners AS c','c.id=a.learner_id')
            ->where('b.examseason_id='.$examSeasonId);
        $db->setQuery($query);
        return $db->loadColumn();
    }
}