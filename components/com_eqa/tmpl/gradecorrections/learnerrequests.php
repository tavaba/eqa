<?php

use Kma\Component\Eqa\Site\Helper\ViewHelper;

defined('_JEXEC') or die();

if(empty($this->learner))
{
	echo 'Cần đăng nhập bằng tài khoản HVSV để xem nội dung trang này';
	return;
}
?>

<?php
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
