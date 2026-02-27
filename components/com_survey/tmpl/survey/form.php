<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

defined('_JEXEC') or die;
$respondentToken = $this->token;
$surveyId = $this->item->id;
$surveyFormJson = $this->surveyFormJson;
if(empty($respondentToken))
    $submitUrl = Route::_('index.php?option=com_survey&layout=form&id='.$surveyId);
else
    $submitUrl = Route::_('index.php?option=com_survey&layout=form&token='.$respondentToken);
?>
<!-- This is the place where SURVEY FORM will be rendered -->
<div id="surveyContainer"></div>

<!-- This form is used by the function 'httpSaveResults' -->
<form id="surveyForm" method="post" action="<?php echo $submitUrl; ?>">
    <input type="hidden" name="task" value="survey.respond">
    <input type="hidden" name="response" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
    const surveyJson = <?php echo $surveyFormJson; ?>;
    const survey = new Survey.Model(surveyJson);
    survey.locale="vi";
    document.addEventListener("DOMContentLoaded", renderSurvey);
    survey.onComplete.add(httpSaveResults);

    /*
     * Implementation of the mentioned methods.
     */
    function renderSurvey() {
        survey.render(document.getElementById("surveyContainer"));
    }
    function ajaxSaveResults(sender, options) {
        // Display the "Saving..." message (pass a string value to display a custom message)
        options.showSaveInProgress();
        const token = $("#csrf_token").attr("name");
        const xhr = new XMLHttpRequest();
        let feedback = JSON.stringify(sender.data);
        let data = [token] + "=1&answer="+feedback;
        xhr.open("POST", "<?php echo $submitUrl; ?>");
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onload = xhr.onerror = function () {
            if (xhr.status == 200) {
                // Display the "Success" message (pass a string value to display a custom message)
                options.showSaveSuccess(xhr.response);
                // Alternatively, you can clear all messages:
                // options.clearSaveMessages();
            } else {
                // Display the "Error" message (pass a string value to display a custom message)
                options.showSaveError();
            }
        };
        xhr.send(data);
    }
    function httpSaveResults(sender) {
        setTimeout(function (){
            // Put response into hidden field
            document.querySelector('#surveyForm input[name="response"]').value =
                JSON.stringify(sender.data);

            // Submit the form
            document.getElementById('surveyForm').submit();
        },
        2000);
    }
</script>