// notifications-index.js
// 通知ページ用のJavaScript

(function() {
    'use strict';

    const config = window.notificationsIndexConfig || {};
    let messagesData = config.messagesData || [];
    const csrfToken = config.csrfToken || '';
    const translations = config.translations || {};
    let currentPage = config.currentPage || 1;
    let hasMorePages = config.hasMorePages || false;
    let isLoadingMore = false;

    // メッセージを開封済みとしてマークする関数
    async function markAsRead(messageId, element) {
        const userId = config.userId;
        
        if (!userId) {
            console.log('Not logged in, skipping read mark');
            return;
        }
        
        try {
            const url = `/notifications/${messageId}/read`;
            console.log('Sending read request:', url, 'userId:', userId, 'csrfToken:', csrfToken ? 'present' : 'none');
            
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            console.log('Response status:', response.status, response.ok);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP error:', response.status, errorText);
                return false;
            }
            
            const result = await response.json();
            console.log('Response result:', result);
            
            if (result.success) {
                element.dataset.isRead = 'true';
                
                const unreadMark = element.querySelector('.unread-mark');
                if (unreadMark) {
                    unreadMark.remove();
                }
                
                updateUnreadBadge();
                return true;
            } else {
                console.error('Failed to mark as read:', result);
                return false;
            }
        } catch (error) {
            console.error('Failed to mark as read:', error);
            return false;
        }
    }

    // HTMLをエスケープしてXSSを防ぐ
    function escapeHtml(str) {
        if (str == null) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // お知らせ本文を安全に表示（エスケープ＋改行→br＋URLリンク化）
    function renderMessageBodySafe(body) {
        if (body == null || body === '') return '';
        const urlPattern = /(https?:\/\/[^\s<>"{}|\\^`\[\]]+)/gi;
        const parts = body.split(urlPattern);
        // split でキャプチャすると [非URL, URL, 非URL, URL, ...] の順になる
        const safe = parts.map(function(p, i) {
            const isUrl = /^https?:\/\//i.test(p);
            if (isUrl) {
                return '<a href="' + escapeHtml(p) + '" target="_blank" rel="noopener noreferrer" class="response-url-link">' + escapeHtml(p) + '</a>';
            }
            return escapeHtml(p);
        });
        return safe.join('').replace(/\n/g, '<br>');
    }

    // メッセージの内容を取得して表示する関数（CSP対応: インラインスタイルを使わずクラスで開閉）
    window.toggleMessage = async function(messageId, element, event) {
        if (event && event.target.closest('.reply-section')) {
            return;
        }
        
        if (event && event.target.closest('.r18-change-section')) {
            return;
        }
        
        const messageBody = element.querySelector('.message-body');
        const toggleIcon = element.querySelector('.toggle-icon');
        const isUnread = element.dataset.isRead === 'false';
        const isClosed = !element.classList.contains('is-open');
        
        if (isClosed) {
            if (!messageBody.textContent.trim()) {
                const message = messagesData.find(m => m.id === messageId);
                if (message) {
                    messageBody.innerHTML = renderMessageBodySafe(message.body);
                }
            }
            element.classList.add('is-open');
            toggleIcon.textContent = '▲';
            
            if (isUnread) {
                await markAsRead(messageId, element);
            }
        } else {
            element.classList.remove('is-open');
            toggleIcon.textContent = '▼';
        }
    };

    // 返信を送信する関数
    window.submitReply = async function(event, messageId) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const replyBody = formData.get('reply_body');
        
        if (!replyBody || !replyBody.trim()) {
            alert(translations.replyRequired);
            return;
        }
        
        try {
            const response = await fetch(`/notifications/${messageId}/reply`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    body: replyBody.trim()
                })
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP error:', response.status, errorText);
                alert(translations.replyFailed);
                return;
            }
            
            const result = await response.json();
            
            if (result.success) {
                const replySection = form.closest('.reply-section');
                const article = form.closest('article');
                const isUnlimitedReply = article && article.dataset.unlimitedReply === 'true';
                
                if (replySection) {
                    if (isUnlimitedReply) {
                        const textarea = form.querySelector('textarea[name="reply_body"]');
                        if (textarea) {
                            textarea.value = '';
                        }
                        alert(translations.replySuccess);
                    } else {
                        replySection.innerHTML = '<div class="reply-success-message">' + translations.replySuccessMessage + '</div>';
                        alert(translations.replySuccess);
                    }
                }
            } else {
                alert(result.error || translations.replyFailed);
            }
        } catch (error) {
            console.error('Failed to send reply:', error);
            alert(translations.replyFailed);
        }
    };

    // コインを受け取る関数
    window.receiveCoin = async function(event, messageId, coinAmount) {
        event.preventDefault();
        event.stopPropagation();
        
        const userId = config.userId;
        
        if (!userId) {
            alert(translations.loginRequiredError);
            return;
        }
        
        const button = event.target;
        const coinSection = button.closest('.coin-reward-section');
        
        button.disabled = true;
        button.textContent = translations.processing;
        
        try {
            const response = await fetch(`/notifications/${messageId}/receive-coin`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP error:', response.status, errorText);
                alert(translations.notificationCoinReceiveFailed);
                button.disabled = false;
                button.textContent = translations.notificationReceiveCoin;
                return;
            }
            
            const result = await response.json();
            
            if (result.success) {
                if (coinSection) {
                    coinSection.innerHTML = '<div class="coin-received-message">' + translations.notificationCoinReceived + '</div>';
                }
                
                const message = messagesData.find(m => m.id === messageId);
                if (message) {
                    message.has_received_coin = true;
                }
            } else {
                alert(result.error || translations.notificationCoinReceiveFailed);
                button.disabled = false;
                button.textContent = translations.notificationReceiveCoin;
            }
        } catch (error) {
            console.error('Failed to receive coin:', error);
            alert(translations.notificationCoinReceiveFailed);
            button.disabled = false;
            button.textContent = translations.notificationReceiveCoin;
        }
    };

    // R18変更リクエストを承認する関数
    window.approveR18Change = async function(event, messageId) {
        event.preventDefault();
        event.stopPropagation();
        
        const userId = config.userId;
        
        if (!userId) {
            alert(translations.loginRequiredError);
            return;
        }
        
        const button = event.target;
        const r18Section = button.closest('.r18-change-section');
        
        const buttons = r18Section.querySelectorAll('button');
        buttons.forEach(btn => {
            btn.disabled = true;
        });
        button.textContent = translations.processing;
        
        try {
            const response = await fetch(`/notifications/${messageId}/r18-approve`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP error:', response.status, errorText);
                const result = await response.json().catch(() => ({ error: translations.r18ChangeApproveFailed }));
                alert(result.error || translations.r18ChangeApproveFailed);
                buttons.forEach(btn => {
                    btn.disabled = false;
                });
                button.textContent = translations.r18ChangeApproveButton;
                return;
            }
            
            const result = await response.json();
            
            if (result.success) {
                if (r18Section) {
                    r18Section.innerHTML = '<div class="r18-change-success-message">' + translations.r18ChangeApproveSuccess + '</div>';
                }
                
                const message = messagesData.find(m => m.id === messageId);
                if (message) {
                    message.reply_used = true;
                }
                
                alert(translations.r18ChangeApproveSuccess);
            } else {
                alert(result.error || translations.r18ChangeApproveFailed);
                buttons.forEach(btn => {
                    btn.disabled = false;
                });
                button.textContent = translations.r18ChangeApproveButton;
            }
        } catch (error) {
                console.error('Failed to approve R18 change:', error);
            alert(translations.r18ChangeApproveFailed);
            buttons.forEach(btn => {
                btn.disabled = false;
            });
            button.textContent = translations.r18ChangeApproveButton;
        }
    };

    // R18変更リクエストを拒否する関数
    window.rejectR18Change = async function(event, messageId) {
        event.preventDefault();
        event.stopPropagation();
        
        const userId = config.userId;
        
        if (!userId) {
            alert(translations.loginRequiredError);
            return;
        }
        
        const button = event.target;
        const r18Section = button.closest('.r18-change-section');
        
        const buttons = r18Section.querySelectorAll('button');
        buttons.forEach(btn => {
            btn.disabled = true;
        });
        button.textContent = translations.processing;
        
        try {
            const response = await fetch(`/notifications/${messageId}/r18-reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP error:', response.status, errorText);
                const result = await response.json().catch(() => ({ error: translations.r18ChangeRejectFailed }));
                alert(result.error || translations.r18ChangeRejectFailed);
                buttons.forEach(btn => {
                    btn.disabled = false;
                });
                button.textContent = translations.r18ChangeRejectButton;
                return;
            }
            
            const result = await response.json();
            
            if (result.success) {
                if (r18Section) {
                    r18Section.innerHTML = '<div class="r18-change-success-message">' + translations.r18ChangeRejectSuccess + '</div>';
                }
                
                const message = messagesData.find(m => m.id === messageId);
                if (message) {
                    message.reply_used = true;
                }
                
                alert(translations.r18ChangeRejectSuccess);
            } else {
                alert(result.error || translations.r18ChangeRejectFailed);
                buttons.forEach(btn => {
                    btn.disabled = false;
                });
                button.textContent = translations.r18ChangeRejectButton;
            }
        } catch (error) {
            console.error('Failed to reject R18 change:', error);
            alert(translations.r18ChangeRejectFailed);
            buttons.forEach(btn => {
                btn.disabled = false;
            });
            button.textContent = translations.r18ChangeRejectButton;
        }
    };

    // 未読数のバッジを更新する関数（CSP対応: 表示はクラスで制御）
    function updateUnreadBadge() {
        const unreadItems = document.querySelectorAll('[data-is-read="false"]');
        const unreadCount = unreadItems.length;
        
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.classList.toggle('is-hidden', unreadCount === 0);
        }
    }

    // さらに表示ボタンで次のページを読み込む
    window.loadMoreNotifications = async function() {
        if (isLoadingMore || !hasMorePages) {
            return;
        }
        
        isLoadingMore = true;
        const loadMoreBtn = document.getElementById('loadMoreBtn');
        const originalText = loadMoreBtn.textContent;
        loadMoreBtn.disabled = true;
        loadMoreBtn.textContent = translations.notificationsLoading;
        
        try {
            const nextPage = currentPage + 1;
            const response = await fetch(`/notifications?page=${nextPage}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error('Failed to load more notifications');
            }
            
            const data = await response.json();
            const notificationsList = document.getElementById('notificationsList');
            
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = data.html;
            const newItems = tempDiv.querySelectorAll('.notification-item');
            
            newItems.forEach(item => {
                notificationsList.appendChild(item);
            });
            
            if (data.messagesData && Array.isArray(data.messagesData)) {
                data.messagesData.forEach(msg => {
                    messagesData.push(msg);
                });
            }
            
            currentPage = nextPage;
            hasMorePages = data.hasMorePages;
            
            if (hasMorePages) {
                loadMoreBtn.disabled = false;
                loadMoreBtn.textContent = originalText;
            } else {
                document.querySelector('.load-more-container')?.remove();
            }
            
            if (typeof updateDateTimeDisplays === 'function') {
                updateDateTimeDisplays();
            }
        } catch (error) {
            console.error('Failed to load more notifications:', error);
            loadMoreBtn.disabled = false;
            loadMoreBtn.textContent = originalText;
            alert(translations.notificationsLoadFailed);
        } finally {
            isLoadingMore = false;
        }
    };

    // CSP対応: インラインイベントを使わずイベント委譲でバインド
    document.addEventListener('DOMContentLoaded', function() {
        const listEl = document.getElementById('notificationsList');
        if (!listEl) return;

        listEl.addEventListener('click', function(e) {
            const btn = e.target.closest('.coin-receive-btn');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                const mid = parseInt(btn.dataset.messageId, 10);
                const amount = parseInt(btn.dataset.coinAmount, 10);
                if (!isNaN(mid) && !isNaN(amount)) {
                    window.receiveCoin(e, mid, amount);
                }
                return;
            }
            const approveBtn = e.target.closest('.r18-approve-btn');
            if (approveBtn) {
                e.preventDefault();
                e.stopPropagation();
                const mid = parseInt(approveBtn.dataset.messageId, 10);
                if (!isNaN(mid)) window.approveR18Change(e, mid);
                return;
            }
            const rejectBtn = e.target.closest('.r18-reject-btn');
            if (rejectBtn) {
                e.preventDefault();
                e.stopPropagation();
                const mid = parseInt(rejectBtn.dataset.messageId, 10);
                if (!isNaN(mid)) window.rejectR18Change(e, mid);
                return;
            }
            const item = e.target.closest('.notification-item');
            if (item && !e.target.closest('.reply-section') && !e.target.closest('.r18-change-section') && !e.target.closest('.coin-reward-section')) {
                const mid = parseInt(item.dataset.messageId, 10);
                if (!isNaN(mid)) window.toggleMessage(mid, item, e);
            }
        });

        listEl.addEventListener('submit', function(e) {
            const form = e.target.closest('.reply-form');
            if (form) {
                e.preventDefault();
                const mid = parseInt(form.dataset.messageId, 10);
                if (!isNaN(mid)) window.submitReply(e, mid);
            }
        });

        const loadMoreBtn = document.getElementById('loadMoreBtn');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function() {
                window.loadMoreNotifications();
            });
        }
    });
})();
