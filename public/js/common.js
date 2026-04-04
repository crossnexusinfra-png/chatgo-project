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

    // DOMが読み込まれた後に実行（スクリプトがbody末尾の場合は即実行）
    function runWhenReady() {
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

        function updateThreadCreateCoinDisplay() {
            var bodyEl = document.querySelector('#createThreadForm textarea.js-create-thread-body');
            var displayEl = document.getElementById('threadCreateCoinDisplay');
            if (!bodyEl || !displayEl) return;
            var baseCoin = parseInt(bodyEl.getAttribute('data-base-coin'), 10) || 2;
            var bodyText = bodyEl.value || '';
            // CoinService と同等の URL 検出（URL1件=課金1文字）
            var urlPattern = new RegExp('https?:\\/\\/[^\\s<>"{}|\\\\^`\\[\\]]+', 'gi');
            var urlMatches = bodyText.match(urlPattern);
            var urlCount = urlMatches ? urlMatches.length : 0;
            var textOnly = bodyText.replace(urlPattern, '');
            var charCount = 0;
            try {
                charCount = ((Array.from && Array.from(textOnly).length) || textOnly.length) + urlCount;
            } catch (e) {
                charCount = textOnly.length + urlCount;
            }
            var bodyCoin = charCount > 0 ? Math.ceil(charCount / 100) : 0;
            var total = baseCoin + bodyCoin;
            var roomLabel = displayEl.getAttribute('data-room-label') || 'Room';
            var firstReplyLabel = displayEl.getAttribute('data-first-reply-label') || 'First reply';
            var totalLabel = displayEl.getAttribute('data-total-label') || 'Total';
            displayEl.textContent = totalLabel + ': ' + baseCoin + ' (' + roomLabel + ') + ' + bodyCoin + ' (' + firstReplyLabel + ' ' + charCount + ') = ' + total;
        }

        if (openCreateThreadModal && createThreadModal) {
            openCreateThreadModal.addEventListener('click', function() {
                if (openCreateThreadModal.disabled) {
                    return;
                }
                createThreadModal.classList.add('show');
                document.body.style.overflow = 'hidden';
                updateThreadCreateCoinDisplay();
            });
        }

        var bodyInput = document.querySelector('#createThreadForm textarea.js-create-thread-body');
        if (bodyInput) {
            bodyInput.addEventListener('input', updateThreadCreateCoinDisplay);
            bodyInput.addEventListener('change', updateThreadCreateCoinDisplay);
        }

        var createThreadTitleInput = document.querySelector('#createThreadForm .js-create-thread-title');
        if (createThreadTitleInput) {
            createThreadTitleInput.addEventListener('input', function() {
                var v = createThreadTitleInput.value;
                var n = v.replace(/\r\n|\r|\n/g, ' ');
                if (n !== v) {
                    createThreadTitleInput.value = n;
                }
            });
        }

        updateThreadCreateCoinDisplay();

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
                        isR18Checkbox.checked = false;
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

        // 通報ボタン：インラインonclickを避けCSP対応（イベント委譲）
        document.addEventListener('click', function(e) {
            const btn = e.target && e.target.closest && e.target.closest('.report-btn');
            if (!btn || btn.classList.contains('reported-badge') || btn.tagName !== 'BUTTON') return;
            const threadId = btn.dataset.reportThreadId || null;
            const responseId = btn.dataset.reportResponseId || null;
            const threadHasCustomImage = btn.dataset.reportThreadHasCustomImage === '1';
            const reportedUserId = null; // プロフィール通報は廃止
            // 通報変更ボタンはサーバーで既存内容を埋め込んでいるのでAPI不要
            const embeddedReason = (btn.dataset.reportReason !== undefined && btn.dataset.reportReason !== '') ? btn.dataset.reportReason : null;
            const embeddedDescription = (btn.dataset.reportDescription !== undefined) ? btn.dataset.reportDescription : null;
            if (window.openReportModal) {
                e.preventDefault();
                window.openReportModal(threadId, responseId, reportedUserId, embeddedReason, embeddedDescription, threadHasCustomImage);
            }
        });

        window.openReportModal = function(threadId, responseId, reportedUserId, embeddedReason, embeddedDescription, threadHasCustomImage) {
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
            const reportDescriptionInput = document.getElementById('report_description');
            if (!reportReasonSelect) return;
            
            // プロフィール通報は廃止（reportedUserIdは常にnull）
            
            // ルーム・リプライ通報: 通報変更ボタンなら data 属性の既存内容を使う（API不要・Cloudflare通過）
            var data;
            if (embeddedReason !== undefined && embeddedReason !== null && String(embeddedReason).trim() !== '') {
                data = {
                    exists: true,
                    reason: String(embeddedReason).trim(),
                    description: (embeddedDescription != null) ? String(embeddedDescription) : '',
                    is_r18_thread: false
                };
                runReportModalWithData(data, threadId, responseId, threadHasCustomImage);
                return;
            }
            
            // 新規通報または data が無い場合のみ API で取得
            var existingPath = (routes.existingReportRoute && routes.existingReportRoute.replace) ? routes.existingReportRoute.replace(/^https?:\/\/[^/]+/, '') : '';
            if (!existingPath) existingPath = '/api/reports/existing';
            var existingUrl = existingPath + '?' + new URLSearchParams({
                thread_id: threadId || '',
                response_id: responseId || ''
            });
            var existingHeaders = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };
            if (typeof window.location !== 'undefined' && window.location.href) {
                existingHeaders['Referer'] = window.location.href;
            }
            fetch(existingUrl, {
                credentials: 'same-origin',
                headers: existingHeaders
            })
            .then(function(response) {
                var ct = (response.headers.get('Content-Type') || '').toLowerCase();
                var isJson = ct.indexOf('application/json') !== -1;
                if (!response.ok && !isJson) {
                    return { exists: false, is_r18_thread: false };
                }
                return response.text().then(function(text) {
                    if (!isJson) return { exists: false, is_r18_thread: false };
                    try {
                        var data = JSON.parse(text);
                        return response.ok ? data : { exists: false, is_r18_thread: false };
                    } catch (e) {
                        return { exists: false, is_r18_thread: false };
                    }
                });
            })
            .then(function(data) {
                runReportModalWithData(data, threadId, responseId, threadHasCustomImage);
            })
            .catch(function() {
                runReportModalWithData({ exists: false, is_r18_thread: false }, threadId, responseId, threadHasCustomImage);
            });
        }
        
        function runReportModalWithData(data, threadId, responseId, threadHasCustomImage) {
            var reportReasonSelect = document.getElementById('report_reason');
            var reportDescriptionInput = document.getElementById('report_description');
            var reportModal = document.getElementById('reportModal');
            if (!reportReasonSelect || !reportModal) return;
            while (reportReasonSelect.options.length > 1) reportReasonSelect.remove(1);
            var baseReasons = [
                { value: 'スパム・迷惑行為', label: translations.reportReasonSpam || '' },
                { value: '攻撃的・不適切な内容', label: translations.reportReasonOffensive || '' },
                { value: '不適切なリンク・外部誘導', label: translations.reportReasonInappropriateLink || '' },
                { value: '成人向け以外のコンテンツ規制違反', label: translations.reportReasonContentViolation || '' },
                { value: '異なる思想に関しての意見の押し付け、妨害', label: translations.reportReasonOpinionImposition || '' },
                { value: 'その他', label: translations.other || '' }
            ];
            baseReasons.forEach(function(reason) {
                var opt = document.createElement('option');
                opt.value = reason.value;
                opt.textContent = reason.label;
                reportReasonSelect.appendChild(opt);
            });
            var otherOpt = reportReasonSelect.querySelector('option[value="その他"]');
            if (!data.is_r18_thread) {
                var ac = document.createElement('option');
                ac.value = '成人向けコンテンツが含まれる';
                ac.textContent = translations.reportReasonAdultContent || '';
                reportReasonSelect.insertBefore(ac, otherOpt || reportReasonSelect.options[reportReasonSelect.options.length - 1]);
            }
            if (threadId && !responseId && threadHasCustomImage) {
                [
                    { value: 'ルーム画像が第三者の著作権を侵害している可能性がある', label: translations.reportReasonThreadImageCopyright || '' },
                    { value: 'ルーム画像に個人情報・他人の情報が含まれている', label: translations.reportReasonThreadImagePersonalInfo || '' },
                    { value: 'ルーム画像に不適切な内容が含まれている', label: translations.reportReasonThreadImageInappropriate || '' }
                ].forEach(function(reason) {
                    var o = document.createElement('option');
                    o.value = reason.value;
                    o.textContent = reason.label;
                    if (otherOpt) reportReasonSelect.insertBefore(o, otherOpt);
                    else reportReasonSelect.appendChild(o);
                });
            }
            var reasonValue = data.exists && data.reason ? String(data.reason).trim() : '';
            var descValue = data.exists && data.description != null ? String(data.description) : '';
            if (reasonValue && !Array.from(reportReasonSelect.options).some(function(opt) { return opt.value === reasonValue; })) {
                var ex = document.createElement('option');
                ex.value = reasonValue;
                ex.textContent = reasonValue;
                reportReasonSelect.appendChild(ex);
            }
            reportReasonSelect.value = reasonValue;
            if (reportDescriptionInput) reportDescriptionInput.value = descValue;
            reportModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

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
                if (title) {
                    title.value = title.value.replace(/\r\n|\r|\n/g, ' ');
                }
                var cancelBtn = form.querySelector('#cancelCreateThread');
                var closeModalBtn = document.getElementById('closeCreateThreadModal');
                if (userName) { userName.readOnly = true; userName.setAttribute('readonly', 'readonly'); }
                if (title) { title.readOnly = true; title.setAttribute('readonly', 'readonly'); }
                if (body) { body.readOnly = true; body.setAttribute('readonly', 'readonly'); }
                if (cancelBtn) { cancelBtn.disabled = true; cancelBtn.setAttribute('disabled', 'disabled'); }
                if (closeModalBtn) { closeModalBtn.disabled = true; closeModalBtn.setAttribute('disabled', 'disabled'); }
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
                var closeReportModalBtn = document.getElementById('closeReportModal');
                if (reportDescription) { reportDescription.readOnly = true; reportDescription.setAttribute('readonly', 'readonly'); }
                if (cancelReport) { cancelReport.disabled = true; cancelReport.setAttribute('disabled', 'disabled'); }
                if (closeReportModalBtn) { closeReportModalBtn.disabled = true; closeReportModalBtn.setAttribute('disabled', 'disabled'); }
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

        // ルーム画像：CSP 対応のためインライン style は使わずクラスで background-size を適用
        // a*d <= b*c → 幅基準 → .thread-image-blur--w (100% auto)、そうでない → .thread-image-blur--h (auto 100%)
        function applyThreadImageBlurSize(wrapper) {
            var blur = wrapper.querySelector('.thread-image-blur');
            var img = wrapper.querySelector('img');
            if (!blur || !img || !img.naturalWidth || !img.naturalHeight) return;
            var a = img.naturalWidth;
            var b = img.naturalHeight;
            var c = 16;
            var d = 9;
            blur.classList.remove('thread-image-blur--w', 'thread-image-blur--h');
            blur.classList.add(a * d <= b * c ? 'thread-image-blur--w' : 'thread-image-blur--h');
        }
        function initThreadImageAspectRatios() {
            document.querySelectorAll('.thread-image-wrapper').forEach(function(wrapper) {
                var img = wrapper.querySelector('img');
                if (!img || !wrapper.querySelector('.thread-image-blur')) return;
                if (img.complete && img.naturalWidth) {
                    applyThreadImageBlurSize(wrapper);
                } else {
                    img.addEventListener('load', function() { applyThreadImageBlurSize(wrapper); });
                }
            });
        }

        // ルーム画像：CSP で style 属性がブロックされるため、data-bg-url を nonce 付き <style> で注入
        var nonce = document.body.getAttribute('data-csp-nonce') || '';
        var blurEls = document.querySelectorAll('.thread-image-blur[data-bg-url]');
        if (blurEls.length && nonce) {
            var rules = [];
            blurEls.forEach(function(el, i) {
                var url = el.getAttribute('data-bg-url');
                if (!url) return;
                var id = 'thread-blur-' + i + '-' + (Math.random().toString(36).slice(2, 10));
                el.id = id;
                try {
                    rules.push('#' + id + '{ background-image: url(' + JSON.stringify(url) + '); }');
                } catch (e) { /* URL が不正な場合はスキップ */ }
                el.removeAttribute('data-bg-url');
            });
            if (rules.length) {
                var styleEl = document.createElement('style');
                styleEl.nonce = nonce;
                styleEl.textContent = rules.join('\n');
                (document.head || document.documentElement).appendChild(styleEl);
            }
        }
        initThreadImageAspectRatios();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runWhenReady);
    } else {
        runWhenReady();
    }
})();
