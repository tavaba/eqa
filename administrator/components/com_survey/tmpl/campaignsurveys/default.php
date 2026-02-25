<?php

use Kma\Library\Kma\Helper\ViewHelper;

defined('_JEXEC') or die();
$campaign = $this->item;
?>
<div class="accordion">
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> <h2>Thông tin đợt khảo sát</h2> </button></h2>
        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
            <div class="accordion-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td style="white-space: nowrap; width: 1%;">Tên gọi:</td>
                        <td><?php echo htmlspecialchars($campaign->title);?></td>
                    </tr>
                    <tr>
                        <td style="white-space: nowrap; width: 1%;">Thời gian bắt đầu:</td>
                        <td><?php echo htmlspecialchars($campaign->start_time);?></td>
                    </tr>
                    <tr>
                        <td style="white-space: nowrap; width: 1%;">Thời gian kết thúc:</td>
                        <td><?php echo htmlspecialchars($campaign->end_time);?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
ViewHelper::printItemsDefaultLayout($this->getLayoutData(), $this->getListLayoutItemFields());
