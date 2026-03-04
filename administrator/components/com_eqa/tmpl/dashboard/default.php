<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

defined('_JEXEC') or die;

JHtml::_('bootstrap.framework');

?>
<style>
    .btn {
        text-align: left !important;
    }
</style>
<div class="container my-5">
    <div class="card shadow-sm rounded-4 border-0">

        <div class="card-body">
            <!-- Nav tabs -->
            <ul class="nav nav-tabs mb-4" id="mainTab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="tab1-tab" data-bs-toggle="tab" data-bs-target="#tab1" type="button" role="tab">
                        <i class="fas fa-university me-1"></i> Thông tin cơ sở
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab2-tab" data-bs-toggle="tab" data-bs-target="#tab2" type="button" role="tab">
                        <i class="fas fa-book-open me-1"></i> Đào tạo
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab3-tab" data-bs-toggle="tab" data-bs-target="#tab3" type="button" role="tab">
                        <i class="fas fa-clipboard-check me-1"></i> Tổ chức thi
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab4-tab" data-bs-toggle="tab" data-bs-target="#tab4" type="button" role="tab">
                        <i class="fas fa-pen-alt me-1"></i> Chấm thi
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab5-tab" data-bs-toggle="tab" data-bs-target="#tab5" type="button" role="tab">
                        <i class="fas fa-search me-1"></i> Phúc khảo, Đính chính
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab8-tab" data-bs-toggle="tab" data-bs-target="#tab8" type="button" role="tab">
                        <i class="fas fa-user-graduate me-1"></i> Đánh giá rèn luyện
                    </button>
                </li>
            </ul>

            <!-- Tab contents -->
            <div class="tab-content" id="mainTabContent">

                <!-- ===== Thông tin cơ sở ===== -->
                <div class="tab-pane fade show active" id="tab1" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=units'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-building me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_UNITS'); ?>
                            </a>
                        </div>
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=employees'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-users me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_EMPLOYEES'); ?>
                            </a>
                        </div>
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=buildings'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-city me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_BUILDINGS'); ?>
                            </a>
                        </div>
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=rooms'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-door-open me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_ROOMS'); ?>
                            </a>
                        </div>
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=specialities'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-graduation-cap me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_SPECIALITIES'); ?>
                            </a>
                        </div>
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=programs'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-scroll me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_PROGRAMS'); ?>
                            </a>
                        </div>
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=subjects'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-book me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_SUBJECTS'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ===== Đào tạo ===== -->
                <div class="tab-pane fade" id="tab2" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=academicyears'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-calendar-alt me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_ACADEMICYEARS'); ?>
                            </a>
                        </div>
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=courses'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-layer-group me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_COURSES'); ?>
                            </a>
                        </div>
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=groups'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-users me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_GROUPS'); ?>
                            </a>
                        </div>
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=cohorts'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-chalkboard-teacher me-1"></i> Nhóm, phân lớp HVSV
                            </a>
                        </div>
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=learners'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-user-graduate me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_LEARNERS'); ?>
                            </a>
                        </div>
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=classes'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-chalkboard me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_CLASSES'); ?>
                            </a>
                        </div>
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=classes&layout=uploadpam'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-file-import me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_IMPORT_PAM'); ?>
                            </a>
                        </div>
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=learners&layout=adddebtors'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-user-clock me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_DEBTORS'); ?>
                            </a>
                        </div>
                        <div class="col-md-4 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=stimulations'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-award me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_STIMULATION'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ===== Tổ chức thi ===== -->
                <div class="tab-pane fade" id="tab3" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=SecondAttempts'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-file-alt me-1"></i> Danh sách thi lần 2
                            </a>
                        </div>
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=examseasons'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-calendar-check me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_EXAMSEASONS'); ?>
                            </a>
                        </div>
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=exams'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-file-alt me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_EXAMS'); ?>
                            </a>
                        </div>
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=exam&layout=question'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-question-circle me-1"></i> Tiếp nhận đề thi
                            </a>
                        </div>
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=examsessions'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-clock me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_EXAMSESSION'); ?>
                            </a>
                        </div>
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=examrooms'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-door-closed me-1"></i> <?php echo Text::_('COM_EQA_BUTTON_EXAMROOM'); ?>
                            </a>
                        </div>
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=examrooms&layout=import'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-upload me-1"></i> Nhập biên bản coi thi, coi thi-chấm thi
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ===== Chấm thi ===== -->
                <div class="tab-pane fade" id="tab4" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=paperexams'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-file-lines me-1"></i> Thông tin các môn thi viết
                            </a>
                        </div>
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=paperexam&layout=masking'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-mask me-1"></i> Đánh phách, Dồn túi
                            </a>
                        </div>
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=paperexams&layout=uploadmarkbymask'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-upload me-1"></i> Nhập điểm chấm thi viết theo số phách
                            </a>
                        </div>
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=exam&layout=uploaditest'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-keyboard me-1"></i> Nhập điểm thi iTest
                            </a>
                        </div>
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=mmproductions'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-database me-1"></i> Sản lượng chấm thi iTest
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ===== Phúc khảo ===== -->
                <div class="tab-pane fade" id="tab5" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=regradings'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-redo me-1"></i> Quản lý yêu cầu phúc khảo
                            </a>
                        </div>
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=gradecorrections'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-pencil-alt me-1"></i> Quản lý yêu cầu đính chính điểm
                            </a>
                        </div>
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=regradingresult&layout=uploadpaper'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-upload me-1"></i> Nhập kết quả phúc khảo bài thi viết
                            </a>
                        </div>
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=regradingresult&layout=uploaditest'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-upload me-1"></i> Nhập kết quả phúc khảo bài thi iTest
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ===== Đánh giá rèn luyện ===== -->
                <div class="tab-pane fade" id="tab8" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-6 d-grid">
                            <a href="<?php echo Route::_('index.php?option=com_eqa&view=conducts'); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-user-check me-1"></i> Kết quả rèn luyện
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
