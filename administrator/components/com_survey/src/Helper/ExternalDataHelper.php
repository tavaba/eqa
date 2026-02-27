<?php

namespace Kma\Component\Survey\Administrator\Helper;

use Exception;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;
use Kma\Library\Kma\Helper\DatabaseHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\NumberHelper;
use Kma\Library\Kma\Helper\StringHelper;

abstract class ExternalDataHelper
{
    private const BASE_URL = 'http://42.112.213.87/hvmmapi/api'; // Replace with actual base URL
    private const API_USER = 'actvn';
    private const API_PASS = 'actvn@a123';

    private static $bearerToken = null;

    /**
     * Get Bearer token for API access
     *
     * @return string|false Bearer token or false on failure
     * @since 1.0.0
     */
    public static function getBearerToken(): string|false
    {
        // Return cached token if available
        if (self::$bearerToken !== null) {
            return self::$bearerToken;
        }

        try {
            // Create HTTP client
            $http = HttpFactory::getHttp();

            // Prepare token request URL
            $tokenUrl = self::BASE_URL . '/CTT_Token/LayChiTiet';
            $tokenUrl .= '?strUser=' . urlencode(self::API_USER);
            $tokenUrl .= '&strPass=' . urlencode(self::API_PASS);

            // Make GET request for token
            $response = $http->get($tokenUrl);

            if ($response->code === 200) {
                $responseData = json_decode($response->body, true);

                // Assuming the token is in the response body
                // You may need to adjust this based on actual API response structure
                if (isset($responseData['token'])) {
                    self::$bearerToken = $responseData['token'];
                    return self::$bearerToken;
                } elseif (isset($responseData['access_token'])) {
                    self::$bearerToken = $responseData['access_token'];
                    return self::$bearerToken;
                } else {
                    // If token structure is different, you might need to parse differently
                    self::$bearerToken = $response->body;
                    return self::$bearerToken;
                }
            }

            Log::add('Failed to get Bearer token. HTTP Code: ' . $response->code, Log::ERROR, 'api');
            return false;

        } catch (Exception $e) {
            Log::add('Error getting Bearer token: ' . $e->getMessage(), Log::ERROR, 'api');
            return false;
        }
    }

