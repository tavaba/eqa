<?php
defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;

?>
    <div class="accordion">
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> Hướng dẫn tạo lớp học phần </button></h2>
            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                <div class="accordion-body">
                </div>
            </div>
        </div>
    </div>
<hr/>
<?php
ViewHelper::printForm($this->form,'basic');