/**
 * @package     Joomla Component
 * @subpackage  Dependent Lists Utility
 * @copyright   Copyright (C) 2024. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

(function($) {
    'use strict';

    /**
     * Dependent Lists Handler
     * Creates a dependency between two list fields where the second list is populated
     * based on the selection in the first list via AJAX
     */
    window.DependentLists = {
        
        /**
         * Initialize dependent lists functionality
         * 
         * @param {Object} options Configuration options
         * @param {string} options.prefix - Prefix for field names (e.g., 'jform')
         * @param {string} options.list1 - Name of the first list field (e.g., 'class_id')
         * @param {string} options.list2 - Name of the second list field (e.g., 'learner_id')
         * @param {string} options.prompt2 - Prompt message for list2 (e.g., '-Chọn sinh viên-')
         * @param {string} options.url2 - AJAX URL to fetch data for list2
         * @param {string|null} [options.list3] - Name of the third list field (optional)
         * @param {string} [options.prompt3] - Prompt message for list3 (required if list3 is provided)
         * @param {string} [options.url3] - AJAX URL to fetch data for list3 (required if list3 is provided)
         * @param {string} [options.loadingText] - Loading text (optional)
         * @param {string} [options.emptyText] - Empty list text (optional)
         * @param {Function} [options.onSuccess] - Success callback (optional)
         * @param {Function} [options.onError] - Error callback (optional)
         */
        init: function(options) {
            // Validate required parameters
            if (!this.validateOptions(options)) {
                console.error('DependentLists: Invalid configuration options');
                return;
            }

            // Set default values
            options = $.extend({
                loadingText: 'Đang tải...',
                emptyText: 'Không có dữ liệu',
                onSuccess: null,
                onError: null
            }, options);

            // Build field selectors
            var list1Selector = '#' + options.prefix + '_' + options.list1;
            var list2Selector = '#' + options.prefix + '_' + options.list2;

            var $list1 = $(list1Selector);
            var $list2 = $(list2Selector);

            // Check if elements exist
            if (!$list1.length || !$list2.length) {
                console.error('DependentLists: Could not find list elements', {
                    list1: list1Selector,
                    list2: list2Selector
                });
                return;
            }

            // Initialize the dependency
            this.setupDependency($list1, $list2, options);
        },

        /**
         * Validate configuration options
         * 
         * @param {Object} options Configuration options
         * @returns {boolean} True if valid, false otherwise
         */
        validateOptions: function(options) {
            if (!options || typeof options !== 'object') {
                return false;
            }

            // Required options for all configurations
            var required = ['prefix', 'list1', 'list2', 'prompt2', 'url2'];
            for (var i = 0; i < required.length; i++) {
                if (!options[required[i]] || typeof options[required[i]] !== 'string') {
                    console.error('DependentLists: Missing or invalid required option:', required[i]);
                    return false;
                }
            }

            // If list3 is provided, prompt3 and url3 are also required
            if (options.list3) {
                if (typeof options.list3 !== 'string') {
                    console.error('DependentLists: list3 must be a string');
                    return false;
                }
                
                if (!options.prompt3 || typeof options.prompt3 !== 'string') {
                    console.error('DependentLists: prompt3 is required when list3 is provided');
                    return false;
                }
                
                if (!options.url3 || typeof options.url3 !== 'string') {
                    console.error('DependentLists: url3 is required when list3 is provided');
                    return false;
                }
            }

            return true;
        },

        /**
         * Setup the dependency between two lists
         * 
         * @param {jQuery} $list1 First list element
         * @param {jQuery} $list2 Second list element
         * @param {Object} options Configuration options
         */
        setupDependency: function($list1, $list2, options) {
            var self = this;
            var $list3 = null;

            // Get list3 element if it exists
            if (options.list3) {
                var list3Selector = '#' + options.prefix + '_' + options.list3;
                $list3 = $(list3Selector);
                
                if (!$list3.length) {
                    console.error('DependentLists: Could not find list3 element:', list3Selector);
                    return;
                }
            }

            // Handle list1 selection change
            $list1.on('change', function() {
                var selectedValue = $(this).val();
                
                if (selectedValue) {
                    self.loadDependentData(selectedValue, $list2, options, 'list2');
                } else {
                    self.clearList($list2, options.prompt2);
                    // Also clear list3 if it exists
                    if ($list3) {
                        self.clearList($list3, options.prompt3);
                    }
                }
            });

            // Handle list2 selection change (for list3)
            if ($list3) {
                $list2.on('change', function() {
                    var selectedValue = $(this).val();
                    
                    if (selectedValue) {
                        self.loadDependentData(selectedValue, $list3, options, 'list3');
                    } else {
                        self.clearList($list3, options.prompt3);
                    }
                });
            }

            // Initialize lists state
            if (!$list1.val()) {
                this.clearList($list2, options.prompt2);
                if ($list3) {
                    this.clearList($list3, options.prompt3);
                }
            }
        },

        /**
         * Load dependent data via AJAX
         * 
         * @param {string|number} parentValue Selected value from parent list
         * @param {jQuery} $targetList Target list element to populate
         * @param {Object} options Configuration options
         * @param {string} targetType Type of target list ('list2' or 'list3')
         */
        loadDependentData: function(parentValue, $targetList, options, targetType) {
            var self = this;
            var url, parentFieldName, prompt;

            // Determine which URL and field name to use
            if (targetType === 'list3') {
                url = options.url3;
                parentFieldName = options.list2;
                prompt = options.prompt3;
            } else {
                url = options.url2;
                parentFieldName = options.list1;
                prompt = options.prompt2;
            }

            // Show loading state
            $targetList.prop('disabled', true);
            this.clearList($targetList);
            this.addOption($targetList, '', options.loadingText);

            // Get CSRF token
            var token = this.getCSRFToken();

            // Prepare AJAX data
            var ajaxData = {};
            ajaxData[parentFieldName] = parentValue;
            if (token) {
                ajaxData[token.name] = token.value;
            }

            // Make AJAX request
            $.ajax({
                url: url,
                type: 'POST',
                data: ajaxData,
                dataType: 'json',
                timeout: 10000,
                success: function(response) {
                    self.handleSuccess(response, $targetList, options, targetType);
                },
                error: function(xhr, status, error) {
                    self.handleError(xhr, status, error, $targetList, options, targetType);
                }
            });
        },

        /**
         * Handle successful AJAX response
         * 
         * @param {Object} response AJAX response
         * @param {jQuery} $targetList Target list element
         * @param {Object} options Configuration options
         * @param {string} targetType Type of target list ('list2' or 'list3')
         */
        handleSuccess: function(response, $targetList, options, targetType) {
            var prompt = targetType === 'list3' ? options.prompt3 : options.prompt2;

            // Clear loading state
            $targetList.prop('disabled', false);
            this.clearList($targetList);

            if (response.success && response.data && response.data.length > 0) {
                // Add prompt option
                this.addOption($targetList, '', prompt);
                
                // Add data options
                for (var i = 0; i < response.data.length; i++) {
                    var item = response.data[i];
                    this.addOption($targetList, item.value, item.name);
                }

                // Call success callback if provided
                if (typeof options.onSuccess === 'function') {
                    options.onSuccess(response, $targetList, targetType);
                }
            } else {
                // No data found
                this.addOption($targetList, '', options.emptyText);
            }
        },

        /**
         * Handle AJAX error
         * 
         * @param {Object} xhr XMLHttpRequest object
         * @param {string} status Status text
         * @param {string} error Error message
         * @param {jQuery} $targetList Target list element
         * @param {Object} options Configuration options
         * @param {string} targetType Type of target list ('list2' or 'list3')
         */
        handleError: function(xhr, status, error, $targetList, options, targetType) {
            console.error('DependentLists AJAX Error (' + targetType + '):', status, error);
            
            // Clear loading state
            $targetList.prop('disabled', false);
            this.clearList($targetList);
            
            // Add error option
            this.addOption($targetList, '', 'Lỗi tải dữ liệu');
            
            // Call error callback if provided
            if (typeof options.onError === 'function') {
                options.onError(xhr, status, error, $targetList, targetType);
            }
        },

        /**
         * Clear all options from a list
         * 
         * @param {jQuery} $list List element to clear
         * @param {string} [defaultText] Optional default option text
         */
        clearList: function($list, defaultText) {
            $list.empty();
            if (defaultText) {
                this.addOption($list, '', defaultText);
            }
        },

        /**
         * Add an option to a list
         * 
         * @param {jQuery} $list List element
         * @param {string|number} value Option value
         * @param {string} text Option text
         */
        addOption: function($list, value, text) {
            var $option = $('<option></option>')
                .attr('value', value)
                .text(text);
            $list.append($option);
        },

        /**
         * Get CSRF token for Joomla
         * 
         * @returns {Object|null} Token object with name and value, or null if not found
         */
        getCSRFToken: function() {
            if (typeof Joomla !== 'undefined' && Joomla.getOptions) {
                var tokenName = Joomla.getOptions('csrf.token');
                if (tokenName) {
                    var $tokenInput = $('input[name="' + tokenName + '"]');
                    if ($tokenInput.length) {
                        return {
                            name: tokenName,
                            value: $tokenInput.val() || '1'
                        };
                    }
                }
            }
            
            // Fallback: look for any hidden token input
            var $tokenInput = $('input[name*="token"]').first();
            if ($tokenInput.length) {
                return {
                    name: $tokenInput.attr('name'),
                    value: $tokenInput.val() || '1'
                };
            }

            return null;
        },

        /**
         * Escape HTML to prevent XSS
         * 
         * @param {string} text Text to escape
         * @returns {string} Escaped text
         */
        escapeHtml: function(text) {
            if (typeof text !== 'string') {
                return text;
            }
            
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { 
                return map[m]; 
            });
        }
    };

})(jQuery);