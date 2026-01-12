// common-utils.js
// 共通ユーティリティ関数

(function() {
    'use strict';

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
        status.innerHTML = translations.adVideoLoading || 'Loading...';

        const modal = document.getElementById(modalId);
        const video = document.getElementById(videoId);
        if (!modal || !video) {
            status.innerHTML = '<div class="error-message">' + (translations.videoPlayerInitFailed || 'Failed to initialize video player') + '</div>';
            btn.disabled = false;
            return;
        }

        function handleError(e) {
            video.removeEventListener('error', handleError);
            video.removeEventListener('canplay', handleCanPlay);
            video.removeEventListener('ended', handleEnded);
            
            let errorMessage = translations.videoLoadFailed || 'Failed to load video';
            
            if (video.error) {
                const videoSrc = video.querySelector('source')?.src || video.src;
                switch (video.error.code) {
                    case video.error.MEDIA_ERR_ABORTED:
                        errorMessage = translations.videoLoadAborted || 'Video loading was aborted';
                        break;
                    case video.error.MEDIA_ERR_NETWORK:
                        errorMessage = translations.videoNetworkError || 'Network error: Could not load video. Please check your network connection.';
                        break;
                    case video.error.MEDIA_ERR_DECODE:
                        errorMessage = translations.videoDecodeError || 'Video decoding failed. The video file may be corrupted.';
                        break;
                    case video.error.MEDIA_ERR_SRC_NOT_SUPPORTED:
                        const videoUrlNotSet = translations.videoUrlNotSet || 'Not Set';
                        const browserConsoleDetails = translations.browserConsoleDetails || 'Please check the browser console for details.';
                        errorMessage = (translations.videoFormatNotSupported || 'Video format not supported') + '<br>Video URL: ' + (videoSrc || videoUrlNotSet) + '<br>' + browserConsoleDetails;
                        break;
                    default:
                        errorMessage = (translations.videoLoadError || 'Video loading error: :code').replace(':code', video.error.code);
                }
            } else {
                const videoSrc = video.querySelector('source')?.src || video.src;
                if (!videoSrc) {
                    errorMessage = translations.videoUrlNotSet || 'Video URL is not set';
                }
            }
            
            console.error('Video error:', video.error, e);
            status.innerHTML = '<div class="error-message">' + errorMessage + '</div>';
            btn.disabled = false;
            modal.style.display = 'none';
        }

        function handleCanPlay() {
            video.removeEventListener('canplay', handleCanPlay);
            status.innerHTML = translations.adVideoPlaying || 'Playing...';
            
            const playPromise = video.play();
            
            if (playPromise !== undefined) {
                playPromise
                    .then(() => {
                        status.innerHTML = translations.adVideoPlaying || 'Playing...';
                    })
                    .catch(error => {
                        console.error('Video play failed:', error);
                        status.innerHTML = '<div class="error-message">' + (translations.videoPlayFailed || 'Failed to play video') + '</div>';
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
                status.innerHTML = '<div class="error-message">' + (translations.errorOccurred || 'An error occurred') + '</div>';
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
            const rewardText = (translations.adWatchReward || 'coins earned!').replace(':coins', finalCoins);
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
     * 無限スクロールでスレッドを読み込む共通関数
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
                    resendBtn.innerHTML = translations.resendButton || 'Resend';
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
        content.innerHTML = '<p class="loading">' + (translations.loading || 'Loading...') + '</p>';
        
        fetch(`/user/${userId}/residence-history`, {
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
                    content.innerHTML = '<p class="no-history">' + (translations.noHistory || 'No history available') + '</p>';
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
                content.innerHTML = '<p class="no-history">' + (translations.errorOccurred || 'An error occurred') + '</p>';
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
            this.textContent = translations.loading || 'Loading...';
            
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
                        this.textContent = translations.showMore || 'Show More';
                    }
                } else {
                    this.parentElement.remove();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.disabled = false;
                this.textContent = translations.showMore || 'Show More';
            });
        });
    };
})();
