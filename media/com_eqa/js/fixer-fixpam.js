/**
 * @package     com_eqa
 * @subpackage  com_eqa.site
 * @copyright   Copyright (C) 2024. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

(function($) {
    'use strict';

    /**
     * Initialize the fixer fixpam functionality
     */
    $(document).ready(function() {
        // Initialize the class selector change handler
        initClassSelector();
    });

    /**
     * Initialize class selector functionality
     */
    function initClassSelector() {
        var $classSelect = $('#jform_class_id');
        var $learnerSelect = $('#jform_learner_id');

        if ($classSelect.length && $learnerSelect.length) {
            // Handle class selection change
            $classSelect.on('change', function() {
                var classId = $(this).val();

                if (classId) {
                    loadClassLearners(classId, $learnerSelect);
                } else {
                    clearLearnerSelect($learnerSelect);
                }
            });

            // Clear learner select on page load if no class is selected
            if (!$classSelect.val()) {
                clearLearnerSelect($learnerSelect);
            }
        }
    }

    /**
     * Load learners for the selected class via AJAX
     *
     * @param {int} classId The selected class ID
     * @param {jQuery} $learnerSelect The learner select element
     */
    function loadClassLearners(classId, $learnerSelect) {
        // Show loading state
        $learnerSelect.prop('disabled', true);
        clearLearnerSelect($learnerSelect);
        addLoadingOption($learnerSelect);

        // Get the CSRF token
        var token = $('input[name="' + Joomla.getOptions('csrf.token') + '"]').val() || '1';

        // Prepare AJAX data
        var ajaxData = {
            class_id: classId,
            format: 'json'
        };
        ajaxData[Joomla.getOptions('csrf.token')] = token;

        // Make AJAX request
        $.ajax({
            url: COM_EQA_AJAX_URL,
            type: 'POST',
            data: ajaxData,
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                handleLearnersSuccess(response, $learnerSelect);
            },
            error: function(xhr, status, error) {
                handleLearnersError(xhr, status, error, $learnerSelect);
            }
        });
    }

    /**
     * Handle successful learners loading
     *
     * @param {Object} response The AJAX response
     * @param {jQuery} $learnerSelect The learner select element
     */
    function handleLearnersSuccess(response, $learnerSelect) {
        // Clear loading state
        $learnerSelect.prop('disabled', false);
        clearLearnerSelect($learnerSelect);

        if (response.success && response.data && response.data.length > 0) {
            // Add default option
            $learnerSelect.append('<option value="">' + Joomla.Text._('COM_EQA_SELECT_LEARNER', 'Select a learner') + '</option>');

            // Add learner options - use 'id' and 'name' from the actual response structure
            $.each(response.data, function(index, learner) {
                $learnerSelect.append('<option value="' + learner.id + '">' + escapeHtml(learner.name) + '</option>');
            });
        } else {
            // No learners found
            $learnerSelect.append('<option value="">' + Joomla.Text._('COM_EQA_NO_LEARNERS_FOUND', 'No learners found for this class') + '</option>');
        }
    }

    /**
     * Handle learners loading error
     *
     * @param {Object} xhr The XMLHttpRequest object
     * @param {string} status The status of the request
     * @param {string} error The error message
     * @param {jQuery} $learnerSelect The learner select element
     */
    function handleLearnersError(xhr, status, error, $learnerSelect) {
        console.error('AJAX Error:', status, error);

        // Clear loading state
        $learnerSelect.prop('disabled', false);
        clearLearnerSelect($learnerSelect);

        // Add error option
        $learnerSelect.append('<option value="">' + Joomla.Text._('COM_EQA_ERROR_LOADING_LEARNERS', 'Error loading learners') + '</option>');

        // Show error message
        var errorMessage = Joomla.Text._('COM_EQA_ERROR_LOADING_LEARNERS', 'Error loading learners. Please try again.');
        showMessage(errorMessage, 'error');
    }

    /**
     * Clear learner select options
     *
     * @param {jQuery} $learnerSelect The learner select element
     */
    function clearLearnerSelect($learnerSelect) {
        $learnerSelect.empty();
    }

    /**
     * Add loading option to learner select
     *
     * @param {jQuery} $learnerSelect The learner select element
     */
    function addLoadingOption($learnerSelect) {
        $learnerSelect.append('<option value="">' + Joomla.Text._('COM_EQA_LOADING', 'Loading...') + '</option>');
    }

    /**
     * Show message to user
     *
     * @param {string} message The message to show
     * @param {string} type The message type (success, error, info, warning)
     */
    function showMessage(message, type) {
        // Use Joomla's message system if available
        if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
            var messages = {};
            messages[type] = [message];
            Joomla.renderMessages(messages);
        } else {
            // Fallback to console
            console.log(type.toUpperCase() + ': ' + message);
        }
    }

    /**
     * Escape HTML characters to prevent XSS
     *
     * @param {string} text The text to escape
     * @returns {string} The escaped text
     */
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

})(jQuery);