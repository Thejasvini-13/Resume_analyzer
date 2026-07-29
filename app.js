document.addEventListener('DOMContentLoaded', () => {
    // 1. Drag & Drop Upload Zone Configuration
    const uploadZone = document.getElementById('upload-zone');
    const fileInput = document.getElementById('resume-file');
    const fileDetails = document.getElementById('file-details');
    const fileNameSpan = document.getElementById('file-name');
    const fileDetailsSize = document.getElementById('file-details-size');
    const removeFileBtn = document.getElementById('remove-file');
    const uploadText = document.getElementById('upload-prompt-text');

    if (uploadZone && fileInput) {
        // Highlight upload zone on dragover
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                uploadZone.classList.add('dragover');
            }, false);
        });

        // Unhighlight upload zone on dragleave
        ['dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                uploadZone.classList.remove('dragover');
            }, false);
        });

        // Handle dropped files
        uploadZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateFileDetails(files[0]);
            }
        });

        // Handle file selection via click
        fileInput.addEventListener('change', (e) => {
            if (fileInput.files.length > 0) {
                updateFileDetails(fileInput.files[0]);
            }
        });

        // Remove selected file
        removeFileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            fileInput.value = '';
            fileDetails.style.display = 'none';
            uploadText.style.display = 'block';
        });
    }

    function updateFileDetails(file) {
        fileNameSpan.textContent = file.name;
        // Format size
        const sizeKB = (file.size / 1024).toFixed(1);
        fileDetailsSize.textContent = `(${sizeKB} KB)`;
        fileDetails.style.display = 'flex';
        uploadText.style.display = 'none';
    }

    // 2. Form Submission & Loader Animation
    const analyzerForm = document.getElementById('analyzer-form');
    const loadingOverlay = document.getElementById('loading-overlay');
    const panelForm = document.getElementById('panel-form');

    if (analyzerForm) {
        analyzerForm.addEventListener('submit', () => {
            if (panelForm) panelForm.style.display = 'none';
            if (loadingOverlay) loadingOverlay.style.display = 'block';
        });
    }

    // 3. Tab Navigation for Results Panel
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    if (tabBtns.length > 0) {
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetTab = btn.getAttribute('data-tab');

                // Remove active classes
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));

                // Set new active classes
                btn.classList.add('active');
                const targetContent = document.getElementById(`tab-${targetTab}`);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            });
        });
    }
});
