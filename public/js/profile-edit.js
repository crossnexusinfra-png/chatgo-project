// profile-edit.js
// プロフィール編集ページ用のJavaScript

(function() {
    'use strict';

    function handleImageError(img, index) {
        if (img) {
            img.style.display = 'none';
        }
        const placeholder = document.getElementById('avatar-placeholder-' + index);
        if (placeholder) {
            placeholder.style.display = 'flex';
        }
    }

    function handleImageLoad(img, index) {
        const placeholder = document.getElementById('avatar-placeholder-' + index);
        if (placeholder) {
            placeholder.style.display = 'none';
        }
        if (img) {
            img.style.display = 'block';
        }
    }

    function selectDefaultAvatar(radio) {
        document.querySelectorAll('.avatar-option').forEach(option => {
            option.classList.remove('selected');
        });
        
        if (radio.checked) {
            radio.closest('.avatar-option').classList.add('selected');
        }
    }

    function toggleCategory(header) {
        const category = header.getAttribute('data-category');
        const content = document.querySelector(`.accordion-content[data-category="${category}"]`);
        
        if (content) {
            const isOpen = content.classList.contains('open');
            
            if (isOpen) {
                content.classList.remove('open');
                header.classList.remove('active');
            } else {
                content.classList.add('open');
                header.classList.add('active');
            }
        }
    }

    // グローバルスコープに公開
    window.handleImageError = handleImageError;
    window.handleImageLoad = handleImageLoad;
    window.selectDefaultAvatar = selectDefaultAvatar;
    window.toggleCategory = toggleCategory;

    // ページ読み込み時の処理
    document.addEventListener('DOMContentLoaded', function() {
        // アコーディオンヘッダーのクリックイベント
        document.querySelectorAll('.accordion-header').forEach(header => {
            header.addEventListener('click', function() {
                toggleCategory(this);
            });
        });
        
        // ラジオボタンの変更イベント
        document.querySelectorAll('input[name="default_avatar"]').forEach(radio => {
            radio.addEventListener('change', function() {
                selectDefaultAvatar(this);
            });
        });
        
        const avatarImages = document.querySelectorAll('.avatar-thumbnail');
        avatarImages.forEach((img) => {
            const index = img.getAttribute('data-index');
            
            img.addEventListener('load', function() {
                handleImageLoad(img, index);
            });
            img.addEventListener('error', function() {
                handleImageError(img, index);
            });
            
            if (img.complete) {
                if (img.naturalHeight === 0) {
                    handleImageError(img, index);
                } else {
                    handleImageLoad(img, index);
                }
            }
        });
        
        const selectedAvatar = document.querySelector('input[name="default_avatar"]:checked');
        if (selectedAvatar) {
            selectDefaultAvatar(selectedAvatar);
        }
        
        document.querySelectorAll('.accordion-content.open').forEach(content => {
            const category = content.getAttribute('data-category');
            const header = document.querySelector(`.accordion-header[data-category="${category}"]`);
            if (header) {
                header.classList.add('active');
            }
        });

        // 文字数カウントの初期化
        const bio = document.getElementById('bio');
        const charCount = document.getElementById('charCount');
        if (bio && charCount) {
            charCount.textContent = bio.value.length;
            
            bio.addEventListener('input', function() {
                charCount.textContent = this.value.length;
                
                if (this.value.length > 100) {
                    charCount.style.color = '#dc3545';
                } else {
                    charCount.style.color = '#666';
                }
            });
        }
    });
})();
