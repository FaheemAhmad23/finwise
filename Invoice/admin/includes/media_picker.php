<?php
/**
 * Reusable Media Picker Popup Component
 * 
 * Include this file in any admin page that needs image selection.
 * Usage: Add a button with onclick="openMediaPicker('targetInputId', 'targetPreviewId')"
 * 
 * The popup shows existing uploaded images in a grid with an "Upload New" button.
 * Selecting an image fills the hidden input and updates the preview.
 */
?>

<!-- Media Picker Modal -->
<div id="mediaPickerModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm hidden">
    <div class="max-w-4xl w-full max-h-[90vh] bg-[#0a0a0a] border border-white/10 rounded-3xl flex flex-col overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between p-6 border-b border-white/5">
            <h2 class="text-xl font-bold">Choose Image</h2>
            <div class="flex items-center gap-3">
                <label class="btn-primary cursor-pointer text-sm">
                    <i class="fa-solid fa-upload mr-2"></i> Upload New
                    <input type="file" id="mediaPickerUploadInput" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden">
                </label>
                <button onclick="closeMediaPicker()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-full flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <!-- Upload Progress (hidden by default) -->
        <div id="mediaPickerUploadProgress" class="hidden px-6 py-3 bg-orange-500/10 border-b border-orange-500/20">
            <div class="flex items-center gap-3">
                <div class="w-5 h-5 border-2 border-orange-500/30 border-t-orange-500 rounded-full animate-spin"></div>
                <span class="text-sm text-orange-500">Uploading image...</span>
            </div>
        </div>

        <!-- Image Grid -->
        <div class="flex-1 overflow-y-auto p-6" id="mediaPickerGrid">
            <div id="mediaPickerImages" class="grid grid-cols-3 md:grid-cols-5 lg:grid-cols-6 gap-3">
                <!-- Images loaded via JS -->
            </div>
            <div id="mediaPickerEmpty" class="hidden text-center py-16">
                <i class="fa-solid fa-images text-5xl text-white/10 mb-4"></i>
                <p class="text-white/30 text-sm">No images uploaded yet.</p>
                <p class="text-white/20 text-xs mt-1">Upload your first image using the button above.</p>
            </div>
            <div id="mediaPickerLoading" class="text-center py-16">
                <div class="w-8 h-8 border-2 border-white/10 border-t-orange-500 rounded-full animate-spin mx-auto mb-4"></div>
                <p class="text-white/30 text-sm">Loading images...</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between p-6 border-t border-white/5">
            <div id="mediaPickerSelected" class="flex items-center gap-3 text-sm text-white/50">
                <span id="mediaPickerSelectedText">No image selected</span>
            </div>
            <div class="flex gap-3">
                <button onclick="closeMediaPicker()" class="px-6 py-2 bg-white/5 hover:bg-white/10 rounded-xl text-sm font-medium transition">Cancel</button>
                <button onclick="confirmMediaPicker()" id="mediaPickerConfirmBtn" class="btn-primary px-6 py-2 text-sm" disabled>Select Image</button>
            </div>
        </div>
    </div>
</div>

<script>
let mediaPickerTargetInput = null;
let mediaPickerTargetPreview = null;
let mediaPickerSelectedPath = null;
let mediaPickerOnSelect = null;

function openMediaPicker(inputId, previewId, onSelectCallback) {
    mediaPickerTargetInput = document.getElementById(inputId);
    mediaPickerTargetPreview = previewId ? document.getElementById(previewId) : null;
    mediaPickerSelectedPath = null;
    mediaPickerOnSelect = onSelectCallback || null;
    
    document.getElementById('mediaPickerConfirmBtn').disabled = true;
    document.getElementById('mediaPickerSelectedText').textContent = 'No image selected';
    document.getElementById('mediaPickerModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    loadMediaPickerImages();
}

function closeMediaPicker() {
    document.getElementById('mediaPickerModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function confirmMediaPicker() {
    if (mediaPickerSelectedPath && mediaPickerTargetInput) {
        mediaPickerTargetInput.value = mediaPickerSelectedPath;
        
        if (mediaPickerTargetPreview) {
            mediaPickerTargetPreview.src = mediaPickerSelectedPath;
            mediaPickerTargetPreview.closest('.media-preview-container')?.classList.remove('hidden');
        }
        
        if (mediaPickerOnSelect) {
            mediaPickerOnSelect(mediaPickerSelectedPath);
        }
    }
    closeMediaPicker();
}

function selectMediaPickerImage(path, el) {
    // Remove previous selection
    document.querySelectorAll('#mediaPickerImages .media-picker-item').forEach(item => {
        item.classList.remove('ring-2', 'ring-orange-500', 'ring-offset-2', 'ring-offset-[#0a0a0a]');
    });
    
    // Highlight selected
    el.classList.add('ring-2', 'ring-orange-500', 'ring-offset-2', 'ring-offset-[#0a0a0a]');
    mediaPickerSelectedPath = path;
    
    document.getElementById('mediaPickerConfirmBtn').disabled = false;
    document.getElementById('mediaPickerSelectedText').textContent = 'Selected: ' + path.split('/').pop();
}

function loadMediaPickerImages() {
    const grid = document.getElementById('mediaPickerImages');
    const empty = document.getElementById('mediaPickerEmpty');
    const loading = document.getElementById('mediaPickerLoading');
    
    grid.innerHTML = '';
    empty.classList.add('hidden');
    loading.classList.remove('hidden');
    
    fetch('/admin/api/media-list.php')
        .then(r => r.json())
        .then(data => {
            loading.classList.add('hidden');
            
            if (!data.length) {
                empty.classList.remove('hidden');
                return;
            }
            
            data.forEach(item => {
                const div = document.createElement('div');
                div.className = 'media-picker-item aspect-square bg-white/5 rounded-xl overflow-hidden cursor-pointer transition-all hover:opacity-80';
                div.onclick = function() { selectMediaPickerImage(item.path, this); };
                
                const img = document.createElement('img');
                img.src = item.path;
                img.alt = item.original_filename;
                img.className = 'w-full h-full object-cover';
                img.loading = 'lazy';
                
                div.appendChild(img);
                grid.appendChild(div);
            });
        })
        .catch(() => {
            loading.classList.add('hidden');
            empty.classList.remove('hidden');
        });
}

// Handle upload
document.getElementById('mediaPickerUploadInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    const progress = document.getElementById('mediaPickerUploadProgress');
    progress.classList.remove('hidden');
    
    const formData = new FormData();
    formData.append('media_file', file);
    formData.append('convert_webp', '1');
    
    fetch('/admin/api/media-upload.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        progress.classList.add('hidden');
        e.target.value = '';
        
        if (data.success) {
            // Reload grid and auto-select the new image
            loadMediaPickerImages();
            setTimeout(() => {
                mediaPickerSelectedPath = data.path;
                document.getElementById('mediaPickerConfirmBtn').disabled = false;
                document.getElementById('mediaPickerSelectedText').textContent = 'Selected: ' + data.filename;
                
                // Highlight the new image
                const items = document.querySelectorAll('#mediaPickerImages .media-picker-item');
                items.forEach(item => {
                    const img = item.querySelector('img');
                    if (img && img.src.endsWith(data.path.split('/').pop())) {
                        item.classList.add('ring-2', 'ring-orange-500', 'ring-offset-2', 'ring-offset-[#0a0a0a]');
                    }
                });
            }, 500);
        } else {
            alert(data.error || 'Upload failed');
        }
    })
    .catch(() => {
        progress.classList.add('hidden');
        e.target.value = '';
        alert('Upload failed. Please try again.');
    });
});
</script>
