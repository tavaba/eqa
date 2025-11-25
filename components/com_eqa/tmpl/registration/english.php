<?php

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;

defined('_JEXEC') or die();

/**
 * @var CMSApplication $app
 */
$app = Factory::getApplication();
$menu = $app->getMenu();
$active = $menu->getActive();
if(!$active)
    die('Truy vấn không hợp lệ');

$params = $active->getParams();
$examTitle = $params->get('title');
$examDate = $params->get('exam_date');
$enabled = $params->get('enabled');
$bank = $params->get('bank');
$account = $params->get('account_number');
$prefix = $params->get('prefix');
$deadline = $params->get('deadline');
$fee = $params->get('fee');
$deadlineOver = strtotime($deadline) < time();
$info = $prefix.$this->learnerCode;
if(!$this->user->guest)
{
	echo '<b>Thông tin tài khoản</b><br/>';
	echo '<table>';
	echo '<tr><td>Tên:</td><td>'.$this->user->name.'</td></tr>';
	echo '<tr><td>Username:</td><td>'.$this->user->username.'</td></tr>';
	echo '<tr><td>E-mail:</td><td>'.$this->user->email. '</td></tr>';
	echo '</table>';
}

if(!$enabled || $deadlineOver)
{
	echo 'Không có kỳ thi để đăng k hoặc đã hết hạn đăng ký';
	return;
}


if(empty($this->learnerCode) && !$app->getIdentity()->authorise('eqa.supervise','com_eqa'))
{
	echo 'Cần đăng nhập bằng tài khoản HVSV hoặc của cán bộ quản lý để sử dụng chức năng này';
	return;
}

if(empty($this->learnerCode))
{
	$info = $prefix.'LEARNERCODE';
}
$qrUrl = "https://img.vietqr.io/image/{$bank}-{$account}-compact2.png?amount={$fee}&addInfo=" . urlencode($info);
?>
<b>Hướng dẫn đăng ký sát hạch tiếng Anh</b>
<ol>
    <li>Kỳ thi: <b><?php echo htmlspecialchars($examTitle);?></b></li>
    <li>Thời gian sát hạch: <?php echo htmlspecialchars($examDate); ?> </li>
	<li>Thời hạn đăng ký: <?php echo $deadline; ?></li>
    <li>Phí sát hạch: <?php echo number_format($fee, 0, ',', '.') . ' VNĐ (có thể thay đổi theo từng đợt thi)';?></li>
	<li>HVSV sử dụng ứng dụng ngân hàng điện tử của mình quét mã QR dưới đây để nộp
        phí sát hạch; việc nộp phí (thành công) được coi là đăng ký sát hạch.
		Khoản phí này sẽ không được hoàn lại trong bất kỳ trường hợp nào.
        Lưu ý: mã QR này được cá nhân hóa cho từng HVSV, không thể dùng chung.</li>
	<li>Hết hạn đăng ký, Phòng KT&amp;ĐBCLĐT sẽ tổng hợp danh sách và công bố cho thí sinh biết.</li>
</ol>

<div class="text-center">
	<img src='<?php echo $qrUrl; ?>' alt='VietQR' />";
</div>
