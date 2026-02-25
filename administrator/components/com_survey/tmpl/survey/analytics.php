<?php

use Joomla\CMS\Router\Route;

defined('_JEXEC') or die;
$survey = $this->item;
$campaign = $this->campaign;
$dataUrl = Route::_('index.php?option=com_survey&task=survey.dashboardDataJson&id='.$survey->id,false)
?>
<style>
    .data-loading-indicator-panel {
        width: 100%;
    }
    .data-loading-indicator {
        position: relative;
        width: 64px;
        height: 64px;
        left: calc((100% - 64px)/ 2);
        top: calc((100% - 64px)/ 2);
        animation: data-loading-indicator-spinner 1s infinite linear;
    }
    @keyframes data-loading-indicator-spinner {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(359deg);
        }
    }

    /* Print mode - ẩn các thành phần không cần thiết */
    body.print-mode #header,
    body.print-mode #sidebar-wrapper,
    body.print-mode #subheader-container,
    body.print-mode .header,
    body.print-mode .subhead,
    body.print-mode #toolbar,
    body.print-mode .toolbar,
    body.print-mode .page-title {
        display: none !important;
    }

    body.print-mode {
        background: white !important;
    }

    body.print-mode .survey-analytics-wrapper {
        padding: 0 !important;
        margin: 0 !important;
    }
</style>
<div id="loadingIndicator" class="data-loading-indicator-panel">
    <div class="data-loading-indicator">
        <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <g clip-path="url(#clip0_17928_11482)">
                <path d="M32 64C14.36 64 0 49.65 0 32C0 14.35 14.36 0 32 0C49.64 0 64 14.35 64 32C64 49.65 49.64 64 32 64ZM32 4C16.56 4 4 16.56 4 32C4 47.44 16.56 60 32 60C47.44 60 60 47.44 60 32C60 16.56 47.44 4 32 4Z" fill="#E5E5E5" />
                <path d="M53.2101 55.2104C52.7001 55.2104 52.1901 55.0104 51.8001 54.6204C51.0201 53.8404 51.0201 52.5704 51.8001 51.7904C57.0901 46.5004 60.0001 39.4704 60.0001 31.9904C60.0001 24.5104 57.0901 17.4804 51.8001 12.1904C51.0201 11.4104 51.0201 10.1404 51.8001 9.36039C52.5801 8.58039 53.8501 8.58039 54.6301 9.36039C60.6701 15.4004 64.0001 23.4404 64.0001 31.9904C64.0001 40.5404 60.6701 48.5704 54.6301 54.6204C54.2401 55.0104 53.7301 55.2104 53.2201 55.2104H53.2101Z" fill="#19B394" />
            </g>
            <defs>
                <clipPath id="clip0_17928_11482">
                    <rect width="64" height="64" fill="white" />
                </clipPath>
            </defs>
        </svg>
    </div>
</div>
<div id="title-block" class="text-center" style="margin-top:20px;">
    <h3 id="title">THỐNG KÊ KẾT QUẢ KHẢO SÁT</h3>
    <div id="survey-title"><?php echo $survey->title;?></div>
    <?php if($campaign): ?>
        <div id="campaign-title"><?php echo '('.$campaign->title.')'; ?></div>
    <?php endif;?>
</div>
<div class="survey-analytics-wrapper">
    <!-- Dashboard Container -->
    <div id="surveyDashboardContainer"></div>
</div>

<script>
    let isPrintMode = false;
    const STORAGE_KEY = 'survey_print_mode_no_confirm';

    function showPrintModeConfirm() {
        return new Promise((resolve) => {
            // Tạo overlay
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            `;

            // Tạo dialog
            const dialog = document.createElement('div');
            dialog.style.cssText = `
                background: white;
                padding: 2rem;
                border-radius: 0.5rem;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                max-width: 500px;
                text-align: center;
            `;

            dialog.innerHTML = `
                <h4 style="margin-top: 0; margin-bottom: 1rem;">Xác nhận</h4>
                <p style="margin-bottom: 2rem;">Chức năng này sẽ ẩn khỏi giao diện các thành phần như
                    sidebar, header và footer... của trang web, chỉ giữ lại nội dung chính.
                    Hãy sử dụng công cụ như GoFullPage (một extension dành cho Chrome) để
                    xuất nội dung ra PDF/PNG/JPEG. Sau đó, bấm phím ESC để trở lại giao diện bình thường.</p>
                <div style="display: flex; gap: 0.5rem; justify-content: center;">
                    <button id="confirm-ok" class="btn btn-primary">OK</button>
                    <button id="confirm-cancel" class="btn btn-secondary">Cancel</button>
                    <button id="confirm-no-show" class="btn btn-warning">Dừng hiển thị</button>
                </div>
            `;

            overlay.appendChild(dialog);
            document.body.appendChild(overlay);

            // Xử lý các nút
            document.getElementById('confirm-ok').onclick = () => {
                document.body.removeChild(overlay);
                resolve('ok');
            };

            document.getElementById('confirm-cancel').onclick = () => {
                document.body.removeChild(overlay);
                resolve('cancel');
            };

            document.getElementById('confirm-no-show').onclick = () => {
                localStorage.setItem(STORAGE_KEY, 'true');
                document.body.removeChild(overlay);
                resolve('ok');
            };

            // ESC để đóng dialog
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    document.body.removeChild(overlay);
                    document.removeEventListener('keydown', escHandler);
                    resolve('cancel');
                }
            };
            document.addEventListener('keydown', escHandler);
        });
    }

    async function togglePrintMode() {
        if (!isPrintMode) {
            // Kiểm tra xem user đã chọn "Dừng hiển thị" chưa
            const noConfirm = localStorage.getItem(STORAGE_KEY);

            let proceed = true;
            if (!noConfirm) {
                const result = await showPrintModeConfirm();
                proceed = (result === 'ok');
            }

            if (proceed) {
                isPrintMode = true;
                document.body.classList.add('print-mode');
            }
        } else {
            // Thoát print mode
            isPrintMode = false;
            document.body.classList.remove('print-mode');
        }
    }

    window.togglePrintMode = togglePrintMode;

    function setupPrintModeHandlers() {
        // Xử lý phím ESC để thoát print mode
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isPrintMode) {
                e.preventDefault();
                togglePrintMode();
            }
        });
    }

    async function loadDashboard() {
        const url = "<?php echo $dataUrl;?>"
        const response = await fetch(url);
        const payload = await response.json();
        const data = payload.data;
        const survey = new Survey.Model(data.model);
        const surveyQuestions = survey.getAllQuestions();
        const responses = data.responses;

        const vizPanel = new SurveyAnalytics.VisualizationPanel(
            surveyQuestions,
            responses,
            {
                allowHideQuestions: true
            }
        );
        vizPanel.render("surveyDashboardContainer");
        document.getElementById("loadingIndicator").style.display = "none";
        const licenseNotice = document.getElementsByClassName("sa-commercial")[0];
        licenseNotice.style.display = "none";
        const toolbarButtons = document.getElementsByClassName("sa-toolbar__button");
        for (let i=0; i<toolbarButtons.length;i++) {
            if(toolbarButtons[i].innerHTML==="Reset Filter")
                toolbarButtons[i].style.display = "none";
        }

        //Print mode
        setupPrintModeHandlers();
    }
    loadDashboard();
</script>