// friends-index.js
// フレンドページ用のJavaScript

(function() {
    'use strict';

    const config = window.friendsIndexConfig || {};
    const translations = config.translations || {};
    const routes = config.routes || {};
    const csrfToken = config.csrfToken || '';

    window.copyInviteCode = function() {
        const inviteCode = document.getElementById('inviteCode');
        if (inviteCode) {
            inviteCode.select();
            document.execCommand('copy');
            if (translations.inviteCodeCopied) {
                alert(translations.inviteCodeCopied);
            }
        }
    };

    window.sendCoins = function(friendId) {
        const sendButton = document.getElementById('send-coins-btn-' + friendId);
        if (sendButton && sendButton.disabled) {
            return;
        }
        const originalText = sendButton ? sendButton.textContent : '';
        if (sendButton) {
            sendButton.disabled = true;
            sendButton.classList.add('btn-disabled');
            sendButton.textContent = translations.submitting || '送信中';
        }
        fetch(routes.sendCoinsRoute || '/friends/send-coins', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                friend_id: friendId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                if (sendButton) {
                    sendButton.disabled = false;
                    sendButton.classList.remove('btn-disabled');
                    sendButton.textContent = originalText;
                }
                if (data.message) {
                    alert(data.message);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (sendButton) {
                sendButton.disabled = false;
                sendButton.classList.remove('btn-disabled');
                sendButton.textContent = originalText;
            }
            if (translations.errorOccurred) {
                alert(translations.errorOccurred);
            }
        });
    };

    // 残り時間のカウントダウン表示
    function updateWaitTimes() {
        const now = Math.floor(Date.now() / 1000);
        
        document.querySelectorAll('.coin-send-wait-time').forEach(function(element) {
            const nextAvailableTimestamp = parseInt(element.getAttribute('data-next-available'));
            const friendId = element.getAttribute('data-friend-id');
            const waitTimeElement = document.getElementById('wait-time-' + friendId);
            const sendButton = document.getElementById('send-coins-btn-' + friendId);
            
            if (nextAvailableTimestamp > now) {
                const remainingSeconds = nextAvailableTimestamp - now;
                const hours = Math.floor(remainingSeconds / 3600);
                const minutes = Math.floor((remainingSeconds % 3600) / 60);
                const seconds = remainingSeconds % 60;
                
                let timeString = '';
                if (hours > 0) {
                    timeString += hours + (translations.hours || 'hours');
                }
                if (minutes > 0) {
                    timeString += minutes + (translations.minutes || 'minutes');
                }
                if (seconds > 0 || timeString === '') {
                    timeString += seconds + (translations.seconds || 'seconds');
                }
                
                if (waitTimeElement) {
                    waitTimeElement.textContent = timeString;
                }
                
                // 残り時間が0になったらボタンを有効化
                if (remainingSeconds <= 0) {
                    if (sendButton) {
                        sendButton.disabled = false;
                        sendButton.classList.remove('btn-disabled');
                    }
                    if (element) {
                        element.style.display = 'none';
                    }
                }
            } else {
                // 時間が経過した場合
                if (sendButton) {
                    sendButton.disabled = false;
                    sendButton.classList.remove('btn-disabled');
                }
                if (element) {
                    element.style.display = 'none';
                }
            }
        });
    }

    // ページ読み込み時にカウントダウンを開始
    document.addEventListener('DOMContentLoaded', function() {
        updateWaitTimes();
        setInterval(updateWaitTimes, 1000);

        // フレンド申請フォーム: 送信開始時にボタン無効化＋送信内容に関係する要素も無効化（二重送信防止）
        document.querySelectorAll('.friend-send-request-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && submitBtn.disabled) {
                    e.preventDefault();
                    return false;
                }
                form.querySelectorAll('button').forEach(function(btn) { btn.disabled = true; });
                form.querySelectorAll('input:not([type="hidden"]), textarea').forEach(function(el) {
                    el.readOnly = true;
                    el.setAttribute('aria-disabled', 'true');
                });
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = translations.sending_request || '申請中';
                }
            });
        });

        // フレンド承認フォーム: 送信開始時にボタン無効化＋送信内容に関係する要素も無効化（二重送信防止）
        document.querySelectorAll('.friend-accept-request-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && submitBtn.disabled) {
                    e.preventDefault();
                    return false;
                }
                form.querySelectorAll('button').forEach(function(btn) { btn.disabled = true; });
                form.querySelectorAll('input:not([type="hidden"]), textarea').forEach(function(el) {
                    el.readOnly = true;
                    el.setAttribute('aria-disabled', 'true');
                });
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = translations.processing || '処理中';
                }
            });
        });
    });

    window.deleteFriend = function(event, friendId) {
        if (arguments.length === 1) {
            friendId = event;
            event = null;
        }
        const button = (event && event.target) ? event.target : null;
        const originalText = button ? button.textContent : '';
        if (!confirm(translations.confirmDeleteFriend || 'Delete this friend?')) {
            return;
        }
        if (button) {
            button.disabled = true;
            button.textContent = translations.deleting || '削除中';
        }
        fetch(routes.deleteRoute || '/friends/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                friend_id: friendId
            })
        })
        .then(response => {
            if (response.ok) {
                location.reload();
            } else {
                if (button) {
                    button.disabled = false;
                    button.textContent = originalText;
                }
                if (translations.errorOccurred) {
                    alert(translations.errorOccurred);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }
            if (translations.errorOccurred) {
                alert(translations.errorOccurred);
            }
        });
    };

    window.rejectFriendRequest = function(event, userId) {
        if (arguments.length === 1) {
            userId = event;
            event = null;
        }
        const button = (event && event.target) ? event.target : null;
        const originalText = button ? button.textContent : '';
        if (!confirm(translations.confirmRejectRequest || 'Reject this friend request?')) {
            return;
        }
        if (button) {
            button.disabled = true;
            button.textContent = translations.processing || '処理中';
        }
        fetch(routes.rejectAvailableRoute || '/friends/reject-available', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                user_id: userId
            })
        })
        .then(response => {
            if (response.ok) {
                location.reload();
            } else {
                if (button) {
                    button.disabled = false;
                    button.textContent = originalText;
                }
                if (translations.errorOccurred) {
                    alert(translations.errorOccurred);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }
            if (translations.errorOccurred) {
                alert(translations.errorOccurred);
            }
        });
    };

    window.confirmRejectRequest = function(event, requestId) {
        if (arguments.length === 1) {
            requestId = event;
            event = null;
        }
        if (!confirm(translations.confirmRejectRequest || 'Reject this friend request?')) {
            return;
        }
        const button = (event && event.target) ? event.target : null;
        if (button) {
            button.disabled = true;
            button.textContent = translations.processing || '処理中';
        }
        const form = document.getElementById('reject-form-' + requestId);
        if (form) {
            form.submit();
        }
    };
})();
