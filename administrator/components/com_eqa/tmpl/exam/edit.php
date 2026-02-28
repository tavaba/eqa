<?php
/**
 * @package     Com_Eqa
 * @subpackage  tmpl/exam
 *
 * @copyright   (C) 2025 KMA
 * @license     GNU General Public License version 2 or later
 */

use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Helper\ViewHelper;

defined('_JEXEC') or die();

// Render the standard edit form
ViewHelper::printItemEditForm($this->form, $this->item->id);

// Build the AJAX URL for fetching subject info
$ajaxUrl = Route::_('index.php?option=com_eqa&task=exam.getJsonSubjectInfo', false);
?>
<script>
(function () {
    'use strict';

    /**
     * Set value for a plain <input> or <textarea> field.
     *
     * @param {string} fieldId   Element id (e.g. 'jform_code')
     * @param {*}      value
     */
    function setInputValue(fieldId, value) {
        const el = document.getElementById(fieldId);
        if (el) {
            el.value = (value !== null && value !== undefined) ? value : '';
        }
    }

    /**
     * Set value for a plain <select> (single-select).
     * Falls back gracefully when the option does not exist.
     *
     * @param {string}          fieldId
     * @param {string|number}   value
     */
    function setSelectValue(fieldId, value) {
        const el = document.getElementById(fieldId);
        if (!el) { return; }

        const strVal = String(value);
        // Try to find the matching option
        const matched = Array.from(el.options).some(function (opt) {
            if (opt.value === strVal) {
                opt.selected = true;
                return true;
            }
            return false;
        });

        if (!matched && el.options.length > 0) {
            // Value not found — leave current selection as-is (safer than forcing)
            console.warn('[EQA] setSelectValue: option "' + strVal + '" not found in #' + fieldId);
        }

        triggerSelect2Change(el);
    }

    /**
     * Set value for a radio-button group rendered by Joomla (btn-group-yesno or similar).
     * Joomla renders radio groups as:
     *   <input type="radio" id="jform_FIELD0" name="jform[FIELD]" value="0">
     *   <input type="radio" id="jform_FIELD1" name="jform[FIELD]" value="1">
     *
     * @param {string}        fieldName  The form field name WITHOUT jform prefix, e.g. 'is_pass_fail'
     * @param {string|number} value      The value to select
     */
    function setRadioValue(fieldName, value) {
        const strVal = String(value);
        const radios = document.querySelectorAll('input[type="radio"][name="jform[' + fieldName + ']"]');
        radios.forEach(function (radio) {
            radio.checked = (radio.value === strVal);
        });
    }

    /**
     * Update the allowed_rooms multi-select field.
     *
     * @param {Array|null} roomIds  Array of integer room IDs, or null to clear all.
     */
    function setAllowedRooms(roomIds) {
        const select = document.getElementById('jform_allowed_rooms');
        if (!select) { return; }

        // Deselect all options first
        Array.from(select.options).forEach(function (opt) {
            opt.selected = false;
        });

        if (roomIds && roomIds.length > 0) {
            const idSet = new Set(roomIds.map(Number));
            Array.from(select.options).forEach(function (opt) {
                if (idSet.has(Number(opt.value))) {
                    opt.selected = true;
                }
            });
        }
        // null / empty → tất cả bỏ chọn, tức là "không giới hạn phòng"

        triggerSelect2Change(select);
    }

    /**
     * Notify Select2 (if present) that the underlying <select> has changed.
     *
     * @param {HTMLElement} el
     */
    function triggerSelect2Change(el) {
        if (window.jQuery && window.jQuery(el).data('select2')) {
            window.jQuery(el).trigger('change');
        }
    }

    /**
     * Fetch subject info from the server and auto-fill all relevant form fields.
     *
     * @param {string|number} subjectId
     */
    function autofillFromSubject(subjectId) {
        if (!subjectId) { return; }

        const url = '<?php echo $ajaxUrl; ?>'
            + '&subject_id=' + encodeURIComponent(subjectId);

        fetch(url, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function (json) {
            if (json.error) {
                console.warn('[EQA] getJsonSubjectInfo error:', json.message);
                return;
            }

            const p = json.data; // payload

            // --- Text / number inputs ---
            setInputValue('jform_code',     p.code);
            setInputValue('jform_name',     p.name);
            setInputValue('jform_duration', p.duration);
            setInputValue('jform_kmonitor', p.kmonitor);
            setInputValue('jform_kassess',  p.kassess);

            // --- Single-select fields (Joomla list fields) ---
            setSelectValue('jform_testtype', p.testtype);

            // --- Radio-button groups (Joomla btn-group-yesno) ---
            setRadioValue('is_pass_fail', p.is_pass_fail);
            setRadioValue('usetestbank',  p.usetestbank);

            // --- Multi-select: allowed_rooms ---
            setAllowedRooms(p.allowed_rooms);
        })
        .catch(function (err) {
            console.error('[EQA] Failed to fetch subject info:', err);
        });
    }

    // ---------------------------------------------------------------
    // Attach event listener once the DOM is ready
    // ---------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        const subjectSelect = document.getElementById('jform_subject_id');
        if (!subjectSelect) { return; }

        // Native change (plain <select>)
        subjectSelect.addEventListener('change', function () {
            autofillFromSubject(this.value);
        });

        // Select2 events (Select2 sometimes swallows the native 'change')
        if (window.jQuery) {
            window.jQuery(subjectSelect).on('select2:select select2:clear', function () {
                autofillFromSubject(this.value);
            });
        }
    });
}());
</script>
