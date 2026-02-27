<?php

namespace Kma\Component\Survey\Administrator\Controller;

use Joomla\CMS\MVC\Controller\AdminController;
use Kma\Component\Survey\Administrator\Helper\ApiHelper;

class TestController extends AdminController
{
    public function vietqr()
    {
        $bank = "970423"; // TPBank
        $account = "y999999999";
        $amount = 10000;
        $info = "ABC876 X001";

        $qrUrl = "https://img.vietqr.io/image/{$bank}-{$account}-compact2.png?amount={$amount}&addInfo=" . urlencode($info);

        // Chèn vào HTML
        echo "<img src='$qrUrl' alt='VietQR' />";
    }
    public function restapi()
    {
        $subjects = [];
        $schoolYears = ['2022_2023','2023_2024','2024_2025'];
        foreach ($schoolYears as $schoolYear) {
            for($semester=1;$semester<=2;$semester++) {
                $data = ApiHelper::getSchedules($schoolYear,$semester);
                foreach ($data as $item) {
                    $subjectCode = $item['courseId'];
                    $subjectName = $item['courseName'];
                    $subjects[$subjectCode] = $subjectName;
                }
            }
        }
        echo '<pre>';
        print_r($subjects);
        echo '</pre>';
        return;
        echo '<table class="table table-bordered">';
        echo '<tr><th>TT</th><th>Khóa</th><th>Mã HVSV</th><th>Họ đệm</th><th>Tên</th><th>Phone</th><th>Email</th></tr>';
        $seq=1;
        foreach ($learners as $learner) {
            echo '<tr>';
            echo '<td>' . $seq++ . '</td>';
            echo '<td>' . $learner['course'] . '</td>';
            echo '<td>' . $learner['code'] . '</td>';
            echo '<td>' . $learner['lastname'] . '</td>';
            echo '<td>' . $learner['firstname'] . '</td>';
            echo '<td>' . $learner['phone'] . '</td>';
            echo '<td>' . $learner['email'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
}