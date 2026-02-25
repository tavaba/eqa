<?php
/**
 * @package     Survey Component
 * @subpackage  com_survey
 * @author      Your Name
 * @copyright   Copyright (C) 2024 Your Company. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

// Get the form item
$item = $this->item;
$formId = $item->id;
$currentModel = !empty($item->model) ? $item->model : '{}';

HTMLHelper::_('behavior.keepalive');
HTMLHelper::_('form.token');
?>

<form action="<?php echo htmlspecialchars(Factory::getApplication()->input->server->get('REQUEST_URI', '', 'string')); ?>"
      method="post" name="adminForm" id="adminForm" class="form-validate">

    <div class="card">
        <div class="card-body bg-primary-subtle">
            <fieldset class="border-0 m-0 p-0">
                <legend ><?php echo 'Thiết kế mẫu phiếu khảo sát: <b>', $this->escape($item->title),'</b>'; ?></legend>
            </fieldset>
        </div>
    </div>
    <div id="surveyBuilder" class="border rounded" style="height: 300vh; min-height: 700px;"></div>

    <!-- Hidden fields for Joomla form handling -->
    <input type="hidden" name="id" value="<?php echo $formId; ?>" />
    <input type="hidden" name="model" id="survey-model-data" value="" />
    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        /*
         * Initialize SurveyJS Form Builder.
         * The 'questionTypes' array defines which types of questions
         * are available in the SurveyJS Creator. This list must match the
         * supported question types defined in the 'SurveyQuestionType' class.
         */
        const builderOptions = {
            showLogicTab: true,
            showTranslationTab: true,
            showPreviewTab: true,
            showJSONEditorTab: true,
            showTestSurveyTab: true,
            allowModifyPages: true,
            questionTypes: [
                "text",
                "comment",
                "checkbox",
                "radiogroup",
                "dropdown",
                "tagbox",
                "imagepicker",
                "boolean",
                "slider",
                "rating",
                "ranking",
                "matrix",
                "panel"
            ]
        };

        // Parse the current model
        let currentSurveyModel = {};
        try {
            currentSurveyModel = JSON.parse(<?php echo json_encode($currentModel); ?>);
        } catch (e) {
            console.warn('Invalid JSON model, using empty model');
            currentSurveyModel = {
                pages: [{
                    name: "page1",
                    elements: []
                }]
            };
        }

        // Create the form builder
        SurveyCreator.localization.currentLocale = "ru";
        const builder = new SurveyCreator.SurveyCreator(builderOptions);

        // Set the current model
        if (Object.keys(currentSurveyModel).length > 0) {
            builder.JSON = currentSurveyModel;
        }

        // Render the builder
        builder.render("surveyBuilder");

        // Store builder reference globally for toolbar access
        window.surveyBuilder = builder;
        window.originalModel = <?php echo json_encode($currentModel); ?>;

        //Hide the license prompt
        const licenseNotice = document.getElementsByClassName('svc-creator__banner')[0];
        licenseNotice.style.display = 'none';
    });

    // Override Joomla's submit task function to handle survey model data
    Joomla.submitbutton = function(task) {
        if (task === 'form.saveModel' || task === 'form.applyModel') {
            // Get the survey model from the builder
            if (window.surveyBuilder) {
                const surveyJSON = window.surveyBuilder.JSON;

                // Set the model data in the hidden field
                document.getElementById('survey-model-data').value = JSON.stringify(surveyJSON);
            }
        }

        // Submit the form using Joomla's standard method
        Joomla.submitform(task, document.getElementById('adminForm'));
    };

</script>