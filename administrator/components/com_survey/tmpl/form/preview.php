<?php
/**
 * @package     Survey Component
 * @subpackage  com_survey
 * @author      Your Name
 * @copyright   Copyright (C) 2024 Your Company. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

// Get the form item
$item = $this->item;
$surveyModel = !empty($item->model) ? $item->model : '{}';
?>

<!-- SurveyJS Form Container -->
<div id="surveyContainer"></div>

<script>
    const surveyJson = <?php echo $surveyModel; ?>;
    const survey = new Survey.Model(surveyJson);
    survey.locale="vi";

    function alertResults (sender) {
        const results = JSON.stringify(sender.data);
        alert(results);
    }

    survey.onComplete.add(alertResults);

    document.addEventListener("DOMContentLoaded", function() {
        survey.render(document.getElementById("surveyContainer"));
    });
</script>