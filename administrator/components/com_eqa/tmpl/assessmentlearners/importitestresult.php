<?php
defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;

/** @var \Kma\Component\Eqa\Administrator\View\AssessmentLearners\HtmlView $this */
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-8">

        <!-- Hướng dẫn -->
        <div class="alert alert-info mb-4">
            <h6 class="alert-heading">
                <span class="icon-info me-1" aria-hidden="true"></span>
                Hướng dẫn nhập điểm thi iTest
            </h6>
            <ul class="mb-0 ps-3">
                <li>Xuất file bảng điểm tổng hợp từ hệ thống <strong>iTest</strong> theo định dạng <strong>.xlsx</strong>.</li>
                <li>File phải chứa đầy đủ kết quả của <strong>tất cả thí sinh</strong> đã được xếp phòng thi trong kỳ sát hạch này.</li>
                <li>Chọn file và nhấn nút <strong>"Nhập điểm"</strong> trên thanh công cụ.</li>
                <li>Hệ thống sẽ kiểm tra tính toàn vẹn dữ liệu trước khi ghi. Nếu có lỗi, <strong>toàn bộ dữ liệu sẽ không được ghi</strong> và thông báo lỗi chi tiết sẽ hiển thị.</li>
                <li>Quy tắc xử lý bất thường:
                    <ul>
                        <li><strong>Vắng thi / Đình chỉ:</strong> điểm = 0, kết quả = Không đạt.</li>
                        <li><strong>Bình thường:</strong> điểm tính từ số câu đúng Listening + Reading theo bảng quy đổi TOEIC chuẩn.</li>
                    </ul>
                </li>
                <li>Ngưỡng đạt: <strong>450 điểm</strong> (nhập học trước 2025) hoặc <strong>500 điểm</strong> (từ 2025 trở đi).</li>
            </ul>
        </div>

        <!-- Form upload -->
        <?php
        ViewHelper::printUploadForm($this->uploadItestResultForm, 'assessmentlearners.importITestResult');
        ?>

    </div>
</div>
