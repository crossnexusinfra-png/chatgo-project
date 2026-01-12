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
                if (data.message) {
                    alert(data.message);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
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
    });

    window.deleteFriend = function(friendId) {
        if (!confirm(translations.confirmDeleteFriend || 'Delete this friend?')) {
            return;
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
                if (translations.errorOccurred) {
                    alert(translations.errorOccurred);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (translations.errorOccurred) {
                alert(translations.errorOccurred);
            }
        });
    };

    window.rejectFriendRequest = function(userId) {
        if (!confirm(translations.confirmRejectRequest || 'Reject this friend request?')) {
            return;
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
                if (translations.errorOccurred) {
                    alert(translations.errorOccurred);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (translations.errorOccurred) {
                alert(translations.errorOccurred);
            }
        });
    };

    window.confirmRejectRequest = function(requestId) {
        if (!confirm(translations.confirmRejectRequest || 'Reject this friend request?')) {
            return;
        }
        
        const form = document.getElementById('reject-form-' + requestId);
        if (form) {
            form.submit();
        }
    };
})();
