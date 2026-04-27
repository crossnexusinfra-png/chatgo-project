// friends-index.js
// フレンドページ用のJavaScript

(function() {
    'use strict';

    function parseJsonDataset(value, fallback) {
        if (!value) return fallback;
        try {
            return JSON.parse(value);
        } catch (e) {
            console.error('Failed to parse friends config dataset:', e);
            return fallback;
        }
    }

    const configElement = document.getElementById('friends-index-config');
    const config = configElement ? {
        csrfToken: configElement.dataset.csrfToken || '',
        routes: parseJsonDataset(configElement.dataset.routes, {}),
        translations: parseJsonDataset(configElement.dataset.translations, {})
    } : (window.friendsIndexConfig || {});
    const translations = config.translations || {};
    const routes = config.routes || {};
    const csrfToken = config.csrfToken || '';

    window.copyInviteCode = function() {
        const inviteCode = document.getElementById('inviteCode');
        const copyButton = document.querySelector('.js-copy-invite-code');
        if (inviteCode) {
            const text = inviteCode.value || '';
            if (!text) return;
            const originalButtonText = copyButton ? copyButton.textContent : '';
            navigator.clipboard.writeText(text).then(function() {
                if (copyButton) {
                    const copiedLabel = copyButton.getAttribute('data-copied-label')
                        || translations.copied
                        || originalButtonText;
                    copyButton.textContent = copiedLabel;
                    setTimeout(function() {
                        copyButton.textContent = originalButtonText;
                    }, 1400);
                }
            });
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
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                friend_id: String(friendId)
            })
        })
        .then(async response => {
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                return response.json();
            }
            const text = await response.text();
            throw new Error('Unexpected response format: ' + text.slice(0, 120));
        })
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
                    timeString += hours + (translations.hours || '');
                }
                if (minutes > 0) {
                    timeString += minutes + (translations.minutes || '');
                }
                if (seconds > 0 || timeString === '') {
                    timeString += seconds + (translations.seconds || '');
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
        const copyInviteButton = document.querySelector('.js-copy-invite-code');
        if (copyInviteButton) {
            copyInviteButton.addEventListener('click', function() {
                window.copyInviteCode();
            });
        }

        updateWaitTimes();
        setInterval(updateWaitTimes, 1000);

        // フレンド申請フォーム: 送信開始時にボタン無効化＋送信内容に関係する要素も無効化（二重送信防止）
        document.querySelectorAll('.friend-send-request-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var formEl = e.target;
                var submitBtn = formEl.querySelector('button[type="submit"]');
                if (submitBtn && submitBtn.disabled) {
                    e.preventDefault();
                    return false;
                }
                e.preventDefault();
                formEl.classList.add('form-submitting');
                formEl.querySelectorAll('.js-friend-form-fields button').forEach(function(btn) {
                    btn.disabled = true;
                    btn.setAttribute('disabled', 'disabled');
                });
                formEl.querySelectorAll('.js-friend-form-fields input:not([type="hidden"]), .js-friend-form-fields textarea').forEach(function(el) {
                    el.readOnly = true;
                    el.setAttribute('readonly', 'readonly');
                });
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.setAttribute('disabled', 'disabled');
                    submitBtn.textContent = translations.sending_request || '申請中';
                }
                setTimeout(function() { formEl.submit(); }, 50);
            });
        });

        // フレンド承認フォーム: 送信開始時にボタン無効化＋送信内容に関係する要素も無効化（二重送信防止）
        document.querySelectorAll('.friend-accept-request-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var formEl = e.target;
                var submitBtn = formEl.querySelector('button[type="submit"]');
                if (submitBtn && submitBtn.disabled) {
                    e.preventDefault();
                    return false;
                }
                e.preventDefault();
                formEl.classList.add('form-submitting');
                formEl.querySelectorAll('.js-friend-form-fields button').forEach(function(btn) {
                    btn.disabled = true;
                    btn.setAttribute('disabled', 'disabled');
                });
                formEl.querySelectorAll('.js-friend-form-fields input:not([type="hidden"]), .js-friend-form-fields textarea').forEach(function(el) {
                    el.readOnly = true;
                    el.setAttribute('readonly', 'readonly');
                });
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.setAttribute('disabled', 'disabled');
                    submitBtn.textContent = translations.processing || '処理中';
                }
                setTimeout(function() { formEl.submit(); }, 50);
            });
        });

        // フレンド申請拒否フォーム: 確認ダイアログ後に送信
        document.querySelectorAll('.friend-reject-request-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && submitBtn.disabled) {
                    e.preventDefault();
                    return false;
                }
                e.preventDefault();
                const confirmMessage = translations.confirmRejectRequest || '';
                const confirmPromise = typeof window.showAppConfirmBox === 'function'
                    ? window.showAppConfirmBox(confirmMessage, { title: '確認' })
                    : Promise.resolve(confirm(confirmMessage));
                confirmPromise.then(function(confirmed) {
                    if (!confirmed) {
                        return;
                    }
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.setAttribute('disabled', 'disabled');
                        submitBtn.textContent = translations.processing || '処理中';
                    }
                    form.submit();
                });
            });
        });

        document.querySelectorAll('.js-send-coins-btn').forEach(function(button) {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                var friendId = button.getAttribute('data-friend-id');
                if (friendId) {
                    window.sendCoins(friendId);
                }
            });
        });

        document.querySelectorAll('.js-delete-friend-btn').forEach(function(button) {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                var friendId = button.getAttribute('data-friend-id');
                if (friendId) {
                    window.deleteFriend(event, friendId);
                }
            });
        });

        document.querySelectorAll('.js-reject-available-btn').forEach(function(button) {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                var userId = button.getAttribute('data-user-id');
                if (userId) {
                    window.rejectFriendRequest(event, userId);
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
        const confirmMessage = translations.confirmDeleteFriend || '';
        const confirmPromise = typeof window.showAppConfirmBox === 'function'
            ? window.showAppConfirmBox(confirmMessage, { title: '確認' })
            : Promise.resolve(confirm(confirmMessage));
        confirmPromise.then(function(confirmed) {
            if (!confirmed) {
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
                if (translations.errorOccurred && typeof window.showAppMessageBox === 'function') {
                    window.showAppMessageBox(translations.errorOccurred, { title: 'エラー' });
                } else if (translations.errorOccurred) {
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
            if (translations.errorOccurred && typeof window.showAppMessageBox === 'function') {
                window.showAppMessageBox(translations.errorOccurred, { title: 'エラー' });
            } else if (translations.errorOccurred) {
                alert(translations.errorOccurred);
            }
        });
        });
    };

    window.rejectFriendRequest = function(event, userId) {
        if (arguments.length === 1) {
            userId = event;
            event = null;
        }
        const button = (event && event.target) ? event.target : null;
        const originalText = button ? button.textContent : '';
        const confirmMessage = translations.confirmRejectRequest || '';
        const confirmPromise = typeof window.showAppConfirmBox === 'function'
            ? window.showAppConfirmBox(confirmMessage, { title: '確認' })
            : Promise.resolve(confirm(confirmMessage));
        confirmPromise.then(function(confirmed) {
            if (!confirmed) {
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
                if (translations.errorOccurred && typeof window.showAppMessageBox === 'function') {
                    window.showAppMessageBox(translations.errorOccurred, { title: 'エラー' });
                } else if (translations.errorOccurred) {
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
            if (translations.errorOccurred && typeof window.showAppMessageBox === 'function') {
                window.showAppMessageBox(translations.errorOccurred, { title: 'エラー' });
            } else if (translations.errorOccurred) {
                alert(translations.errorOccurred);
            }
        });
        });
    };

    window.confirmRejectRequest = function(event, requestId) {
        if (arguments.length === 1) {
            requestId = event;
            event = null;
        }
        const confirmMessage = translations.confirmRejectRequest || '';
        const confirmPromise = typeof window.showAppConfirmBox === 'function'
            ? window.showAppConfirmBox(confirmMessage, { title: '確認' })
            : Promise.resolve(confirm(confirmMessage));
        confirmPromise.then(function(confirmed) {
            if (!confirmed) {
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
        });
    };
})();
