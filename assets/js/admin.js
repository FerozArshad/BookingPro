/**
 * Booking System Pro - Simplified Admin JavaScript
 * No AJAX, no complex features - just simple page-based functionality
 */

(function($) {
    'use strict';
    
    const BSP_AdminJS = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Delete confirmations
            $('.bsp-delete-btn, .submitdelete').on('click', this.confirmDelete);
            
            // Bulk action confirmations
            $('.bsp-bulk-action-btn').on('click', this.confirmBulkAction);
            
            // Select all checkboxes
            $('#cb-select-all-1').on('change', this.toggleSelectAll);
        },
        
        confirmDelete: function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        },
        
        confirmBulkAction: function(e) {
            const action = $('#bulk-action-selector-top').val();
            const selectedItems = $('.bsp-bulk-checkbox:checked').length;
            
            if (action === '-1') {
                alert('Please select an action.');
                e.preventDefault();
                return false;
            }
            
            if (selectedItems === 0) {
                alert('Please select at least one item.');
                e.preventDefault();
                return false;
            }
            
            if (action === 'delete') {
                if (!confirm('Are you sure you want to delete the selected items? This action cannot be undone.')) {
                    e.preventDefault();
                    return false;
                }
            } else {
                if (!confirm('Are you sure you want to perform this action on the selected items?')) {
                    e.preventDefault();
                    return false;
                }
            }
        },
        
        toggleSelectAll: function() {
            const isChecked = $(this).prop('checked');
            $('.bsp-bulk-checkbox').prop('checked', isChecked);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        BSP_AdminJS.init();
    });
    
})(jQuery);
