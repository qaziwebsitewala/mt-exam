(function($) {
    'use strict';

    $(document).ready(function() {
        const $fileInput = $('#results_csv');
        const $fileLabel = $('.em-file-label');
        const $fileInfo = $('.em-file-info');
        const $filename = $('.em-filename');
        const $removeBtn = $('.em-remove-file');
        const $form = $('#em-import-form');

        // File input change
        $fileInput.on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                $fileLabel.hide();
                $fileInfo.show();
                $filename.text(file.name);
            }
        });

        // Remove file
        $removeBtn.on('click', function() {
            $fileInput.val('');
            $fileLabel.show();
            $fileInfo.hide();
            $filename.text('');
        });

        // Drag and drop
        $fileLabel.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css('background', '#e5f2ff');
        });

        $fileLabel.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css('background', '#f0f6fc');
        });

        $fileLabel.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css('background', '#f0f6fc');

            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                $fileInput[0].files = files;
                $fileInput.trigger('change');
            }
        });

        // Form validation
        $form.on('submit', function(e) {
            if (!$fileInput.val()) {
                e.preventDefault();
                alert('Please select a CSV file to upload.');
                return false;
            }

            const file = $fileInput[0].files[0];
            if (file && !file.name.endsWith('.csv')) {
                e.preventDefault();
                alert('Please upload a CSV file.');
                return false;
            }

            // Show loading indicator
            const $submitBtn = $(this).find('button[type="submit"]');
            $submitBtn.prop('disabled', true);
            $submitBtn.html('<span class="dashicons dashicons-update spin"></span> Importing...');
        });
    });

})(jQuery);