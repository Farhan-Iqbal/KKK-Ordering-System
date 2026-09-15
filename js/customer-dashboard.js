document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('attachment');
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const form = document.getElementById('customerOrderForm');

    // Display selected filename in upload box
    if (fileInput && fileNameDisplay) {
        fileInput.addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
                fileNameDisplay.textContent = `Selected File: ${this.files[0].name}`;
                fileNameDisplay.style.color = '#4f46e5';
            } else {
                fileNameDisplay.textContent = 'Click to upload or drag & drop files here';
                fileNameDisplay.style.color = '';
            }
        });
    }

    // Reset file display text on form reset
    if (form) {
        form.addEventListener('reset', function () {
            if (fileNameDisplay) {
                fileNameDisplay.textContent = 'Click to upload or drag & drop files here';
                fileNameDisplay.style.color = '';
            }
        });
    }
});