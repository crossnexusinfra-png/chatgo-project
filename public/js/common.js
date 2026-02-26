// common.js
// 共通ユーティリティ関数

(function() {
    'use strict';

    const config = window.commonConfig || {};
    const translations = config.translations || {};
    const routes = config.routes || {};
    const isAdult = config.isAdult || false;

    /**
     * UTC日時をローカルタイムゾーンに変換して表示するユーティリティ関数
     */
    window.formatLocalDateTime = function(utcDateTimeString, format = 'ja') {
        if (!utcDateTimeString) return '';
        
        try {
            let utcDate;
            if (utcDateTimeString.includes('T')) {
                if (!utcDateTimeString.endsWith('Z') && !utcDateTimeString.includes('+') && !utcDateTimeString.includes('-', 10)) {
                    utcDate = new Date(utcDateTimeString + 'Z');
                } else {
                    utcDate = new Date(utcDateTimeString);
                }
            } else {
                utcDate = new Date(utcDateTimeString.replace(' ', 'T') + 'Z');
            }
            
            if (isNaN(utcDate.getTime())) {
                console.warn('Invalid date:', utcDateTimeString);
                return utcDateTimeString;
            }
            
            const year = utcDate.getFullYear();
            const month = String(utcDate.getMonth() + 1).padStart(2, '0');
            const day = String(utcDate.getDate()).padStart(2, '0');
            const hours = String(utcDate.getHours()).padStart(2, '0');
            const minutes = String(utcDate.getMinutes()).padStart(2, '0');
            
            return `${year}-${month}-${day} ${hours}:${minutes}`;
        } catch (e) {
            console.error('Error formatting date:', e, utcDateTimeString);
            return utcDateTimeString;
        }
    };
    
    /**
     * ページ内のすべてのUTC日時をローカルタイムゾーンに変換
     */
    window.convertAllUtcDatesToLocal = function() {
        const elements = document.querySelectorAll('[data-utc-datetime]');
        elements.forEach(element => {
            const utcDateTime = element.getAttribute('data-utc-datetime');
            const format = element.getAttribute('data-format') || 'en';
            const formatted = window.formatLocalDateTime(utcDateTime, format);
            if (formatted) {
                element.textContent = formatted;
            }
        });
    };

    // DOMが読み込まれた後に実行
    document.addEventListener('DOMContentLoaded', function() {
        // UTC日時をローカルタイムゾーンに変換
        window.convertAllUtcDatesToLocal();
        
        // Enterキーで検索を実行
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const form = this.closest('form');
                    if (form) {
                        form.submit();
                    }
                }
            });
        }
        
        // タグページでの検索フォームの制御
        const searchForm = document.getElementById('searchForm');
        const currentPath = window.location.pathname;
        
        if (searchForm && currentPath.startsWith('/tag/')) {
            const tagMatch = currentPath.match(/^\/tag\/(.+)$/);
            if (tagMatch) {
                const tag = decodeURIComponent(tagMatch[1]);
                searchForm.action = `/tag/${tag}`;
            }
        }
        
        // モバイルメニューの制御
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileTagsOverlay = document.getElementById('mobileTagsOverlay');
        const closeTagsBtn = document.getElementById('closeTagsBtn');
        
        if (mobileMenuBtn && mobileTagsOverlay) {
            mobileMenuBtn.addEventListener('click', function() {
                mobileTagsOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        }
        
        if (closeTagsBtn && mobileTagsOverlay) {
            closeTagsBtn.addEventListener('click', function() {
                mobileTagsOverlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
        
        if (mobileTagsOverlay) {
            mobileTagsOverlay.addEventListener('click', function(e) {
                if (e.target === mobileTagsOverlay) {
                    mobileTagsOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        }
        
        // ESCキーでオーバーレイを閉じる
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && mobileTagsOverlay && mobileTagsOverlay.classList.contains('active')) {
                mobileTagsOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // ルーム作成モーダルの制御
        const openCreateThreadModal = document.getElementById('openCreateThreadModal');
        const createThreadModal = document.getElementById('createThreadModal');
        const closeCreateThreadModal = document.getElementById('closeCreateThreadModal');
        const cancelCreateThread = document.getElementById('cancelCreateThread');

        if (openCreateThreadModal && createThreadModal) {
            openCreateThreadModal.addEventListener('click', function() {
                createThreadModal.classList.add('show');
                document.body.style.overflow = 'hidden';
            });
        }

        if (closeCreateThreadModal && createThreadModal) {
            closeCreateThreadModal.addEventListener('click', function() {
                createThreadModal.classList.remove('show');
                document.body.style.overflow = '';
            });
        }

        if (cancelCreateThread && createThreadModal) {
            cancelCreateThread.addEventListener('click', function() {
                createThreadModal.classList.remove('show');
                document.body.style.overflow = '';
            });
        }

        if (createThreadModal) {
            createThreadModal.addEventListener('click', function(e) {
                if (e.target === createThreadModal) {
                    createThreadModal.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && createThreadModal && createThreadModal.classList.contains('show')) {
                createThreadModal.classList.remove('show');
                document.body.style.overflow = '';
            }
        });

        // R18タグ選択時に自動的にR18チェックボックスをチェック
        const tagSelect = document.getElementById('tag');
        const isR18Checkbox = document.getElementById('is_r18');
        if (tagSelect) {
            const r18Tags = [
                '成人向けメディア・コンテンツ・創作',
                '性体験談・性的嗜好・フェティシズム',
                'アダルト業界・風俗・ナイトワーク'
            ];
            
            tagSelect.addEventListener('change', function() {
                const selectedTag = this.value;
                
                if (!isAdult && r18Tags.includes(selectedTag)) {
                    if (translations.r18ThreadAdultOnly) {
                        alert(translations.r18ThreadAdultOnly);
                    }
                    this.value = 'その他';
                    return;
                }
                
                if (isR18Checkbox) {
                    if (r18Tags.includes(selectedTag)) {
                        isR18Checkbox.checked = true;
                        isR18Checkbox.disabled = true;
                    } else {
                        isR18Checkbox.disabled = false;
                    }
                }
            });
            
            if (isR18Checkbox && r18Tags.includes(tagSelect.value)) {
                isR18Checkbox.checked = true;
                isR18Checkbox.disabled = true;
            }
        }

        // 通報モーダルの制御
        const reportModal = document.getElementById('reportModal');
        const closeReportModal = document.getElementById('closeReportModal');
        const cancelReport = document.getElementById('cancelReport');

        window.openReportModal = function(threadId, responseId, reportedUserId) {
            if (!reportModal) return;
            
            const reportThreadIdInput = document.getElementById('report_thread_id');
            const reportResponseIdInput = document.getElementById('report_response_id');
            const reportReportedUserIdInput = document.getElementById('report_reported_user_id');
            if (reportThreadIdInput) {
                reportThreadIdInput.value = threadId || '';
            }
            if (reportResponseIdInput) {
                reportResponseIdInput.value = responseId || '';
            }
            if (reportReportedUserIdInput) {
                reportReportedUserIdInput.value = reportedUserId || '';
            }
            
            const reportReasonSelect = document.getElementById('report_reason');
            if (!reportReasonSelect) return;
            
            // 既存のオプションをすべて削除（最初の空オプションを除く）
            while (reportReasonSelect.options.length > 1) {
                reportReasonSelect.remove(1);
            }
            
            // プロフィール通報の場合
            if (reportedUserId) {
                // プロフィール通報専用の理由を追加
                const profileReasons = [
                    { value: 'スパム・迷惑行為', label: translations.reportReasonSpam || '' },
                    { value: '攻撃的・不適切な内容', label: translations.reportReasonOffensive || '' },
                    { value: '不適切なリンク・外部誘導', label: translations.reportReasonInappropriateLink || '' },
                    { value: 'なりすまし・虚偽の人物情報', label: translations.reportReasonImpersonation || '' },
                    { value: 'その他', label: translations.other || '' }
                ];
                
                profileReasons.forEach(reason => {
                    const option = document.createElement('option');
                    option.value = reason.value;
                    option.textContent = reason.label;
                    reportReasonSelect.appendChild(option);
                });
            } else {
                // ルーム・リプライ通報の場合
                const threadImageReasons = [
                    { value: 'ルーム画像が第三者の著作権を侵害している可能性がある', label: translations.reportReasonThreadImageCopyright || '' },
                    { value: 'ルーム画像に個人情報・他人の情報が含まれている', label: translations.reportReasonThreadImagePersonalInfo || '' },
                    { value: 'ルーム画像に不適切な内容が含まれている', label: translations.reportReasonThreadImageInappropriate || '' }
                ];
                
                const adultContentReason = {
                    value: '成人向けコンテンツが含まれる',
                    label: translations.reportReasonAdultContent || ''
                };
                
                // 基本の通報理由を追加
                const baseReasons = [
                    { value: 'スパム・迷惑行為', label: translations.reportReasonSpam || '' },
                    { value: '攻撃的・不適切な内容', label: translations.reportReasonOffensive || '' },
                    { value: '不適切なリンク・外部誘導', label: translations.reportReasonInappropriateLink || '' },
                    { value: '成人向け以外のコンテンツ規制違反', label: translations.reportReasonContentViolation || '' },
                    { value: '異なる思想に関しての意見の押し付け、妨害', label: translations.reportReasonOpinionImposition || '' },
                    { value: 'その他', label: translations.other || '' }
                ];
                
                baseReasons.forEach(reason => {
                    const option = document.createElement('option');
                    option.value = reason.value;
                    option.textContent = reason.label;
                    reportReasonSelect.appendChild(option);
                });
                
                const existingRoute = routes.existingReportRoute || '/reports/existing';
                fetch(existingRoute + '?' + new URLSearchParams({
                    thread_id: threadId || '',
                    response_id: responseId || ''
                }))
                .then(response => response.json())
                .then(data => {
                    const isR18Thread = data.is_r18_thread || false;
                    const contentViolationOption = reportReasonSelect.querySelector('option[value="成人向け以外のコンテンツ規制違反"]');
                    const otherOption = reportReasonSelect.querySelector('option[value="その他"]');
                    
                    if (!isR18Thread) {
                        const adultContentOption = document.createElement('option');
                        adultContentOption.value = adultContentReason.value;
                        adultContentOption.textContent = adultContentReason.label;
                        if (contentViolationOption) {
                            reportReasonSelect.insertBefore(adultContentOption, contentViolationOption);
                        } else if (otherOption) {
                            reportReasonSelect.insertBefore(adultContentOption, otherOption);
                        }
                    }
                    
                    if (threadId && !responseId) {
                        threadImageReasons.forEach(reason => {
                            const option = document.createElement('option');
                            option.value = reason.value;
                            option.textContent = reason.label;
                            if (otherOption) {
                                reportReasonSelect.insertBefore(option, otherOption);
                            }
                        });
                    }
                    
                    const reportReasonInput = document.getElementById('report_reason');
                    const reportDescriptionInput = document.getElementById('report_description');
                    
                    if (data.exists) {
                        if (reportReasonInput) {
                            reportReasonInput.value = data.reason || '';
                        }
                        if (reportDescriptionInput) {
                            reportDescriptionInput.value = data.description || '';
                        }
                    } else {
                        if (reportReasonInput) {
                            reportReasonInput.value = '';
                        }
                        if (reportDescriptionInput) {
                            reportDescriptionInput.value = '';
                        }
                    }
                    
                    reportModal.classList.add('show');
                    document.body.style.overflow = 'hidden';
                })
                .catch(error => {
                    console.error('Error fetching existing report:', error);
                    reportModal.classList.add('show');
                    document.body.style.overflow = 'hidden';
                });
                return;
            }
            
            // プロフィール通報の場合の既存通報取得
            const existingRoute = routes.existingReportRoute || '/reports/existing';
            fetch(existingRoute + '?' + new URLSearchParams({
                reported_user_id: reportedUserId || ''
            }))
            .then(response => response.json())
            .then(data => {
                const reportReasonInput = document.getElementById('report_reason');
                const reportDescriptionInput = document.getElementById('report_description');
                
                if (data.exists) {
                    if (reportReasonInput) {
                        reportReasonInput.value = data.reason || '';
                    }
                    if (reportDescriptionInput) {
                        reportDescriptionInput.value = data.description || '';
                    }
                } else {
                    if (reportReasonInput) {
                        reportReasonInput.value = '';
                    }
                    if (reportDescriptionInput) {
                        reportDescriptionInput.value = '';
                    }
                }
                
                reportModal.classList.add('show');
                document.body.style.overflow = 'hidden';
            })
            .catch(error => {
                console.error('Error fetching existing report:', error);
                reportModal.classList.add('show');
                document.body.style.overflow = 'hidden';
            });
        };

        if (closeReportModal && reportModal) {
            closeReportModal.addEventListener('click', function() {
                reportModal.classList.remove('show');
                document.body.style.overflow = '';
                const reportForm = document.getElementById('reportForm');
                if (reportForm) {
                    reportForm.reset();
                }
            });
        }

        if (cancelReport && reportModal) {
            cancelReport.addEventListener('click', function() {
                reportModal.classList.remove('show');
                document.body.style.overflow = '';
                const reportForm = document.getElementById('reportForm');
                if (reportForm) {
                    reportForm.reset();
                }
            });
        }

        if (reportModal) {
            reportModal.addEventListener('click', function(e) {
                if (e.target === reportModal) {
                    reportModal.classList.remove('show');
                    document.body.style.overflow = '';
                    const reportForm = document.getElementById('reportForm');
                    if (reportForm) {
                        reportForm.reset();
                    }
                }
            });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && reportModal && reportModal.classList.contains('show')) {
                reportModal.classList.remove('show');
                document.body.style.overflow = '';
                const reportForm = document.getElementById('reportForm');
                if (reportForm) {
                    reportForm.reset();
                }
            }
        });

        // ルーム作成フォーム: 送信開始時にボタン無効化＋送信内容に関係する入力も無効化（二重送信防止）
        const createThreadForm = document.getElementById('createThreadForm');
        if (createThreadForm) {
            createThreadForm.addEventListener('submit', function(e) {
                var form = e.target;
                if (form.id !== 'createThreadForm') return;
                var submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && submitBtn.disabled) {
                    e.preventDefault();
                    return false;
                }
                e.preventDefault();
                form.classList.add('form-submitting');
                var userName = form.querySelector('.js-create-thread-user_name');
                var title = form.querySelector('.js-create-thread-title');
                var body = form.querySelector('.js-create-thread-body');
                if (userName) { userName.readOnly = true; userName.setAttribute('readonly', 'readonly'); }
                if (title) { title.readOnly = true; title.setAttribute('readonly', 'readonly'); }
                if (body) { body.readOnly = true; body.setAttribute('readonly', 'readonly'); }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.setAttribute('disabled', 'disabled');
                    submitBtn.textContent = translations.creating_room || '作成中';
                }
                setTimeout(function() { form.submit(); }, 50);
            });
        }

        // 通報フォーム: 送信開始時にボタン無効化＋送信内容に関係する入力も無効化（二重送信防止）
        const reportForm = document.getElementById('reportForm');
        if (reportForm) {
            reportForm.addEventListener('submit', function(e) {
                var form = e.target;
                if (form.id !== 'reportForm') return;
                var submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && submitBtn.disabled) {
                    e.preventDefault();
                    return false;
                }
                e.preventDefault();
                form.classList.add('form-submitting');
                var reportDescription = form.querySelector('.js-report-description') || form.querySelector('textarea[name="description"]');
                var cancelReport = form.querySelector('#cancelReport');
                if (reportDescription) { reportDescription.readOnly = true; reportDescription.setAttribute('readonly', 'readonly'); }
                if (cancelReport) { cancelReport.disabled = true; cancelReport.setAttribute('disabled', 'disabled'); }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.setAttribute('disabled', 'disabled');
                    submitBtn.textContent = translations.submitting || '送信中';
                }
                setTimeout(function() { form.submit(); }, 50);
            });
        }

        // 改善要望フォーム: 送信開始時にボタン無効化＋送信内容に関係する入力も無効化（二重送信防止）
        const suggestionForm = document.getElementById('suggestionForm');
        if (suggestionForm) {
            suggestionForm.addEventListener('submit', function(e) {
                var form = e.target;
                if (form.id !== 'suggestionForm') return;
                var submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && submitBtn.disabled) {
                    e.preventDefault();
                    return false;
                }
                e.preventDefault();
                form.classList.add('form-submitting');
                var textarea = form.querySelector('.js-suggestion-message') || form.querySelector('textarea');
                if (textarea) { textarea.readOnly = true; textarea.setAttribute('readonly', 'readonly'); }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.setAttribute('disabled', 'disabled');
                    submitBtn.textContent = translations.submitting || '送信中';
                }
                setTimeout(function() { form.submit(); }, 50);
            });
        }

        // 画像ファイル選択時のファイル名表示
        const imageInput = document.getElementById('image');
        const imageFileName = document.getElementById('imageFileName');
        if (imageInput && imageFileName) {
            imageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    imageFileName.textContent = file.name;
                } else {
                    imageFileName.textContent = translations.noFileSelected || 'No file selected';
                }
            });
        }
    });
})();
