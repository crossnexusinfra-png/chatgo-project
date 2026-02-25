// thread-show.js
// ルーム詳細ページ用のJavaScript

(function() {
    'use strict';

    // グローバル変数（viewから渡される）
    const config = window.threadShowConfig || {};
    const translations = config.translations || {};
    const threadId = config.threadId || 0;
    const initialResponseCount = config.initialResponseCount || 0;
    const totalResponses = config.totalResponses || 0;
    const responsesPerPage = config.responsesPerPage || 10;
    const phpUploadMaxSize = config.phpUploadMaxSize || (2 * 1024 * 1024);
    const lang = config.lang || 'ja';
    const routes = config.routes || {};
    const isCurrentUserThreadOwner = config.isCurrentUserThreadOwner || false;
    const continuationRequestThreshold = config.continuationRequestThreshold || 3;
    const csrfToken = config.csrfToken || '';

    // 無限スクロール用の変数
    let isLoadingResponses = false;
    let isLoadingForSearch = false;
    let hasMoreResponses = true;
    let currentOffset = initialResponseCount;

    // リアルタイム更新用の変数
    let lastResponseId = 0;
    let pollingInterval = null;
    let isPollingActive = true;

    // 検索機能用の変数
    let isSearchMode = false;
    let searchInput, searchResults, searchResultsArea, searchResultsList, responsesContainer, chatInput;

    // ページ読み込み時に一番下までスクロール
    function scrollToBottom() {
        const responsesContainer = document.getElementById('responsesContainer');
        if (responsesContainer) {
            responsesContainer.scrollTop = responsesContainer.scrollHeight;
        }
    }

    // リプライを読み込む関数
    async function loadMoreResponses() {
        if (isLoadingResponses || !hasMoreResponses) {
            console.log('loadMoreResponses skipped:', {
                isLoadingResponses: isLoadingResponses,
                hasMoreResponses: hasMoreResponses
            });
            return;
        }
        
        console.log('loadMoreResponses called:', {
            currentOffset: currentOffset,
            hasMoreResponses: hasMoreResponses
        });

        isLoadingResponses = true;
        const responsesContainer = document.getElementById('responsesContainer');
        const loadingIndicator = document.getElementById('loadingIndicator');
        
        const scrollHeightBefore = responsesContainer.scrollHeight;
        const scrollTopBefore = responsesContainer.scrollTop;
        
        if (loadingIndicator) {
            loadingIndicator.style.display = 'block';
        }

        try {
            const response = await fetch(`/threads/${threadId}/responses?offset=${currentOffset}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();

            hasMoreResponses = data.hasMore !== undefined ? data.hasMore : false;
            currentOffset = data.offset !== undefined ? data.offset : currentOffset;

            if (data.html && data.html.trim() !== '') {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data.html;
                
                const firstResponseItem = responsesContainer.querySelector('.response-item, .alert, .no-responses');
                if (firstResponseItem) {
                    const insertBefore = firstResponseItem;
                    const children = Array.from(tempDiv.children);
                    children.forEach(child => {
                        responsesContainer.insertBefore(child, insertBefore);
                    });
                } else {
                    const loadingIndicator = document.getElementById('loadingIndicator');
                    if (loadingIndicator) {
                        const children = Array.from(tempDiv.children);
                        children.forEach(child => {
                            responsesContainer.insertBefore(child, loadingIndicator.nextSibling);
                        });
                    } else {
                        const children = Array.from(tempDiv.children);
                        children.forEach(child => {
                            responsesContainer.appendChild(child);
                        });
                    }
                }

                const scrollHeightAfter = responsesContainer.scrollHeight;
                const heightDiff = scrollHeightAfter - scrollHeightBefore;
                responsesContainer.scrollTop = scrollTopBefore + heightDiff;

                if (typeof initReplyButtons === 'function') {
                    initReplyButtons();
                }
            } else {
                console.log(translations.responseLoadEmpty || 'Response HTML is empty. Stopping load.');
                hasMoreResponses = false;
            }
            
            if (!hasMoreResponses && loadingIndicator) {
                loadingIndicator.style.display = 'none';
            }
        } catch (error) {
            console.error(translations.responseLoadFailed || 'Failed to load responses:', error);
            if (loadingIndicator) {
                loadingIndicator.style.display = 'none';
            }
            hasMoreResponses = false;
        } finally {
            isLoadingResponses = false;
        }
    }

    // 検索モードに入る
    function enterSearchMode() {
        isSearchMode = true;
        isPollingActive = false;
        stopPolling();
        if (responsesContainer) {
            responsesContainer.style.display = 'none';
        }
        if (chatInput) {
            chatInput.style.display = 'none';
        }
        if (searchResultsArea) {
            searchResultsArea.classList.add('active');
        }
    }

    // 検索モードを終了
    function exitSearchMode() {
        isSearchMode = false;
        isPollingActive = true;
        if (!document.hidden) {
            startPolling();
        }
        if (responsesContainer) {
            responsesContainer.style.display = 'block';
        }
        if (chatInput) {
            chatInput.style.display = 'block';
        }
        if (searchResultsArea) {
            searchResultsArea.classList.remove('active');
        }
        if (searchResults) {
            searchResults.style.display = 'none';
        }
    }

    // 検索クエリを解析する関数
    function parseSearchQuery(query) {
        const result = {
            include: [],
            exclude: []
        };
        
        query = query.replace(/　/g, ' ');
        const parts = query.split(' ');
        
        parts.forEach(part => {
            part = part.trim();
            if (part.length === 0) return;
            
            if (part.substring(0, 1) === '-') {
                const excludeWord = part.substring(1);
                if (excludeWord.length > 0) {
                    result.exclude.push(excludeWord);
                }
            } else {
                result.include.push(part);
            }
        });
        
        return result;
    }

    // 検索実行
    async function performSearch(query) {
        const keywords = parseSearchQuery(query);
        const validKeywords = keywords.include.filter(keyword => keyword.length >= 2);
        
        if (validKeywords.length === 0) {
            displaySearchResults([], query);
            return;
        }
        
        const searchTargetRadios = document.querySelectorAll('input[name="searchTarget"]');
        let selectedTarget = 'both';
        searchTargetRadios.forEach(radio => {
            if (radio.checked) {
                selectedTarget = radio.value;
            }
        });
        
        try {
            const response = await fetch(`/threads/${threadId}/responses/search?query=${encodeURIComponent(query)}&target=${selectedTarget}`);
            const data = await response.json();
            
            const results = [];
            data.results.forEach((result, index) => {
                const searchTargetText = (result.body + ' ' + result.user_name).toLowerCase();
                let matchesExclude = true;
                keywords.exclude.forEach(excludeKeyword => {
                    if (excludeKeyword.length >= 2 && searchTargetText.includes(excludeKeyword.toLowerCase())) {
                        matchesExclude = false;
                    }
                });

                if (matchesExclude) {
                    const element = document.querySelector(`[data-response-id="${result.response_id}"]`);
                    
                    results.push({
                        element: element,
                        body: result.body,
                        user: result.display_name,
                        time: result.created_at,
                        index: index,
                        response_id: result.response_id,
                        response_order: result.response_order
                    });
                }
            });

            displaySearchResults(results, query);
        } catch (error) {
            console.error(translations.searchError || 'Search Error:', error);
            displaySearchResults([], query);
        }
    }

    // 検索結果表示
    function displaySearchResults(results, query) {
        if (!isSearchMode) {
            enterSearchMode();
        }

        if (results.length === 0) {
            const keywords = parseSearchQuery(query);
            const validKeywords = keywords.include.filter(keyword => keyword.length >= 2);
            const hasExcludeKeywords = keywords.exclude.filter(keyword => keyword.length >= 2).length > 0;
            
            let message = '';
            if (validKeywords.length === 0 && !hasExcludeKeywords) {
                message = '<p>' + translations.searchMinLengthHint + '</p><p><strong>' + translations.searchHints + '</strong></p><ul class="search-hint-list"><li>' + translations.searchAndHint + '</li><li>' + translations.searchExcludeHint + '</li></ul>';
            } else {
                message = '<p>' + translations.noSearchResults + '</p>';
            }
            
            searchResultsList.innerHTML = '<div class="search-result-response">' + message + '</div>';
        } else {
            searchResultsList.innerHTML = results.map(result => {
                const highlightedUser = highlightText(result.user, query);
                const highlightedBody = highlightText(result.body, query);
                const responseId = result.response_id || (result.element ? result.element.getAttribute('data-response-id') : '');
                const responseOrder = result.response_order !== undefined ? result.response_order : '';
                
                return `<div class="search-result-response" data-index="${result.index}" data-response-id="${responseId}" data-response-order="${responseOrder}"><div class="search-result-value search-result-value-username">${highlightedUser}</div><div class="search-result-value">${highlightedBody}</div><div class="search-result-value">${result.time}</div></div>`;
            }).join('');

            searchResultsList.querySelectorAll('.search-result-response').forEach(item => {
                item.addEventListener('click', function() {
                    const responseId = this.getAttribute('data-response-id');
                    const responseOrder = this.getAttribute('data-response-order');
                    scrollToSearchResult(responseId, responseOrder ? parseInt(responseOrder) : undefined);
                });

                item.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f0f0f0';
                });
                item.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = 'white';
                });
            });
        }

        requestAnimationFrame(() => {
            setTimeout(() => {
                searchResultsArea.scrollTop = searchResultsArea.scrollHeight;
            }, 50);
        });
    }

    // テキストハイライト
    function highlightText(text, query) {
        if (!query) return text;
        
        const keywords = parseSearchQuery(query);
        const allKeywords = keywords.include;
        
        let highlightedText = text;
        allKeywords.forEach(keyword => {
            if (keyword.length >= 2) {
                const escapedKeyword = keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                const regex = new RegExp(`(${escapedKeyword})`, 'gi');
                highlightedText = highlightedText.replace(regex, '<span class="highlight">$1</span>');
            }
        });
        
        return highlightedText;
    }

    // 返信機能のJavaScript
    function initReplyButtons() {
        document.querySelectorAll('.reply-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const responseId = this.getAttribute('data-response-id');
                const userName = this.getAttribute('data-user-name');
                const responseBody = this.getAttribute('data-response-body');
                
                console.log('Reply button clicked:', responseId, userName, responseBody);
                
                showReplyTarget(responseId, userName, responseBody);
                setReplyMode(responseId);
                
                const textarea = document.getElementById('body');
                if (textarea) {
                    textarea.focus();
                }
            });
        });
    }

    // 返信先情報を表示
    function showReplyTarget(responseId, userName, responseBody) {
        const replyTarget = document.getElementById('reply-target');
        const replyTargetUser = replyTarget.querySelector('.reply-target-user');
        const replyTargetBody = replyTarget.querySelector('.reply-target-body');
        
        replyTargetUser.textContent = userName;
        replyTargetBody.textContent = responseBody;
        replyTarget.classList.add('show');
    }

    // 返信モードに設定
    function setReplyMode(responseId) {
        const form = document.getElementById('response-form');
        const parentResponseIdInput = document.getElementById('parent_response_id');
        const textarea = document.getElementById('body');
        
        if (routes.replyRoute) {
            form.action = routes.replyRoute.replace(':responseId', responseId);
        }
        
        parentResponseIdInput.value = responseId;
        textarea.placeholder = translations.replyPlaceholderDetail || '';
    }

    // 返信をキャンセル（グローバル関数）
    window.cancelReply = function() {
        const replyTarget = document.getElementById('reply-target');
        const form = document.getElementById('response-form');
        const parentResponseIdInput = document.getElementById('parent_response_id');
        const textarea = document.getElementById('body');
        
        replyTarget.classList.remove('show');
        
        if (routes.storeRoute) {
            form.action = routes.storeRoute;
        }
        parentResponseIdInput.value = '';
        textarea.placeholder = translations.messagePlaceholder || '';
        textarea.value = '';
    };

    // リプライ元をクリックしたときに該当のリプライにスクロール
    window.scrollToResponse = function(responseId) {
        const targetResponse = document.querySelector(`[data-response-id="${responseId}"]`);
        if (targetResponse) {
            targetResponse.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
            targetResponse.style.backgroundColor = '#fff3cd';
            setTimeout(() => {
                targetResponse.style.backgroundColor = '';
            }, 2000);
        }
    };

    // 検索結果から該当リプライにスクロール（グローバル関数）
    window.scrollToSearchResult = async function(responseId, responseOrder) {
        console.log('scrollToSearchResult called:', { responseId, responseOrder });
        
        isLoadingForSearch = true;
        
        try {
            isSearchMode = false;
            if (responsesContainer) {
                responsesContainer.style.display = 'block';
                void responsesContainer.offsetHeight;
            }
            if (chatInput) {
                chatInput.style.display = 'block';
            }
            if (searchResultsArea) {
                searchResultsArea.classList.remove('active');
            }
            if (searchResults) {
                searchResults.style.display = 'none';
            }
            
            if (searchInput) {
                searchInput.value = '';
            }
            
            await new Promise(resolve => setTimeout(resolve, 300));
            await new Promise(resolve => requestAnimationFrame(resolve));
            await new Promise(resolve => requestAnimationFrame(resolve));
            
            let targetResponse = null;
            if (responsesContainer) {
                targetResponse = responsesContainer.querySelector(`[data-response-id="${responseId}"]`);
            }
            
            if (!targetResponse && responseOrder !== undefined) {
                console.log('Loading responses until target is found...');
                
                const positionFromLatest = totalResponses - responseOrder;
                const requiredOffset = positionFromLatest;
                
                if (currentOffset < requiredOffset && hasMoreResponses) {
                    const maxAttempts = Math.ceil((requiredOffset - currentOffset) / 10) + 5;
                    let attempts = 0;
                    
                    while (currentOffset < requiredOffset && hasMoreResponses && attempts < maxAttempts) {
                        console.log(`Loading attempt ${attempts + 1}/${maxAttempts}...`);
                        await loadMoreResponses();
                        attempts++;
                        await new Promise(resolve => setTimeout(resolve, 400));
                        
                        if (responsesContainer) {
                            targetResponse = responsesContainer.querySelector(`[data-response-id="${responseId}"]`);
                        }
                        
                        if (targetResponse) {
                            console.log('Target found! Breaking loading loop.');
                            break;
                        }
                    }
                    
                    if (!targetResponse) {
                        await new Promise(resolve => setTimeout(resolve, 300));
                        if (responsesContainer) {
                            targetResponse = responsesContainer.querySelector(`[data-response-id="${responseId}"]`);
                        }
                    }
                }
            }
            
            if (responsesContainer) {
                targetResponse = responsesContainer.querySelector(`[data-response-id="${responseId}"]`);
            } else {
                targetResponse = document.querySelector(`#responsesContainer [data-response-id="${responseId}"]`);
            }
            
            if (targetResponse) {
                console.log('Scrolling to target response');
                
                if (responsesContainer) {
                    await new Promise(resolve => requestAnimationFrame(resolve));
                    
                    let attempts = 0;
                    while (attempts < 15) {
                        targetResponse = responsesContainer.querySelector(`[data-response-id="${responseId}"]`);
                        
                        if (targetResponse && targetResponse.offsetParent !== null && targetResponse.offsetHeight > 0) {
                            console.log(`Element rendered successfully after ${attempts + 1} attempts`);
                            break;
                        }
                        
                        await new Promise(resolve => setTimeout(resolve, 150));
                        attempts++;
                    }
                    
                    let targetRect = targetResponse.getBoundingClientRect();
                    
                    if (targetRect.height === 0 || targetResponse.offsetHeight === 0) {
                        console.warn('Target element still not rendered after waiting.');
                    }
                    
                    const currentScrollTop = responsesContainer.scrollTop;
                    const searchContainer = document.querySelector('.search-container');
                    const searchContainerHeight = searchContainer ? searchContainer.offsetHeight : 0;
                    
                    let targetTopInContainer, effectiveTargetHeight;
                    
                    if (targetResponse.offsetHeight > 0) {
                        targetTopInContainer = targetResponse.offsetTop;
                        effectiveTargetHeight = targetResponse.offsetHeight;
                    } else if (targetRect.height > 0) {
                        const containerRect = responsesContainer.getBoundingClientRect();
                        targetTopInContainer = (targetRect.top - containerRect.top) + currentScrollTop;
                        effectiveTargetHeight = targetRect.height;
                    } else {
                        targetTopInContainer = 0;
                        effectiveTargetHeight = 100;
                    }
                    
                    let scrollPosition;
                    if (searchContainerHeight > 0) {
                        scrollPosition = targetTopInContainer - (searchContainerHeight + 150);
                    } else {
                        scrollPosition = targetTopInContainer - 100;
                    }
                    
                    try {
                        if (typeof responsesContainer.scrollTo === 'function') {
                            responsesContainer.scrollTo({
                                top: scrollPosition,
                                behavior: 'smooth'
                            });
                        } else {
                            responsesContainer.scrollTop = scrollPosition;
                        }
                        
                        await new Promise(resolve => setTimeout(resolve, 100));
                    } catch (error) {
                        console.error('Scroll error:', error);
                        responsesContainer.scrollTop = scrollPosition;
                    }
                }
                
                let elementToHighlight = targetResponse;
                if (!targetResponse.classList.contains('response-item')) {
                    elementToHighlight = targetResponse.closest('.response-item');
                }
                
                if (elementToHighlight) {
                    elementToHighlight.classList.add('highlighted');
                    setTimeout(() => {
                        elementToHighlight.classList.remove('highlighted');
                    }, 2000);
                }
            } else {
                console.warn('Target response not found, scrolling to top');
                if (responsesContainer) {
                    responsesContainer.scrollTop = 0;
                }
            }
        } finally {
            isLoadingForSearch = false;
            console.log('isLoadingForSearch flag cleared');
        }
    };

    // メディアファイル選択と検証
    function initMediaFileHandlers() {
        const mediaFileBtn = document.getElementById('media-file-btn');
        const mediaFileInput = document.getElementById('media_file');
        const mediaFileName = document.getElementById('media-file-name');
        const responseForm = document.getElementById('response-form');
        const bodyTextarea = document.getElementById('body');

        if (!mediaFileBtn || !mediaFileInput) {
            return;
        }

        function showMediaError(message) {
            if (!bodyTextarea) {
                return;
            }
            
            const parent = bodyTextarea.parentElement;
            if (parent) {
                const existingError = parent.querySelector('.alert.alert-danger');
                if (existingError && existingError.id !== 'body-error') {
                    existingError.remove();
                }

                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger media-error';
                errorDiv.textContent = message;
                parent.insertBefore(errorDiv, bodyTextarea);
            }
        }

        function clearMediaError() {
            if (!bodyTextarea) {
                return;
            }
            
            const parent = bodyTextarea.parentElement;
            if (parent) {
                const errorDivs = parent.querySelectorAll('.alert.alert-danger');
                errorDivs.forEach(function(div) {
                    if (div.id !== 'body-error') {
                        div.remove();
                    }
                });
            }
        }

        mediaFileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (mediaFileInput) {
                mediaFileInput.click();
            }
        });

        mediaFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            clearMediaError();

            if (!file) {
                mediaFileName.style.display = 'none';
                return;
            }

            const allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
            const allowedVideoTypes = ['video/mp4', 'video/webm'];
            const allowedAudioTypes = ['audio/mpeg', 'audio/mp4', 'audio/webm', 'audio/webm;codecs=opus'];
            const allowedTypes = [...allowedImageTypes, ...allowedVideoTypes, ...allowedAudioTypes];
            
            const fileNameLower = file.name.toLowerCase();
            const lastDotIndex = fileNameLower.lastIndexOf('.');
            const extension = lastDotIndex !== -1 ? fileNameLower.substring(lastDotIndex + 1) : '';
            const allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            const allowedVideoExtensions = ['mp4', 'webm'];
            const allowedAudioExtensions = ['mp3', 'm4a', 'webm'];
            const allowedExtensions = [...allowedImageExtensions, ...allowedVideoExtensions, ...allowedAudioExtensions];
            
            const isValidMimeType = allowedTypes.includes(file.type);
            const isValidExtension = extension && allowedExtensions.includes(extension);
            
            if (!isValidMimeType && !isValidExtension) {
                showMediaError(translations.fileFormatNotAllowed);
                mediaFileInput.value = '';
                mediaFileName.style.display = 'none';
                return;
            }

            const maxImageSize = 1.5 * 1024 * 1024;
            const maxVideoSize = 10 * 1024 * 1024;
            const maxAudioSize = 5 * 1024 * 1024;

            let maxSize = 0;
            let maxSizeMB = 0;
            if (allowedImageTypes.includes(file.type)) {
                maxSize = maxImageSize;
                maxSizeMB = 1.5;
            } else if (allowedVideoTypes.includes(file.type)) {
                maxSize = maxVideoSize;
                maxSizeMB = 10;
            } else if (allowedAudioTypes.includes(file.type)) {
                maxSize = maxAudioSize;
                maxSizeMB = 5;
            }

            if (file.size > phpUploadMaxSize) {
                const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
                const phpMaxMB = (phpUploadMaxSize / (1024 * 1024)).toFixed(0);
                const errorMsg = translations.fileSizeExceedsPhpLimit
                    .replace(':phpMaxMB', phpMaxMB)
                    .replace(':fileSizeMB', fileSizeMB);
                showMediaError(errorMsg);
                mediaFileInput.value = '';
                mediaFileName.style.display = 'none';
                return;
            }

            if (file.size > maxSize) {
                let fileTypeName = '';
                if (allowedImageTypes.includes(file.type)) {
                    fileTypeName = translations.imageFile;
                } else if (allowedVideoTypes.includes(file.type)) {
                    fileTypeName = translations.videoFile;
                } else if (allowedAudioTypes.includes(file.type)) {
                    fileTypeName = translations.audioFile;
                }
                
                const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
                const errorMsg = translations.fileSizeTooLarge
                    .replace(':fileType', fileTypeName)
                    .replace(':fileSizeMB', fileSizeMB)
                    .replace(':maxSizeMB', maxSizeMB);
                showMediaError(errorMsg);
                mediaFileInput.value = '';
                mediaFileName.style.display = 'none';
                return;
            }

            clearMediaError();
            const displayFileName = file.name.length > 20 ? file.name.substring(0, 20) + '...' : file.name;
            const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
            let fileTypeLabel = '';
            if (allowedImageTypes.includes(file.type)) {
                fileTypeLabel = translations.imageMaxSize;
            } else if (allowedVideoTypes.includes(file.type)) {
                fileTypeLabel = translations.videoMaxSize;
            } else if (allowedAudioTypes.includes(file.type)) {
                fileTypeLabel = translations.audioMaxSize;
            }
            mediaFileName.innerHTML = `${displayFileName} <span class="media-file-info-text">(${fileSizeMB}MB, ${fileTypeLabel})</span>`;
            mediaFileName.style.display = 'block';
        });

        if (responseForm) {
            responseForm.addEventListener('submit', function(e) {
                const submitBtn = responseForm.querySelector('button[type="submit"]');
                if (submitBtn && submitBtn.disabled) {
                    e.preventDefault();
                    return false;
                }
                const body = document.getElementById('body').value.trim();
                const mediaFile = document.getElementById('media_file').files[0];

                if (!body && !mediaFile) {
                    e.preventDefault();
                    showMediaError(translations.messageOrFileRequired || translations.messageOrFileRequired);
                    return false;
                }
                
                if (mediaFile) {
                    const allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
                    const allowedVideoTypes = ['video/mp4', 'video/webm'];
                    const allowedAudioTypes = ['audio/mpeg', 'audio/mp4', 'audio/webm', 'audio/webm;codecs=opus'];
                    const allowedTypes = [...allowedImageTypes, ...allowedVideoTypes, ...allowedAudioTypes];
                    
                    const mediaFileNameLower = mediaFile.name.toLowerCase();
                    const lastDotIndex = mediaFileNameLower.lastIndexOf('.');
                    const extension = lastDotIndex !== -1 ? mediaFileNameLower.substring(lastDotIndex + 1) : '';
                    const allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                    const allowedVideoExtensions = ['mp4', 'webm'];
                    const allowedAudioExtensions = ['mp3', 'm4a', 'webm'];
                    const allowedExtensions = [...allowedImageExtensions, ...allowedVideoExtensions, ...allowedAudioExtensions];
                    
                    const isValidMimeType = allowedTypes.includes(mediaFile.type);
                    const isValidExtension = extension && allowedExtensions.includes(extension);
                    
                    if (!isValidMimeType && !isValidExtension) {
                        e.preventDefault();
                        showMediaError(translations.fileFormatNotAllowed);
                        return false;
                    }
                    
                    const maxImageSize = 1.5 * 1024 * 1024;
                    const maxVideoSize = 10 * 1024 * 1024;
                    const maxAudioSize = 5 * 1024 * 1024;
                    
                    if (mediaFile.size > phpUploadMaxSize) {
                        e.preventDefault();
                        const fileSizeMB = (mediaFile.size / (1024 * 1024)).toFixed(2);
                        const phpMaxMB = (phpUploadMaxSize / (1024 * 1024)).toFixed(0);
                        const errorMsg = translations.fileSizeExceedsPhpLimit
                            ? translations.fileSizeExceedsPhpLimit
                                .replace(':phpMaxMB', phpMaxMB)
                                .replace(':fileSizeMB', fileSizeMB)
                            : `File size exceeds PHP configuration limit (${phpMaxMB}MB). Selected file: ${fileSizeMB}MB. Please check server settings.`;
                        showMediaError(errorMsg);
                        return false;
                    }
                    
                    let maxSize = 0;
                    let maxSizeMB = 0;
                    let fileTypeName = '';
                    if (allowedImageTypes.includes(mediaFile.type)) {
                        maxSize = maxImageSize;
                        maxSizeMB = 1.5;
                        fileTypeName = translations.imageFile;
                    } else if (allowedVideoTypes.includes(mediaFile.type)) {
                        maxSize = maxVideoSize;
                        maxSizeMB = 10;
                        fileTypeName = translations.videoFile;
                    } else if (allowedAudioTypes.includes(mediaFile.type)) {
                        maxSize = maxAudioSize;
                        maxSizeMB = 5;
                        fileTypeName = translations.audioFile;
                    }
                    
                    if (mediaFile.size > maxSize) {
                        e.preventDefault();
                        const fileSizeMB = (mediaFile.size / (1024 * 1024)).toFixed(2);
                        const errorMsg = translations.fileSizeTooLarge
                            .replace(':fileType', fileTypeName)
                            .replace(':fileSizeMB', fileSizeMB)
                            .replace(':maxSizeMB', maxSizeMB);
                        showMediaError(errorMsg);
                        return false;
                    }
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = translations.submitting || '送信中';
                    submitBtn.style.background = '#b0b0b0';
                    submitBtn.style.backgroundColor = '#b0b0b0';
                    submitBtn.style.color = '#666';
                    submitBtn.style.cursor = 'not-allowed';
                }
            });
        }
    }

    // 画像モーダル表示
    window.openImageModal = function(imageSrc) {
        let modal = document.getElementById('image-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'image-modal';
            modal.className = 'image-modal';
            modal.onclick = function() {
                window.closeImageModal();
            };
            document.body.appendChild(modal);
        }
        
        let img = modal.querySelector('.image-modal-content');
        if (!img) {
            img = document.createElement('img');
            img.className = 'image-modal-content';
            modal.appendChild(img);
        }
        
        img.src = imageSrc;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    // 画像モーダルを閉じる
    window.closeImageModal = function() {
        const modal = document.getElementById('image-modal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    // ESCキーでモーダルを閉じる
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.closeImageModal();
        }
    });

    // 動画のサムネイルを生成
    function generateVideoThumbnail(video) {
        function showFirstFrame() {
            video.currentTime = 0.1;
            video.addEventListener('seeked', function() {
            }, { once: true });
        }

        if (video.readyState >= 2) {
            showFirstFrame();
        } else {
            video.addEventListener('loadedmetadata', showFirstFrame, { once: true });
        }
    }

    // 動画再生切り替え
    window.toggleVideoPlay = function(videoElement) {
        if (videoElement.paused) {
            videoElement.play();
            videoElement.classList.add('playing');
            videoElement.controls = true;
        } else {
            videoElement.pause();
            videoElement.classList.remove('playing');
            videoElement.controls = false;
        }
    };

    // 広告動画視聴処理
    window.watchAdFromThread = function() {
        window.watchAdVideo({
            modalId: 'adVideoModalThread',
            videoId: 'adVideoThread',
            statusId: 'adWatchStatusThread',
            btnId: 'watchAdBtnThread',
            translations: translations,
            csrfToken: csrfToken,
            watchAdRoute: routes.watchAdRoute || '/coins/watch-ad',
            onSuccess: function(coins) {
                playCoinRouletteThread(coins);
            },
            onClose: function() {
                window.closeAdVideoFromThread();
            }
        });
    };

    window.closeAdVideoFromThread = function() {
        const modal = document.getElementById('adVideoModalThread');
        const video = document.getElementById('adVideoThread');
        if (!modal || !video) return;
        modal.style.display = 'none';
        video.pause();
        video.currentTime = 0;
    };

    // 続きルーム要望のトグル
    window.toggleContinuationRequest = function(threadId) {
        const btn = document.getElementById('continuation-request-btn');
        const countEl = document.getElementById('continuation-request-count');
        const ownerStatusEl = document.getElementById('thread-owner-request-status');
        
        if (!btn || !countEl) return;

        btn.disabled = true;
        const originalText = btn.textContent;

        fetch(`/threads/${threadId}/continuation-request`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                btn.disabled = false;
                return;
            }

            const requestText = translations.continuationRequestButton;
            const cancelText = translations.continuationRequestButtonCancel;
            const limitReachedText = translations.continuationRequestLimitReached;
            const isLimitReached = data.request_count >= continuationRequestThreshold;
            
            if (data.action === 'added') {
                btn.textContent = cancelText;
                btn.style.background = '#28a745';
                btn.style.color = 'white';
            } else {
                if (isLimitReached && !data.has_user_request && !isCurrentUserThreadOwner) {
                    btn.textContent = limitReachedText;
                    btn.style.background = '#6c757d';
                    btn.style.color = '#ffffff';
                    btn.disabled = true;
                    btn.style.cursor = 'not-allowed';
                    btn.style.opacity = '0.6';
                } else {
                    btn.textContent = requestText;
                    btn.style.background = '#ffc107';
                    btn.style.color = '#856404';
                    btn.disabled = false;
                    btn.style.cursor = 'pointer';
                    btn.style.opacity = '1';
                }
            }

            const countTemplate = translations.continuationRequestCount;
            const countText = countTemplate.replace(':count', data.request_count) + ` / ${continuationRequestThreshold}`;
            countEl.textContent = countText;
            
            if (ownerStatusEl) {
                if (data.has_owner_request) {
                    ownerStatusEl.textContent = translations.threadOwnerRequested;
                    ownerStatusEl.style.color = '#28a745';
                } else {
                    ownerStatusEl.textContent = translations.threadOwnerNotRequested;
                    ownerStatusEl.style.color = '#6c757d';
                }
            }

            if (isCurrentUserThreadOwner || !isLimitReached || data.has_user_request) {
                btn.disabled = false;
            }

            if (data.continuation_created) {
                const message = translations.continuationThreadCreated;
                
                const toast = document.createElement('div');
                toast.className = 'alert alert-success toast-message';
                toast.textContent = message;
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    location.reload();
                }, 3000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(translations.errorOccurred);
            btn.disabled = false;
        });
    };

    // ルーレットアニメーション
    function playCoinRouletteThread(finalCoins) {
        window.playCoinRoulette({
            overlayId: 'coinRouletteOverlayThread',
            valueId: 'coinRouletteValueThread',
            messageId: 'coinRouletteMessageThread',
            okBtnId: 'coinRouletteOkButtonThread',
            skipBtnId: 'coinRouletteSkipButtonThread',
            finalCoins: finalCoins,
            translations: translations
        });
    }

    // 最新のレスポンスIDを取得
    function getLatestResponseId() {
        const responsesContainer = document.getElementById('responsesContainer');
        if (!responsesContainer) return 0;
        
        const responseItems = responsesContainer.querySelectorAll('[data-response-id]');
        let maxId = 0;
        
        responseItems.forEach(item => {
            const responseId = parseInt(item.getAttribute('data-response-id'), 10);
            if (responseId && responseId > maxId) {
                maxId = responseId;
            }
        });
        
        return maxId;
    }

    // 新しいレスポンスを取得して表示
    async function checkForNewResponses() {
        if (!isPollingActive || isLoadingForSearch || isSearchMode) {
            return;
        }

        // threadIdが0の場合は設定が読み込まれていないので停止
        if (!threadId || threadId === 0) {
            console.error('[リアルタイム更新] エラー: threadIdが設定されていません');
            stopPolling();
            return;
        }

        // lastResponseIdが0の場合は、初回なので現在の最新IDを設定
        if (lastResponseId === 0) {
            const currentLatestId = getLatestResponseId();
            if (currentLatestId > 0) {
                lastResponseId = currentLatestId;
                console.log('[リアルタイム更新] 初期化: 最新レスポンスID =', lastResponseId);
            } else {
                console.log('[リアルタイム更新] 初期化: レスポンスが見つかりませんでした');
            }
            // 初回はサーバーに問い合わせない（次回から開始）
            return;
        }

        try {
            console.log('[リアルタイム更新] 新しいレスポンスをチェック中... (threadId:', threadId, ', lastResponseId:', lastResponseId, ')');
            const response = await fetch(`/threads/${threadId}/responses/new?last_response_id=${lastResponseId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                console.error('[リアルタイム更新] エラー: HTTP', response.status);
                return;
            }

            const data = await response.json();

            if (data.html && data.html.trim() !== '') {
                console.log('[リアルタイム更新] 新しいレスポンスが見つかりました');
                const responsesContainer = document.getElementById('responsesContainer');
                if (!responsesContainer) return;

                // 現在のスクロール位置を記録
                const wasAtBottom = responsesContainer.scrollHeight - responsesContainer.scrollTop <= responsesContainer.clientHeight + 100;
                const scrollHeightBefore = responsesContainer.scrollHeight;

                // 新しいレスポンスを追加
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data.html;
                const children = Array.from(tempDiv.children);
                
                children.forEach(child => {
                    responsesContainer.appendChild(child);
                });

                // 返信ボタンの初期化
                if (typeof initReplyButtons === 'function') {
                    initReplyButtons();
                }

                // 動画サムネイルの生成
                const newVideoThumbnails = responsesContainer.querySelectorAll('.media-video-thumbnail');
                newVideoThumbnails.forEach(function(video) {
                    generateVideoThumbnail(video);
                });

                // スクロール位置を調整
                if (wasAtBottom) {
                    // 元々一番下にいた場合は、新しいレスポンスの後にスクロール
                    responsesContainer.scrollTop = responsesContainer.scrollHeight;
                } else {
                    // それ以外の場合は、スクロール位置を維持
                    const scrollHeightAfter = responsesContainer.scrollHeight;
                    const heightDiff = scrollHeightAfter - scrollHeightBefore;
                    responsesContainer.scrollTop += heightDiff;
                }

                // 最新のレスポンスIDを更新
                if (data.latest_response_id) {
                    lastResponseId = data.latest_response_id;
                    console.log('[リアルタイム更新] 最新レスポンスIDを更新:', lastResponseId);
                } else {
                    lastResponseId = getLatestResponseId();
                    console.log('[リアルタイム更新] DOMから最新レスポンスIDを取得:', lastResponseId);
                }
            } else if (data.latest_response_id && data.latest_response_id > lastResponseId) {
                // HTMLがなくても、最新のレスポンスIDを更新（他のユーザーが削除した場合など）
                lastResponseId = data.latest_response_id;
                console.log('[リアルタイム更新] 最新レスポンスIDを更新（HTMLなし）:', lastResponseId);
            } else {
                console.log('[リアルタイム更新] 新しいレスポンスはありません');
            }
        } catch (error) {
            console.error('[リアルタイム更新] エラー:', error);
        }
    }

    // ポーリングを開始
    function startPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }
        
        // threadIdが0の場合は設定が読み込まれていないので停止
        if (!threadId || threadId === 0) {
            console.error('[リアルタイム更新] エラー: threadIdが設定されていません。ポーリングを開始できません。');
            return;
        }
        
        console.log('[リアルタイム更新] ポーリングを開始します (threadId:', threadId, ')');
        
        // 初回の最新レスポンスIDを設定（DOMが読み込まれた後）
        setTimeout(() => {
            const currentLatestId = getLatestResponseId();
            if (currentLatestId > 0 && lastResponseId === 0) {
                lastResponseId = currentLatestId;
                console.log('[リアルタイム更新] 初期化: 最新レスポンスID =', lastResponseId);
            }
        }, 500);
        
        // 3秒ごとに新しいレスポンスをチェック
        pollingInterval = setInterval(checkForNewResponses, 3000);
    }

    // ポーリングを停止
    function stopPolling() {
        if (pollingInterval) {
            console.log('[リアルタイム更新] ポーリングを停止します');
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }

    // ページの可視性が変わったときにポーリングを制御
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            isPollingActive = false;
            stopPolling();
        } else {
            isPollingActive = true;
            startPolling();
        }
    });

    // DOMContentLoaded時の初期化
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(scrollToBottom, 100);
        setTimeout(scrollToBottom, 500);

        responsesContainer = document.getElementById('responsesContainer');
        if (responsesContainer) {
            const loadingIndicator = document.createElement('div');
            loadingIndicator.id = 'loadingIndicator';
            loadingIndicator.textContent = translations.loading || 'Loading...';
            
            const firstChild = responsesContainer.firstElementChild;
            if (firstChild) {
                responsesContainer.insertBefore(loadingIndicator, firstChild);
            } else {
                responsesContainer.appendChild(loadingIndicator);
            }

            function checkAndLoadMore() {
                if (responsesContainer.scrollTop <= 100 && hasMoreResponses && !isLoadingResponses) {
                    console.log('Auto-loading more responses (initial check)...');
                    loadMoreResponses();
                }
            }

            responsesContainer.addEventListener('scroll', function() {
                if (isLoadingForSearch) {
                    console.log('Skipping auto-load: loading for search result');
                    return;
                }
                
                if (responsesContainer.scrollTop <= 100 && hasMoreResponses && !isLoadingResponses) {
                    console.log('Loading more responses...');
                    loadMoreResponses();
                }
            });

            setTimeout(function() {
                checkAndLoadMore();
            }, 1000);
        }

        // 検索機能の初期化
        searchInput = document.getElementById('searchInput');
        searchResults = document.getElementById('searchResults');
        searchResultsArea = document.getElementById('searchResultsArea');
        searchResultsList = document.getElementById('searchResultsList');
        // responsesContainerは上で既に設定済み
        chatInput = document.querySelector('.chat-input');
        let searchTimeout;

        if (searchInput && searchResults && searchResultsArea && searchResultsList && responsesContainer) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length === 0) {
                    exitSearchMode();
                    return;
                }

                searchTimeout = setTimeout(() => {
                    performSearch(query);
                }, 300);
            });

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const query = this.value.trim();
                    if (query.length > 0) {
                        performSearch(query);
                    }
                }
            });

            document.querySelectorAll('input[name="searchTarget"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const query = searchInput.value.trim();
                    if (query.length > 0) {
                        performSearch(query);
                    }
                });
            });

            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.style.display = 'none';
                }
            });

            initReplyButtons();
        }

        // ルーム名の原文/訳文トグル
        const titleBtn = document.querySelector('.show-original-title-btn');
        if (titleBtn) {
            const titleText = document.querySelector('.thread-title-text');
            const displayTitle = titleBtn.getAttribute('data-display-title');
            const originalTitle = titleBtn.getAttribute('data-original-title');
            if (titleText && displayTitle !== null && originalTitle !== null) {
                titleBtn.addEventListener('click', function() {
                    const showingOriginal = titleBtn.textContent === translations.show_translation;
                    if (showingOriginal) {
                        titleText.textContent = displayTitle;
                        titleBtn.textContent = translations.show_original;
                    } else {
                        titleText.textContent = originalTitle;
                        titleBtn.textContent = translations.show_translation;
                    }
                });
            }
        }

        // リプライ本文の原文/訳文トグル（イベント委譲で動的追加リプライにも対応）
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.show-original-response-btn');
            if (!btn) return;
            e.preventDefault();
            const wrapper = btn.closest('.response-body-wrapper');
            if (!wrapper) return;
            const displayEl = wrapper.querySelector('.response-body-display');
            const originalEl = wrapper.querySelector('.response-body-original');
            if (!displayEl || !originalEl) return;
            const showingOriginal = displayEl.classList.contains('response-body-hidden');
            if (showingOriginal) {
                displayEl.classList.remove('response-body-hidden');
                displayEl.classList.add('response-body-visible');
                originalEl.classList.add('response-body-hidden');
                originalEl.classList.remove('response-body-visible');
                btn.textContent = translations.show_original;
            } else {
                displayEl.classList.add('response-body-hidden');
                displayEl.classList.remove('response-body-visible');
                originalEl.classList.remove('response-body-hidden');
                originalEl.classList.add('response-body-visible');
                btn.textContent = translations.show_translation;
            }
        });

        // 画像プレビュークリック（イベント委譲・CSP対応でインラインonclickを使わない）
        document.addEventListener('click', function(e) {
            const preview = e.target.closest('.media-preview-image');
            if (!preview) return;
            e.preventDefault();
            const url = preview.getAttribute('data-image-url');
            if (url) window.openImageModal(url);
        });
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const preview = e.target.closest('.media-preview-image');
            if (!preview) return;
            e.preventDefault();
            const url = preview.getAttribute('data-image-url');
            if (url) window.openImageModal(url);
        });

        // メディアファイルハンドラーの初期化
        initMediaFileHandlers();

        // 動画サムネイルの生成
        const videoThumbnails = document.querySelectorAll('.media-video-thumbnail');
        videoThumbnails.forEach(function(video) {
            generateVideoThumbnail(video);
        });

        // リアルタイム更新のポーリングを開始
        startPolling();
    });

    window.addEventListener('load', function() {
        setTimeout(scrollToBottom, 100);
        // ページ読み込み後にポーリングを開始
        if (!pollingInterval) {
            startPolling();
        }
    });

    // ページを離れる前にポーリングを停止
    window.addEventListener('beforeunload', function() {
        stopPolling();
    });
})();
