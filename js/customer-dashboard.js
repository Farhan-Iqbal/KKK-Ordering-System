document.addEventListener('DOMContentLoaded', () => {
    // 1. Auto-generate Order ID if empty
    const orderIdInput = document.getElementById('orderId');
    if (orderIdInput && !orderIdInput.value) {
        // Generates an Order ID format like: ORD-1725868800
        orderIdInput.value = 'ORD-' + Math.floor(Date.now() / 1000);
    }

    // 2. Display file name inside dropzone on file select
    const fileInput = document.getElementById('attachment');
    const fileNameDisplay = document.getElementById('fileNameDisplay');

    if (fileInput && fileNameDisplay) {
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                fileNameDisplay.textContent = 'Selected: ' + e.target.files[0].name;
                fileNameDisplay.style.fontWeight = '600';
                fileNameDisplay.style.color = '#4f46e5';
            } else {
                fileNameDisplay.textContent = 'Click to upload or drag & drop files here';
                fileNameDisplay.style.fontWeight = 'normal';
                fileNameDisplay.style.color = '#0f172a';
            }
        });
    }
});