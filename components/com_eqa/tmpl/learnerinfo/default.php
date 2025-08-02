<?php


use Kma\Component\Eqa\Administrator\Helper\ViewHelper;

defined('_JEXEC') or die();

if(empty($this->user))
{
	echo '<div class="alert alert-danger">' . 'Error!' . '</div>';
    return;
}
/**
 * @var Joomla\CMS\User\User $user
 */
$user     = $this->user;
$username = $user->username ? htmlspecialchars($user->username) : '';
$email    = $user->email ? htmlspecialchars($user->email) : '';
$fullname = $user->name ? htmlspecialchars($user->name) : '';
?>
<div>
    Họ và tên: <strong><?php echo $fullname ?></strong><br/>
    Tên đăng nhập: <strong><?php echo $username ?></strong><br/>
    Email: <strong><?php echo $email ?></strong>
</div>
