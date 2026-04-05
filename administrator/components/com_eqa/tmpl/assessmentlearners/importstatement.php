<?php
defined('_JEXEC') or die();

/**
 * Template layout "importstatement" cho view SecondAttempts.
 *
 * Hiển thị form upload file Excel bản sao kê tài khoản ngân hàng MB Bank.
 * Sau khi submit, hệ thống tự đối chiếu payment_code và cập nhật trạng thái
 * payment_completed cho các thí sinh đã nộp phí.
 */


use Kma\Library\Kma\Helper\ViewHelper;
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-8">

        <!-- Hướng dẫn sử dụng -->
        <div class="alert alert-info mb-4">
            <h6 class="alert-heading">
                <span class="icon-info me-1" aria-hidden="true"></span>
                Hướng dẫn
            </h6>
            <ul class="mb-0 ps-3">
                <li>Xuất bản sao kê tài khoản ngân hàng theo định dạng Excel (.xlsx).</li>
                <li>Chọn file và nhấn nút <strong>"Đối chiếu &amp; Cập nhật"</strong> trên thanh công cụ.</li>
                <li>Hệ thống sẽ tìm kiếm <strong>mã thanh toán</strong> (8 ký tự) trong cột "Nội dung" của từng giao dịch.</li>
                <li>Chỉ các giao dịch có số tiền <strong>khớp chính xác</strong> với phí đăng ký mới được ghi nhận.</li>
                <li>Các trường hợp sai số tiền hoặc thanh toán 2 lần sẽ được <strong>báo cáo</strong> nhưng không tự động cập nhật.</li>
            </ul>
        </div>

        <!-- Form upload -->
        <?php ViewHelper::printUploadForm($this->uploadStatementForm, 'assessmentlearners.importStatement'); ?>

    </div>
</div>
