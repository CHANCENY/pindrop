(function($){
    
    // File upload handler for pindrop forms
    // Handles multiple file uploads with AJAX, validation, preview, and removal
    
    const fieldSettings = [FIELD_SETTING_JSON];
    const fieldName = "[FIELD_NAME]";
    const wrapperId = "[FIELD_WRAPPER_ID]";
    
    // Create file upload UI elements
    const fileInput = $.querySelector(`input[name="${fieldName}"]`);
    if (!fileInput) {
        console.error(`File input with name "${fieldName}" not found in wrapper`, $);
        return;
    }
    
    const uploadArea = document.createElement('div');
    uploadArea.className = 'file-upload-area';
    uploadArea.innerHTML = `
        <div class="file-drop-zone">
            <div class="file-drop-text">
                <div class="file-icon">📁</div>
                <p>Drag & drop files here or click to browse</p>
                <small>Accepts: ${fieldSettings.accept || 'all files'}</small>
                ${fieldSettings.multiple ? `<small>Max files: ${fieldSettings.maxFiles || 1}</small>` : ''}
                ${fieldSettings.maxSize ? `<small>Max size: ${formatFileSize(fieldSettings.maxSize)}</small>` : ''}
            </div>
            <button type="button" class="file-browse-btn">Browse Files</button>
        </div>
        <div class="file-progress" style="display: none;">
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            <div class="progress-text">Uploading... <span class="progress-percent">0%</span></div>
        </div>
        <div class="file-list"></div>
        <div class="file-errors"></div>
    `;
    
    // Insert upload area after file input
    fileInput.parentNode.insertBefore(uploadArea, fileInput.nextSibling);
    fileInput.style.display = 'none';
    
    // File selection handlers
    const dropZone = uploadArea.querySelector('.file-drop-zone');
    const browseBtn = uploadArea.querySelector('.file-browse-btn');
    const fileList = uploadArea.querySelector('.file-list');
    const progressArea = uploadArea.querySelector('.file-progress');
    const errorArea = uploadArea.querySelector('.file-errors');
    const progressBar = uploadArea.querySelector('.progress-fill');
    const progressText = uploadArea.querySelector('.progress-percent');
    
    let uploadedFiles = [];
    
    // Browse button click
    browseBtn.addEventListener('click', () => {
        console.log('Browse button clicked, triggering file input click');
        fileInput.click();
    });
    
    // File input change
    fileInput.addEventListener('change', (e) => {
        console.log('File input changed, files:', e.target.files);
        handleFiles(e.target.files);
    });
    
    // Drag and drop handlers
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    
    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        handleFiles(e.dataTransfer.files);
    });
    
    // Handle file processing
    function handleFiles(files) {
        console.log('Handling files:', files);
        
        if (!files || files.length === 0) {
            console.log('No files to process');
            return;
        }
        
        // Validate files
        const validationErrors = validateFiles(files);
        if (validationErrors.length > 0) {
            showErrors(validationErrors);
            return;
        }
        
        // Upload each file
        Array.from(files).forEach(file => {
            uploadFile(file);
        });
    }
    
    // Validate files against field settings
    function validateFiles(files) {
        const errors = [];
        
        Array.from(files).forEach((file, index) => {
            // Check file type
            if (fieldSettings.accept) {
                const acceptedTypes = fieldSettings.accept.split(',').map(type => type.trim());
                const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
                if (!acceptedTypes.some(type => type.includes(fileExtension) || type.includes(file.type))) {
                    errors.push(`File "${file.name}" has invalid type. Accepts: ${fieldSettings.accept}`);
                }
            }
            
            // Check file size
            if (fieldSettings.maxSize && file.size > fieldSettings.maxSize) {
                errors.push(`File "${file.name}" is too large. Max size: ${formatFileSize(fieldSettings.maxSize)}`);
            }
            
            // Check max files
            if (fieldSettings.maxFiles && uploadedFiles.length + index >= fieldSettings.maxFiles) {
                errors.push(`Maximum ${fieldSettings.maxFiles} files allowed`);
            }
        });
        
        return errors;
    }
    
    // Upload single file via AJAX
    function uploadFile(file) {
        console.log('Uploading file:', file.name);
        
        const formData = new FormData();
        formData.append('file', file);
        formData.append('field_name', fieldName);
        
        // Show progress
        progressArea.style.display = 'block';
        progressBar.style.width = '0%';
        progressText.textContent = 'Uploading... 0%';
        
        // Create file preview item
        const fileItem = createFilePreview(file);
        fileList.appendChild(fileItem);
        
        // AJAX upload
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/pindrop/uploads/files', true);
        
        // Progress tracking
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percent + '%';
                progressText.textContent = `Uploading... ${percent}%`;
            }
        });
        
        // Upload complete
        xhr.addEventListener('load', () => {
            progressArea.style.display = 'none';
            
            try {
                const response = JSON.parse(xhr.responseText);
                console.log('Upload response:', response);
                
                if (response.status) {
                    // Success
                    uploadedFiles.push(...response.data);
                    updateFilePreview(fileItem, response.data[0]);
                } else {
                    // Error
                    showErrors([response.message]);
                    fileItem.remove();
                }
            } catch (e) {
                console.error('Failed to parse response:', e);
                showErrors(['Invalid server response']);
                fileItem.remove();
            }
        });
        
        // Upload error
        xhr.addEventListener('error', () => {
            progressArea.style.display = 'none';
            console.error('Upload failed for file:', file.name);
            showErrors([`Failed to upload "${file.name}"`]);
            fileItem.remove();
        });
        
        xhr.send(formData);
    }
    
    // Create file preview element
    function createFilePreview(file) {
        const item = document.createElement('div');
        item.className = 'file-preview-item uploading';
        
        const icon = getFileIcon(file.type);
        const size = formatFileSize(file.size);
        
        item.innerHTML = `
            <div class="file-preview-icon">${icon}</div>
            <div class="file-preview-info">
                <div class="file-name">${file.name}</div>
                <div class="file-size">${size}</div>
                <div class="file-status">Uploading...</div>
            </div>
            <button type="button" class="file-remove-btn" disabled>×</button>
        `;
        
        return item;
    }
    
    // Update file preview with server response
    function updateFilePreview(item, fileData) {
        item.classList.remove('uploading');
        item.classList.add('uploaded');
        
        const status = item.querySelector('.file-status');
        status.textContent = 'Uploaded';
        
        const removeBtn = item.querySelector('.file-remove-btn');
        removeBtn.disabled = false;
        removeBtn.addEventListener('click', () => {
            removeFile(item, fileData);
        });
        
        // Store file data
        item.dataset.fileId = fileData.id;
        item.dataset.filePath = fileData.path;
    }
    
    // Remove uploaded file
    function removeFile(item, fileData) {
        if (confirm(`Remove "${fileData.name}"?`)) {
            // AJAX request to remove file
            const formData = new FormData();
            formData.append('file_id', fileData.id);
            formData.append('field_name', fieldName);
            
            fetch('/pindrop/uploads/files/remove', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    item.remove();
                    uploadedFiles = uploadedFiles.filter(f => f.id !== fileData.id);
                } else {
                    alert('Failed to remove file: ' + data.message);
                }
            })
            .catch(() => {
                alert('Failed to remove file');
            });
        }
    }
    
    // Show error messages
    function showErrors(errors) {
        errorArea.innerHTML = errors.map(error => 
            `<div class="error-message">${error}</div>`
        ).join('');
    }
    
    // Get appropriate file icon
    function getFileIcon(fileType) {
        if (fileType.startsWith('image/')) return '🖼️';
        if (fileType.startsWith('video/')) return '🎥';
        if (fileType.startsWith('audio/')) return '🎵';
        if (fileType.includes('pdf')) return '📄';
        if (fileType.includes('word')) return '📝';
        if (fileType.includes('excel')) return '📊';
        if (fileType.includes('powerpoint')) return '📽';
        if (fileType.includes('zip') || fileType.includes('rar')) return '🗜️';
        return '📄';
    }
    
    // Format file size for display
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
})(document.getElementById("[FIELD_WRAPPER_ID]"));