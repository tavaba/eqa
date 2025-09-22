<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;

//Preprocessing
$samplePath = 'media/com_eqa/xlsx/sample_conduct_performance.xlsx';
$sampleUrl = JUri::root().$samplePath;
?>
<div class="accordion">
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingOne"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne"> Hướng dẫn sử dụng </button></h2>
        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
            <div class="accordion-body">
                File mẫu ở đây: <a href="<?php echo $sampleUrl;?>">sample_conduct_performance.xlsx</a><br/>
                Yêu cầu về file được upload<br/>
                <ol>
                    <li>Có thể upload 1 hoặc nhiều file cùng lúc</li>
                    <li>Một file có thể có 1 hoặc nhiều worksheet</li>
                    <li>Thứ tự các cột phải đúng như trong file mẫu</li>
                    <li>Quan trọng là cột <b>Mã HVSV</b>, cột <b>Họ và tên</b> không quan trọng</li>
                </ol>
            </div>
        </div>
    </div>
</div>
<div></div>
<hr/>
<?php
ViewHelper::printItemsUploadForm($this);