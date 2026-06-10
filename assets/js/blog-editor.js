/**
 * Soạn blog: slug tiếng Việt, preview ảnh, đồng bộ editor.
 */
(function () {
    'use strict';

    const form = document.getElementById('blogEditorForm');
    if (!form) {
        return;
    }

    const titleInput = document.getElementById('blogTitle');
    const slugInput = document.getElementById('blogSlug');
    const editor = document.getElementById('editorSurface');
    const hiddenContent = document.getElementById('contentHtml');
    const coverInput = document.getElementById('coverImageInput');
    const coverDropzone = document.getElementById('coverDropzone');
    const coverPreview = document.getElementById('coverPreview');
    const coverPreviewImg = document.getElementById('coverPreviewImg');
    const coverRemoveBtn = document.getElementById('coverRemoveBtn');
    const existingImageInput = document.getElementById('existingImagePath');

    let slugManual = Boolean(slugInput && slugInput.dataset.manual === '1');

    /**
     * Chuyển tiêu đề tiếng Việt thành slug URL.
     * @param {string} text
     * @returns {string}
     */
    function slugifyVietnamese(text) {
        if (!text) {
            return '';
        }
        let s = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        s = s.replace(/đ/g, 'd').replace(/Đ/g, 'D');
        s = s.toLowerCase();
        s = s.replace(/[^a-z0-9\s-]/g, '');
        s = s.replace(/[\s_-]+/g, '-').replace(/^-+|-+$/g, '');
        return s;
    }

    function syncEditor() {
        if (editor && hiddenContent) {
            hiddenContent.value = editor.innerHTML.trim();
        }
    }

    function updateSlugFromTitle() {
        if (!titleInput || !slugInput || slugManual) {
            return;
        }
        slugInput.value = slugifyVietnamese(titleInput.value);
    }

    function showCoverPreview(src) {
        if (!coverPreview || !coverPreviewImg) {
            return;
        }
        coverPreviewImg.src = src;
        coverPreview.classList.add('is-visible');
        if (coverDropzone) {
            coverDropzone.style.display = 'none';
        }
    }

    function hideCoverPreview() {
        if (coverPreview) {
            coverPreview.classList.remove('is-visible');
        }
        if (coverPreviewImg) {
            coverPreviewImg.removeAttribute('src');
        }
        if (coverDropzone) {
            coverDropzone.style.display = '';
        }
        if (coverInput) {
            coverInput.value = '';
        }
        if (existingImageInput) {
            existingImageInput.value = '';
        }
    }

    function handleCoverFile(file) {
        if (!file || !file.type.startsWith('image/')) {
            return;
        }
        const reader = new FileReader();
        reader.onload = function () {
            showCoverPreview(reader.result);
        };
        reader.readAsDataURL(file);
    }

    if (titleInput) {
        titleInput.addEventListener('input', updateSlugFromTitle);
    }

    if (slugInput) {
        slugInput.addEventListener('input', function () {
            slugManual = slugInput.value.trim() !== '';
            slugInput.dataset.manual = slugManual ? '1' : '0';
        });
    }

    if (coverDropzone && coverInput) {
        coverDropzone.addEventListener('click', function () {
            coverInput.click();
        });

        coverDropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            coverDropzone.classList.add('is-dragover');
        });

        coverDropzone.addEventListener('dragleave', function () {
            coverDropzone.classList.remove('is-dragover');
        });

        coverDropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            coverDropzone.classList.remove('is-dragover');
            const file = e.dataTransfer && e.dataTransfer.files[0];
            if (file) {
                coverInput.files = e.dataTransfer.files;
                handleCoverFile(file);
            }
        });

        coverInput.addEventListener('change', function () {
            const file = coverInput.files && coverInput.files[0];
            handleCoverFile(file);
        });
    }

    if (coverRemoveBtn) {
        coverRemoveBtn.addEventListener('click', function (e) {
            e.preventDefault();
            hideCoverPreview();
        });
    }

  if (existingImageInput && existingImageInput.value && coverPreviewImg) {
        showCoverPreview(existingImageInput.value);
    }

    document.querySelectorAll('.blog-editor-toolbar button[data-cmd]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const cmd = btn.getAttribute('data-cmd');
            const val = btn.getAttribute('data-val') || null;
            document.execCommand(cmd, false, val);
            syncEditor();
            if (editor) {
                editor.focus();
            }
        });
    });

    const insertImageBtn = document.getElementById('insertImageBtn');
    if (insertImageBtn) {
        insertImageBtn.addEventListener('click', function () {
            const url = window.prompt('Nhập URL ảnh hoặc đường dẫn (vd: assets/images/...):');
            if (!url) {
                return;
            }
            document.execCommand('insertImage', false, url);
            syncEditor();
        });
    }

    const insertLinkBtn = document.getElementById('insertLinkBtn');
    if (insertLinkBtn) {
        insertLinkBtn.addEventListener('click', function () {
            const url = window.prompt('Nhập URL liên kết:');
            if (!url) {
                return;
            }
            document.execCommand('createLink', false, url);
            syncEditor();
        });
    }

    if (editor) {
        editor.addEventListener('input', syncEditor);
        editor.addEventListener('blur', syncEditor);
    }

    form.addEventListener('submit', function () {
        syncEditor();
    });

    updateSlugFromTitle();
    syncEditor();

    window.BlogEditor = {
        slugifyVietnamese: slugifyVietnamese,
    };
})();
