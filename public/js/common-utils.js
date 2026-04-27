// common-utils.js
// 共通ユーティリティ関数

(function() {
    'use strict';

    function isEnglishUi() {
        const lang = (document.documentElement && document.documentElement.lang ? document.documentElement.lang : '').toLowerCase();
        return lang.indexOf('en') === 0;
    }

    window.getAppDialogTitle = function(kind) {
        const en = isEnglishUi();
        if (kind === 'error') {
            return en ? 'Error' : 'エラー';
        }
        return en ? 'Confirmation' : '確認';
    };

    window.getAppDialogCancelText = function() {
        return isEnglishUi() ? 'Cancel' : 'キャンセル';
    };

    function ensureMessageBoxElements() {
        let overlay = document.getElementById('appMessageBoxOverlay');
        if (overlay) {
            return overlay;
        }

        overlay = document.createElement('div');
        overlay.id = 'appMessageBoxOverlay';
        overlay.setAttribute('role', 'presentation');
        overlay.style.position = 'fixed';
        overlay.style.inset = '0';
        overlay.style.background = 'rgba(0, 0, 0, 0.45)';
        overlay.style.display = 'none';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.padding = '16px';
        overlay.style.zIndex = '9999';

        const box = document.createElement('div');
        box.id = 'appMessageBox';
        box.style.background = '#fff';
        box.style.borderRadius = '10px';
        box.style.maxWidth = '460px';
        box.style.width = '100%';
        box.style.boxShadow = '0 12px 30px rgba(0, 0, 0, 0.2)';
        box.style.padding = '18px 16px 14px';

        const title = document.createElement('h3');
        title.id = 'appMessageBoxTitle';
        title.style.margin = '0 0 10px';
        title.style.fontSize = '18px';
        title.style.lineHeight = '1.4';
        title.textContent = window.getAppDialogTitle('confirm');

        const message = document.createElement('p');
        message.id = 'appMessageBoxMessage';
        message.style.margin = '0';
        message.style.whiteSpace = 'pre-wrap';
        message.style.wordBreak = 'break-word';
        message.style.lineHeight = '1.6';

        const actions = document.createElement('div');
        actions.style.display = 'flex';
        actions.style.gap = '8px';
        actions.style.justifyContent = 'flex-end';
        actions.style.marginTop = '14px';

        const cancelButton = document.createElement('button');
        cancelButton.id = 'appMessageBoxCancel';
        cancelButton.type = 'button';
        cancelButton.className = 'btn btn-secondary';
        cancelButton.textContent = window.getAppDialogCancelText();

        const okButton = document.createElement('button');
        okButton.id = 'appMessageBoxOk';
        okButton.type = 'button';
        okButton.className = 'btn btn-primary';
        okButton.textContent = 'OK';

        actions.appendChild(cancelButton);
        actions.appendChild(okButton);
        box.appendChild(title);
        box.appendChild(message);
        box.appendChild(actions);
        overlay.appendChild(box);
        document.body.appendChild(overlay);

        return overlay;
    }

    window.showAppMessageBox = function(message, options) {
        const opts = options || {};
        const overlay = ensureMessageBoxElements();
        const titleEl = document.getElementById('appMessageBoxTitle');
        const messageEl = document.getElementById('appMessageBoxMessage');
        const okButton = document.getElementById('appMessageBoxOk');
        const cancelButton = document.getElementById('appMessageBoxCancel');

        return new Promise(function(resolve) {
            const title = typeof opts.title === 'string' && opts.title !== '' ? opts.title : window.getAppDialogTitle('confirm');
            const okText = typeof opts.okText === 'string' && opts.okText !== '' ? opts.okText : 'OK';
            const cancelText = typeof opts.cancelText === 'string' && opts.cancelText !== '' ? opts.cancelText : window.getAppDialogCancelText();
            const showCancel = !!opts.showCancel;
            const closeOnBackdrop = opts.closeOnBackdrop !== false;

            titleEl.textContent = title;
            messageEl.textContent = message || '';
            okButton.textContent = okText;
            cancelButton.textContent = cancelText;
            cancelButton.style.display = showCancel ? '' : 'none';
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';

            function cleanup() {
                overlay.style.display = 'none';
                document.body.style.overflow = '';
                okButton.removeEventListener('click', onOk);
                cancelButton.removeEventListener('click', onCancel);
                overlay.removeEventListener('click', onBackdropClick);
                document.removeEventListener('keydown', onKeyDown);
            }

            function onOk() {
                cleanup();
                resolve(true);
            }

            function onCancel() {
                cleanup();
                resolve(false);
            }

            function onBackdropClick(event) {
                if (!closeOnBackdrop) return;
                if (event.target === overlay) {
                    onCancel();
                }
            }

            function onKeyDown(event) {
                if (event.key !== 'Escape') return;
                if (showCancel || closeOnBackdrop) {
                    onCancel();
                }
            }

            okButton.addEventListener('click', onOk);
            cancelButton.addEventListener('click', onCancel);
            overlay.addEventListener('click', onBackdropClick);
            document.addEventListener('keydown', onKeyDown);
            (showCancel ? cancelButton : okButton).focus();
        });
    };

    window.showAppConfirmBox = function(message, options) {
        const opts = options || {};
        return window.showAppMessageBox(message, Object.assign({}, opts, { showCancel: true }));
    };

    /**
     * 広告動画を視聴する共通関数
     * @param {Object} options - 設定オプション
     * @param {string} options.modalId - モーダルのID
     * @param {string} options.videoId - 動画要素のID
     * @param {string} options.statusId - ステータス表示要素のID
     * @param {string} options.btnId - ボタンのID
     * @param {Object} options.translations - 翻訳オブジェクト
     * @param {string} options.csrfToken - CSRFトークン
     * @param {string} options.watchAdRoute - 広告視聴APIのルート
     * @param {Function} options.onSuccess - 成功時のコールバック（コイン数を引数に受け取る）
     * @param {Function} options.onClose - 閉じる時のコールバック
     */
    window.watchAdVideo = function(options) {
        const {
            modalId,
            videoId,
            statusId,
            btnId,
            translations = {},
            csrfToken = '',
            watchAdRoute = '/coins/watch-ad',
            onSuccess,
            onClose
        } = options;

        const btn = document.getElementById(btnId);
        const status = document.getElementById(statusId);
        if (!btn || !status) return;

        btn.disabled = true;
        status.innerHTML = translations.adVideoLoading || '読み込み中...';

        const modal = document.getElementById(modalId);
        const video = document.getElementById(videoId);
        if (!modal || !video) {
            status.innerHTML = '<div class="error-message">' + (translations.videoPlayerInitFailed || '動画プレイヤーの初期化に失敗しました') + '</div>';
            btn.disabled = false;
            return;
        }

        function handleError(e) {
            video.removeEventListener('error', handleError);
            video.removeEventListener('canplay', handleCanPlay);
            video.removeEventListener('ended', handleEnded);
            
            let errorMessage = translations.videoLoadFailed || '動画の読み込みに失敗しました';
            
            if (video.error) {
                const videoSrc = video.querySelector('source')?.src || video.src;
                switch (video.error.code) {
                    case video.error.MEDIA_ERR_ABORTED:
                        errorMessage = translations.videoLoadAborted || '動画の読み込みが中断されました';
                        break;
                    case video.error.MEDIA_ERR_NETWORK:
                        errorMessage = translations.videoNetworkError || 'ネットワークエラー: 動画を読み込めませんでした。ネットワーク接続を確認してください。';
                        break;
                    case video.error.MEDIA_ERR_DECODE:
                        errorMessage = translations.videoDecodeError || '動画のデコードに失敗しました。ファイルが破損している可能性があります。';
                        break;
                    case video.error.MEDIA_ERR_SRC_NOT_SUPPORTED:
                        const videoUrlNotSet = translations.videoUrlNotSet || '未設定';
                        const browserConsoleDetails = translations.browserConsoleDetails || '詳細はブラウザのコンソールをご確認ください。';
                        errorMessage = (translations.videoFormatNotSupported || '動画フォーマットがサポートされていません') + '<br>Video URL: ' + (videoSrc || videoUrlNotSet) + '<br>' + browserConsoleDetails;
                        break;
                    default:
                        errorMessage = (translations.videoLoadError || '動画読み込みエラー: :code').replace(':code', video.error.code);
                }
            } else {
                const videoSrc = video.querySelector('source')?.src || video.src;
                if (!videoSrc) {
                    errorMessage = translations.videoUrlNotSet || '動画URLが設定されていません';
                }
            }
            
            console.error('Video error:', video.error, e);
            status.innerHTML = '<div class="error-message">' + errorMessage + '</div>';
            btn.disabled = false;
            modal.style.display = 'none';
        }

        function handleCanPlay() {
            video.removeEventListener('canplay', handleCanPlay);
            status.innerHTML = translations.adVideoPlaying || '再生中...';
            
            const playPromise = video.play();
            
            if (playPromise !== undefined) {
                playPromise
                    .then(() => {
                        status.innerHTML = translations.adVideoPlaying || '再生中...';
                    })
                    .catch(error => {
                        console.error('Video play failed:', error);
                        status.innerHTML = '<div class="error-message">' + (translations.videoPlayFailed || '動画の再生に失敗しました') + '</div>';
                        btn.disabled = false;
                        modal.style.display = 'none';
                        video.removeEventListener('error', handleError);
                        video.removeEventListener('ended', handleEnded);
                    });
            }
        }

        function handleEnded() {
            video.removeEventListener('ended', handleEnded);
            video.removeEventListener('error', handleError);

            if (onClose) {
                onClose();
            }

            fetch(watchAdRoute, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (onSuccess) {
                        onSuccess(data.coins);
                    }
                } else {
                    status.innerHTML = '<div class="error-message">' + data.message + '</div>';
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('API error:', error);
                status.innerHTML = '<div class="error-message">' + (translations.errorOccurred || 'エラーが発生しました') + '</div>';
                btn.disabled = false;
            });
        }

        video.addEventListener('error', handleError);
        video.addEventListener('canplay', handleCanPlay);
        video.addEventListener('ended', handleEnded);

        modal.style.display = 'flex';
        video.load();
    };

    /**
     * コインルーレットアニメーションを実行する共通関数
     * @param {Object} options - 設定オプション
     * @param {string} options.overlayId - オーバーレイのID
     * @param {string} options.valueId - 値表示要素のID
     * @param {string} options.messageId - メッセージ表示要素のID
     * @param {string} options.okBtnId - OKボタンのID
     * @param {string} options.skipBtnId - スキップボタンのID
     * @param {number} options.finalCoins - 最終コイン数
     * @param {Object} options.translations - 翻訳オブジェクト
     */
    window.playCoinRoulette = function(options) {
        const {
            overlayId,
            valueId,
            messageId,
            okBtnId,
            skipBtnId,
            finalCoins,
            translations = {}
        } = options;

        const overlay = document.getElementById(overlayId);
        const valueEl = document.getElementById(valueId);
        const messageEl = document.getElementById(messageId);
        const okBtn = document.getElementById(okBtnId);
        const skipBtn = document.getElementById(skipBtnId);
        if (!overlay || !valueEl || !messageEl || !okBtn || !skipBtn) return;

        overlay.style.display = 'flex';
        messageEl.style.display = 'none';
        okBtn.style.display = 'none';
        skipBtn.style.display = 'inline-block';

        const numbers = [3, 4, 5];
        let idx = 0;
        let delay = 80;
        const steps = 20;
        let timeoutId = null;
        let isSkipped = false;

        function showFinalResult() {
            if (isSkipped) return;
            isSkipped = true;
            
            if (timeoutId) {
                clearTimeout(timeoutId);
                timeoutId = null;
            }

            valueEl.textContent = finalCoins;
            const rewardText = (translations.adWatchReward || ':coinsコイン獲得！').replace(':coins', finalCoins);
            messageEl.textContent = rewardText;
            messageEl.style.display = 'block';
            skipBtn.style.display = 'none';
            okBtn.style.display = 'inline-block';
            okBtn.onclick = function() {
                overlay.style.display = 'none';
                location.reload();
            };
        }

        skipBtn.onclick = showFinalResult;

        function step(stepIndex) {
            if (isSkipped) return;
            
            if (stepIndex >= steps) {
                showFinalResult();
                return;
            }

            valueEl.textContent = numbers[idx % numbers.length];
            idx++;

            delay *= 1.12;
            timeoutId = setTimeout(() => step(stepIndex + 1), delay);
        }

        step(0);
    };

    /**
     * 無限スクロールでルームを読み込む共通関数
     * @param {Object} options - 設定オプション
     * @param {string} options.url - 読み込み先URL
     * @param {Object} options.params - URLパラメータ
     * @param {Function} options.onLoad - 読み込み成功時のコールバック（data, currentOffsetを引数に受け取る）
     * @param {Function} options.onError - エラー時のコールバック
     */
    window.createInfiniteScrollLoader = function(options) {
        const {
            url,
            params = {},
            hasMore = true,
            onLoad,
            onError
        } = options;

        let isLoadingThreads = false;
        let currentHasMoreThreads = hasMore;
        let currentOffset = params.offset || 20;

        function loadMoreThreads() {
            if (isLoadingThreads || !currentHasMoreThreads) {
                return;
            }
            
            isLoadingThreads = true;
            const loadingIndicator = document.getElementById('loading-indicator');
            const postsGrid = document.querySelector('.posts-grid');
            
            if (loadingIndicator) {
                loadingIndicator.style.display = 'block';
            }
            
            const urlObj = new URL(url, window.location.origin);
            Object.keys(params).forEach(key => {
                if (key !== 'offset') {
                    urlObj.searchParams.set(key, params[key]);
                }
            });
            urlObj.searchParams.set('offset', currentOffset);
            
            fetch(urlObj.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.html && postsGrid) {
                    postsGrid.insertAdjacentHTML('beforeend', data.html);
                    
                    currentOffset = data.offset !== undefined ? data.offset : currentOffset;
                    currentHasMoreThreads = data.hasMore !== undefined ? data.hasMore : false;
                    
                    if (onLoad) {
                        onLoad(data, currentOffset);
                    }
                } else {
                    currentHasMoreThreads = false;
                }
                
                if (loadingIndicator) {
                    loadingIndicator.style.display = 'none';
                }
                isLoadingThreads = false;
            })
            .catch(error => {
                console.error('Error:', error);
                if (loadingIndicator) {
                    loadingIndicator.style.display = 'none';
                }
                isLoadingThreads = false;
                
                if (onError) {
                    onError(error);
                }
            });
        }
        
        // スクロールイベントを監視
        window.addEventListener('scroll', function() {
            const scrollHeight = document.documentElement.scrollHeight;
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const clientHeight = document.documentElement.clientHeight;
            
            if (scrollTop + clientHeight >= scrollHeight - 100) {
                loadMoreThreads();
            }
        });

        return {
            loadMore: loadMoreThreads,
            setOffset: (offset) => { currentOffset = offset; },
            setHasMore: (hasMore) => { currentHasMoreThreads = hasMore; }
        };
    };

    /**
     * 認証タイマーを初期化する共通関数
     * @param {Object} options - 設定オプション
     * @param {number} options.timeLeft - 残り時間（秒）
     * @param {number} options.resendTimeLeft - 再送信可能までの時間（秒）
     * @param {string} options.timerElementId - タイマー表示要素のID
     * @param {string} options.resendTimerElementId - 再送信タイマー表示要素のID
     * @param {string} options.resendBtnId - 再送信ボタンのID
     * @param {Object} options.translations - 翻訳オブジェクト
     */
    window.initAuthTimer = function(options) {
        const {
            timeLeft: initialTimeLeft = 600,
            resendTimeLeft: initialResendTimeLeft = 60,
            timerElementId = 'timer',
            resendTimerElementId = 'resendTimer',
            resendBtnId = 'resendBtn',
            translations = {}
        } = options;

        let timeLeft = initialTimeLeft;
        let resendTimeLeft = initialResendTimeLeft;

        const timerElement = document.getElementById(timerElementId);
        const resendTimerElement = document.getElementById(resendTimerElementId);
        const resendBtn = document.getElementById(resendBtnId);

        // メインタイマー
        const timer = setInterval(() => {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            if (timerElement) {
                timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                if (timerElement) {
                    timerElement.textContent = '00:00';
                }
                if (translations.expiredAlert) {
                    alert(translations.expiredAlert);
                }
            }
            timeLeft--;
        }, 1000);

        // 再送信タイマー
        const resendTimer = setInterval(() => {
            if (resendTimerElement) {
                resendTimerElement.textContent = resendTimeLeft;
            }
            
            if (resendTimeLeft <= 0) {
                clearInterval(resendTimer);
                if (resendBtn) {
                    resendBtn.disabled = false;
                    resendBtn.innerHTML = translations.resendButton || '再送信';
                }
            }
            resendTimeLeft--;
        }, 1000);

        return {
            clear: () => {
                clearInterval(timer);
                clearInterval(resendTimer);
            }
        };
    };

    /**
     * プロフィール履歴モーダルを開く共通関数
     * @param {Object} options - 設定オプション
     * @param {string} options.modalId - モーダルのID
     * @param {string} options.contentId - コンテンツ表示要素のID
     * @param {number} options.userId - ユーザーID
     * @param {Object} options.countries - 国名マッピング
     * @param {Function} options.getCountryName - 国コードから国名を取得する関数
     * @param {Object} options.translations - 翻訳オブジェクト
     */
    window.openResidenceHistoryModalCommon = function(options) {
        const {
            modalId = 'residenceHistoryModal',
            contentId = 'historyContent',
            userId,
            countries = {},
            getCountryName,
            translations = {}
        } = options;

        const modal = document.getElementById(modalId);
        const content = document.getElementById(contentId);
        
        if (!modal || !content) return;
        
        modal.style.display = 'block';
        content.innerHTML = '<p class="loading">' + (translations.loading || '読み込み中...') + '</p>';
        
        fetch(`/api/user/${userId}/residence-history`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.length === 0) {
                    content.innerHTML = '<p class="no-history">' + (translations.noHistory || '履歴はありません') + '</p>';
                } else {
                    let html = '';
                    data.forEach(history => {
                        const oldCountry = history.old_residence ? (getCountryName ? getCountryName(history.old_residence) : (countries[history.old_residence] || history.old_residence)) : '-';
                        const newCountry = getCountryName ? getCountryName(history.new_residence) : (countries[history.new_residence] || history.new_residence);
                        const changedAt = history.changed_at;
                        const utcDateTime = changedAt.includes('T') ? changedAt : changedAt.replace(' ', 'T') + 'Z';
                        
                        html += `
                            <div class="history-item">
                                <div class="history-change">
                                    <span>${oldCountry}</span>
                                    <span>→</span>
                                    <span>${newCountry}</span>
                                </div>
                                <div class="history-date">
                                    <span data-utc-datetime="${utcDateTime}" data-format="en">${changedAt}</span>
                                </div>
                            </div>
                        `;
                    });
                    content.innerHTML = html;
                    if (typeof convertAllUtcDatesToLocal === 'function') {
                        convertAllUtcDatesToLocal();
                    } else if (typeof formatLocalDateTime === 'function') {
                        const dateElements = content.querySelectorAll('[data-utc-datetime]');
                        dateElements.forEach(element => {
                            const utcDateTime = element.getAttribute('data-utc-datetime');
                            const format = element.getAttribute('data-format') || 'en';
                            const formatted = formatLocalDateTime(utcDateTime, format);
                            if (formatted) {
                                element.textContent = formatted;
                            }
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching residence history:', error);
                content.innerHTML = '<p class="no-history">' + (translations.errorOccurred || 'エラーが発生しました') + '</p>';
            });
    };

    /**
     * さらに表示ボタンの処理を初期化する共通関数
     * @param {Object} options - 設定オプション
     * @param {string} options.buttonId - ボタンのID
     * @param {string} options.listId - リスト要素のID
     * @param {Function} options.getUrl - URLを取得する関数（offsetを引数に受け取る）
     * @param {Object} options.translations - 翻訳オブジェクト
     */
    window.initLoadMoreButton = function(options) {
        const {
            buttonId = 'load-more-threads',
            listId = 'threads-list',
            getUrl,
            translations = {}
        } = options;

        const loadMoreBtn = document.getElementById(buttonId);
        if (!loadMoreBtn) return;

        loadMoreBtn.addEventListener('click', function() {
            const offset = parseInt(this.getAttribute('data-offset'));
            const threadsList = document.getElementById(listId);
            
            if (!threadsList || !getUrl) return;

            this.disabled = true;
            this.textContent = translations.loading || '読み込み中...';
            
            const url = getUrl(offset);
            
            fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.html && threadsList) {
                    threadsList.insertAdjacentHTML('beforeend', data.html);
                    this.setAttribute('data-offset', offset + 5);
                    
                    if (!data.hasMore) {
                        this.parentElement.remove();
                    } else {
                        this.disabled = false;
                        this.textContent = translations.showMore || 'もっと見る';
                    }
                } else {
                    this.parentElement.remove();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.disabled = false;
                this.textContent = translations.showMore || 'もっと見る';
            });
        });
    };
})();
