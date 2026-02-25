<?php
defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die;

$links = [
    ['url' => Route::_('index.php?option=com_survey&view=respondents'), 'label' => 'Người được khảo sát', 'icon' => 'fa fa-user'],
   ['url' => Route::_('index.php?option=com_survey&view=units'), 'label' => 'Đơn vị, Tổ chức', 'icon' => 'fa fa-cubes'],
    ['url' => Route::_('index.php?option=com_survey&view=respondentgroups'), 'label' => 'Nhóm khảo sát', 'icon' => 'fa fa-users-cog'],
    ['url' => Route::_('index.php?option=com_survey&view=classes'), 'label' => 'Lớp học phần', 'icon' => 'fa fa-users'],
    ['url' => Route::_('index.php?option=com_survey&view=topics'), 'label' => 'Chủ đề khảo sát', 'icon' => 'fa fa-list'],
    ['url' => Route::_('index.php?option=com_survey&view=forms'), 'label' => 'Phiếu khảo sát', 'icon' => 'fa fa-wpforms'],
    ['url' => Route::_('index.php?option=com_survey&view=campaigns'), 'label' => 'Đợt khảo sát', 'icon' => 'fa fa-bullhorn'],
    ['url' => Route::_('index.php?option=com_survey&view=surveys'), 'label' => 'Cuộc khảo sát', 'icon' => 'fa fa-poll'],
    ['url' => Route::_('index.php?option=com_survey&view=logs'), 'label' => 'Logs', 'icon' => 'fa fa-file-alt'],
];
?>

<div class="container mt-4">
    <div class="row g-3">
        <?php foreach ($links as $link): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="<?php echo $link['url']; ?>" class="text-decoration-none">
                    <div class="card shadow-sm h-100 text-center">
                        <div class="card-body d-flex flex-column justify-content-center align-items-center">
                            <i class="<?php echo $link['icon']; ?> fa-2x mb-2 text-primary"></i>
                            <h6 class="card-title mb-0"><?php echo Text::_($link['label']); ?></h6>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