    /**
     * Make authenticated API request
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array $data Request data for POST requests
     *
     * @return array|false Response data or false on failure
     * @since 1.0.0
     */
    private static function makeAuthenticatedRequest($endpoint, $method = 'POST', $data = []): bool|array
    {
        $token = self::getBearerToken();

        if (!$token) {
            return false;
        }

        try {
            $http = HttpFactory::getHttp();

            // Prepare headers
            $headers = [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            $url = self::BASE_URL . $endpoint;

            // Make request based on method
            if (strtoupper($method) === 'POST') {
                $response = $http->post($url, json_encode($data), $headers);
            } else {
                $response = $http->get($url, $headers);
            }

            if ($response->code === 200) {
                return json_decode($response->body, true);
            }

            Log::add('API request failed. HTTP Code: ' . $response->code . ' Response: ' . $response->body, Log::ERROR, 'api');
            return false;

        } catch (Exception $e) {
            Log::add('Error making API request: ' . $e->getMessage(), Log::ERROR, 'api');
            return false;
        }
    }

    /**
     * Get list of teachers
     *
     * @return array|false Teacher data or false on failure
     * @since 1.0.0
     */
    public static function getTeachers()
    {
        return self::makeAuthenticatedRequest('/ThongTin/getTeachers');
    }

    /**
     * Get list of student classes (administrative classes)
     *
     * @return array|false Student class data or false on failure
     * @since 1.0
     */
    public static function getStudentClasses()
    {
        return self::makeAuthenticatedRequest('/ThongTin/getStudentClass');
    }

    /**
     * Get exam scores
     *
     * @param string $schoolYear School year (e.g., '2022_2023')
     * @param int $semester Semester number
     *
     * @return array|false Exam score data or false on failure
     * @since 1.0.0
     */
    public static function getExamScores($schoolYear = '2022_2023', $semester = 1): bool|array
    {
        $endpoint = '/ThongTin/getExamScore?schoolyear=' . $schoolYear . '&semester=' . $semester;
        return self::makeAuthenticatedRequest($endpoint);
    }

    /**
     * Get students by class ID
     *
     * @param string $classId Class ID
     *
     * @return array|false Student data or false on failure
     * @since 1.0.0
     */
    public static function getStudentsByClassId($classId): bool|array
    {
        $endpoint = '/ThongTin/getStudentByClassId?classId=' . urlencode($classId);
        return self::makeAuthenticatedRequest($endpoint);
    }

    /**
     * Get schedules for a semester
     *
     * @param string $schoolYear School year (e.g., '2022_2023')
     * @param int $semester Semester number
     *
     * @return array|false Schedule data or false on failure
     * @since 1.0.0
     */
    public static function getSchedules($schoolYear = '2022_2023', $semester = 1): bool|array
    {
        $endpoint = '/ThongTin/getSchedules?schoolyear=' . $schoolYear . '&semester=' . $semester;
        return self::makeAuthenticatedRequest($endpoint);
    }

    /**
     * Get students by student class ID
     *
     * @param string $studentClass Student class ID (e.g., 'CHAT9')
     *
     * @return array|false Student data or false on failure
     * @since 1.0.0
     */
    public static function getStudentsByStudentClassId($studentClass): bool|array
    {
        $endpoint = '/ThongTin/getStudentByStudentClassId?studentClass=' . urlencode($studentClass);
        return self::makeAuthenticatedRequest($endpoint);
    }


    /**
     * @param string|null $updatedAfter  Một timestampt có dạng 'Y-m-d H:i:s' (YYYY-MM-DD HH:MM:SS).
     *                           Ví dụ: '2023-05-19 16:47:08'. Nếu NULL thì không giới hạn.
     * @param int[]|null $coreIds Giới hạn kết quả chỉ lấy những người có externalId trong mảng này.
     *                           Nếu là NULL thì không giới hạn. Nhưng mảng rỗng sẽ gây ra lỗi.
     *
     * @return string JSON encoded data from the source
     * @throws Exception
     * @since 1.0.0
     */
    public static function fetchEmployeesFromEqa(?string $updatedAfter, ?array $coreIds=null): string
    {
        //Check time validity
        if(!is_null($updatedAfter) && preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $updatedAfter) == 0)
            throw new Exception('Invalid timestampt!');

        //Check ID set validity
        if(!is_null($coreIds))
        {
            if(empty($coreIds))
                throw new Exception('The parameter $externalIds can be NULL but cannot be an empty array!');
            if(!NumberHelper::isIntegerArray($coreIds))
                throw new Exception('Some elements of the parameter $externalIds are not integers!');
            $idSet = implode(',', $coreIds);
        }

        //Fetch learners
        $db = DatabaseHelper::getDatabaseDriver();
        $columns = [
            'a.id AS core_id',
            'a.firstname AS firstname',
            'a.lastname AS lastname',
            'a.code AS code',
            'a.description AS note'
        ];
        $query = $db->getQuery(true)
            ->select($columns)
            ->from('#__eqa_employees AS a');
        if(isset($idSet))
            $query->where("a.id IN ($idSet)");
        if($updatedAfter)
            $query->where('(a.updated_at >' . $db->quote($updatedAfter) . ' OR a.created_at >' . $db->quote($updatedAfter) . ')');
        $db->setQuery($query);
        $items = $db->loadAssocList();
        return json_encode($items);
    }
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
                    'Nam' => RespondentHelper::RESPONDENT_GENDER_MALE,
                    'Nu','Nữ' => RespondentHelper::RESPONDENT_GENDER_FEMALE,
                    default => null
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
                'Nam' => RespondentHelper::RESPONDENT_GENDER_MALE,
                'Nu','Nữ' => RespondentHelper::RESPONDENT_GENDER_FEMALE,
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
                'Nam' => RespondentHelper::RESPONDENT_GENDER_MALE,
                'Nu','Nữ' => RespondentHelper::RESPONDENT_GENDER_FEMALE,
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