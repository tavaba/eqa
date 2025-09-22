<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

defined('_JEXEC') or die;

// Add Bootstrap CSS and JS (if not already included in your Joomla template)
JHtml::_('bootstrap.loadCss');
JHtml::_('bootstrap.framework');

?>
<div class="container">
    <div class="row justify-content-center align-items-center" >
        <div class="col-auto">
            <!-- Nav tabs -->
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab1-tab" data-bs-toggle="tab" data-bs-target="#tab1" type="button" role="tab" aria-controls="tab1" aria-selected="true">
                        <?php echo "Thông tin cơ sở"; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab2-tab" data-bs-toggle="tab" data-bs-target="#tab2" type="button" role="tab" aria-controls="tab2" aria-selected="false">
                        <?php echo "Đào tạo"; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab3-tab" data-bs-toggle="tab" data-bs-target="#tab3" type="button" role="tab" aria-controls="tab3" aria-selected="false">
                        <?php echo "Tổ chức thi"; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab4-tab" data-bs-toggle="tab" data-bs-target="#tab4" type="button" role="tab" aria-controls="tab4" aria-selected="false">
                        <?php echo "Chấm thi"; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab5-tab" data-bs-toggle="tab" data-bs-target="#tab5" type="button" role="tab" aria-controls="tab5" aria-selected="false">
                        <?php echo "Phúc khảo"; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab6-tab" data-bs-toggle="tab" data-bs-target="#tab6" type="button" role="tab" aria-controls="tab6" aria-selected="false">
			            <?php echo "Sửa sai"; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab7-tab" data-bs-toggle="tab" data-bs-target="#tab7" type="button" role="tab" aria-controls="tab6" aria-selected="false">
			            <?php echo "Thống kê"; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab8-tab" data-bs-toggle="tab" data-bs-target="#tab8" type="button" role="tab" aria-controls="tab6" aria-selected="false">
			            <?php echo "Đánh giá rèn luyện"; ?>
                    </button>
                </li>
            </ul>

            <!-- Tab panes -->
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="tab1" role="tabpanel" aria-labelledby="tab1-tab">
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=units'); ?>" class="btn btn-primary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_UNITS'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=employees'); ?>" class="btn btn-secondary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_EMPLOYEES'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=buildings'); ?>" class="btn btn-secondary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_BUILDINGS'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=rooms'); ?>" class="btn btn-secondary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_ROOMS'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=specialities'); ?>" class="btn btn-secondary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_SPECIALITIES'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=programs'); ?>" class="btn btn-secondary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_PROGRAMS'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=subjects'); ?>" class="btn btn-secondary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_SUBJECTS'); ?>
                        </a>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab2" role="tabpanel" aria-labelledby="tab2-tab">
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=academicyears'); ?>" class="btn btn-secondary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_ACADEMICYEARS'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=courses'); ?>" class="btn btn-secondary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_COURSES'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=groups'); ?>" class="btn btn-secondary btn-lg">
		                    <?php echo Text::_('COM_EQA_BUTTON_GROUPS'); ?>
                        </a>
                        <a href="<?php echo Route::_('index.php?option=com_eqa&view=cohorts'); ?>" class="btn btn-secondary btn-lg">
		                    <?php echo Text::_('Nhóm, phân lớp HVSV'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=learners'); ?>" class="btn btn-secondary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_LEARNERS'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=classes'); ?>" class="btn btn-secondary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_CLASSES'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=classes&layout=uploadpam'); ?>" class="btn btn-primary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_IMPORT_PAM'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=learners&layout=adddebtors'); ?>" class="btn btn-primary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_DEBTORS'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=stimulations'); ?>" class="btn btn-primary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_STIMULATION'); ?>
                        </a>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab3" role="tabpanel" aria-labelledby="tab3-tab">
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&task=examseasons.exportUnpassedExaminees'); ?>" class="btn btn-primary btn-lg">
		                    <?php echo 'Tải danh sách thi sinh thi lại và bảo lưu'; ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=examseasons'); ?>" class="btn btn-primary btn-lg">
		                    <?php echo Text::_('COM_EQA_BUTTON_EXAMSEASONS'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=exams'); ?>" class="btn btn-primary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_EXAMS'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=exam&layout=question'); ?>" class="btn btn-primary btn-lg">
                            <?php echo "Tiếp nhận đề thi"; ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=examsessions'); ?>" class="btn btn-primary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_EXAMSESSION'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=examrooms'); ?>" class="btn btn-primary btn-lg">
                            <?php echo Text::_('COM_EQA_BUTTON_EXAMROOM'); ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=examrooms&layout=import'); ?>" class="btn btn-primary btn-lg">
                            <?php echo "Nhập biên bản coi thi, coi thi-chấm thi"; ?>
                        </a>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab4" role="tabpanel" aria-labelledby="tab4-tab">
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=paperexams'); ?>" class="btn btn-primary btn-lg">
                            <?php echo "Thông tin các môn thi viết"; ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=paperexam&layout=masking'); ?>" class="btn btn-primary btn-lg">
                            <?php echo "Đánh phách, Dồn túi"; ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=paperexams&layout=uploadmarkbymask'); ?>" class="btn btn-primary btn-lg">
                            <?php echo "Nhập điểm chấm thi viết theo số phách"; ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=exam&layout=uploaditest'); ?>" class="btn btn-primary btn-lg">
                            <?php echo "Nhập điểm thi iTest"; ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=mmproductions'); ?>" class="btn btn-primary btn-lg">
                            <?php echo "Sản lượng chấm thi iTest"; ?>
                        </a>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab5" role="tabpanel" aria-labelledby="tab5-tab">
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=regradings'); ?>" class="btn btn-primary btn-lg">
		                    <?php echo "Quản lý yêu cầu phúc khảo"; ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=gradecorrections'); ?>" class="btn btn-primary btn-lg">
		                    <?php echo "Quản lý yêu cầu đính chính điểm"; ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=regradingresult&layout=uploadpaper'); ?>" class="btn btn-primary btn-lg">
		                    <?php echo "Nhập kết quả phúc khảo bài thi viết"; ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=regradingresult&layout=uploaditest'); ?>" class="btn btn-primary btn-lg">
		                    <?php echo "Nhập kết quả phúc khảo bài thi iTest"; ?>
                        </a>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab6" role="tabpanel" aria-labelledby="tab6-tab">
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=fixer&layout=fixpam'); ?>" class="btn btn-primary btn-lg">
				            <?php echo "Sửa điểm quá trình"; ?>
                        </a>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab7" role="tabpanel" aria-labelledby="tab7-tab">
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=monitoringexams'); ?>" class="btn btn-primary btn-lg">
				            <?php echo "Giám sát tiến độ môn thi"; ?>
                        </a>
                        <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=examseasons'); ?>" class="btn btn-primary btn-lg">
				            <?php echo "Xuất số liệu thống kê"; ?>
                        </a>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab8" role="tabpanel" aria-labelledby="tab8-tab">
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?php echo Route::_('index.php?option=com_eqa&view=conducts'); ?>" class="btn btn-primary btn-lg">
				            <?php echo "Kết quả rèn luyện"; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
