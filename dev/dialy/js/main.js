$(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content') || '';
    let currentDate = toYmd(new Date());
    let currentSearch = '';
    let allTags = [];
    let selectedTags = [];
    let loadedTweets = [];
    let isLoading = false;
    let hasMore = true;

    $.ajaxSetup({
        beforeSend: function (xhr, settings) {
            if ((settings.type || settings.method || '').toUpperCase() === 'POST') {
                xhr.setRequestHeader('X-CSRF-Token', csrfToken);
            }
        }
    });

    setCurrentDate(currentDate, false);
    fetchAllTags(function () {
        renderTagSuggestions();
        loadTweets(false);
    });

    $('#calendarBtn').on('click', function () {
        const calendarInput = $('#calendarInput')[0];
        if (calendarInput.showPicker) {
            calendarInput.showPicker();
        } else {
            calendarInput.focus();
            calendarInput.click();
        }
    });

    $('#prevDateBtn').on('click', function () {
        shiftDate(-1);
    });

    $('#nextDateBtn').on('click', function () {
        shiftDate(1);
    });

    $('#calendarInput').on('change', function () {
        if (this.value) {
            setCurrentDate(this.value, true);
        }
    });

    $('#searchToggleBtn').on('click', function () {
        $('#searchBarContainer').slideToggle(200, function () {
            if ($(this).is(':visible')) {
                $('#searchInput').trigger('focus');
            }
        });
    });

    $('#searchInput').on('keydown', function (e) {
        if ((e.key === 'Enter' || e.keyCode === 13) && !e.isComposing) {
            e.preventDefault();
            currentSearch = $(this).val().trim();
            $('#searchClearBtn').toggle(currentSearch !== '');
            loadTweets(false);
        }
    });

    $('#searchClearBtn').on('click', function () {
        $('#searchInput').val('');
        currentSearch = '';
        $(this).hide();
        loadTweets(false);
    });

    $('#tagsInput').on('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ',' || e.keyCode === 13) && !e.isComposing) {
            e.preventDefault();
            addComposerTag($(this).val());
            $(this).val('');
        }

        if (e.key === 'Backspace' && $(this).val() === '' && selectedTags.length > 0) {
            selectedTags.pop();
            renderComposerTags();
        }
    });

    $('#tagsInput').on('blur', function () {
        addComposerTag($(this).val());
        $(this).val('');
    });

    $('#composerTagChips').on('click', '.composerTagRemove', function () {
        const tagName = $(this).data('tag-name');
        selectedTags = selectedTags.filter(function (tag) {
            return tag !== tagName;
        });
        renderComposerTags();
    });

    $('#tagSuggestions').on('click', '.tagSuggestionBtn', function () {
        addComposerTag($(this).data('tag-name'));
    });

    window.triggerLoadMore = function () {
        loadTweets(true);
    };

    window.adjustScroll = function () {
        const chatFeed = $('#chatFeed')[0];
        if (chatFeed.scrollTop + chatFeed.clientHeight >= chatFeed.scrollHeight - 500) {
            chatFeed.scrollTop = chatFeed.scrollHeight;
        }
    };

    $('#chatFeed').on('scroll', function () {
        if ($(this).scrollTop() === 0 && hasMore && !isLoading && loadedTweets.length > 0) {
            loadTweets(true);
        }
    });

    $('#tweetText').on('input', function () {
        checkSubmitState();
        $(this).css('height', '46px');
        $(this).css('height', Math.min(this.scrollHeight, 150) + 'px');
    });

    $('#tweetText').on('keydown', function (e) {
        const isPc = window.matchMedia('(any-hover: hover)').matches;
        if (isPc && e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!$('#submitBtn').prop('disabled')) {
                $('#tweetForm').trigger('submit');
            }
        }
    });

    $('#imageUpload').on('change', function () {
        if (!this.files || !this.files[0]) {
            checkSubmitState();
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            $('#imagePreview').attr('src', e.target.result);
            $('#imagePreviewContainer').show();
            checkSubmitState();
        };
        reader.readAsDataURL(this.files[0]);
    });

    $('#removeImageBtn').on('click', function () {
        $('#imageUpload').val('');
        $('#imagePreview').attr('src', '');
        $('#imagePreviewContainer').hide();
        checkSubmitState();
    });

    $('#tweetForm').on('submit', function (e) {
        e.preventDefault();
        syncTagsInput();

        const formData = new FormData(this);
        $('#submitBtn').prop('disabled', true);

        $.ajax({
            url: 'api/postTweet.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                if (response.success && response.tweet) {
                    loadedTweets.push(response.tweet);
                    renderAllTweets();
                    scrollToBottom();
                    resetComposer();
                    fetchAllTags(renderTagSuggestions);
                } else {
                    alert('投稿に失敗しました: ' + (response.error || '不明なエラー'));
                    checkSubmitState();
                }
            },
            error: function () {
                alert('通信エラーが発生しました。');
                checkSubmitState();
            }
        });
    });

    $('#chatFeed').on('click', '.tweetMenuBtn', function (e) {
        e.stopPropagation();
        const $menu = $(this).siblings('.tweetMenu');
        $('.tweetMenu').not($menu).removeClass('isOpen');
        $menu.toggleClass('isOpen');
    });

    $(document).on('click', function () {
        $('.tweetMenu').removeClass('isOpen');
    });

    $('#chatFeed').on('click', '.deleteBtn', function (e) {
        e.stopPropagation();
        const tweetId = $(this).data('id');
        $('.tweetMenu').removeClass('isOpen');

        if (!confirm('この記録を削除しますか？')) {
            return;
        }

        $.ajax({
            url: 'api/deleteTweet.php',
            method: 'POST',
            data: { tweetId: tweetId },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#tweet-' + tweetId).fadeOut(300, function () {
                        $(this).remove();
                        loadedTweets = loadedTweets.filter(function (tweet) {
                            return String(tweet.id) !== String(tweetId);
                        });
                    });
                } else {
                    alert('削除に失敗しました: ' + (response.error || '不明なエラー'));
                }
            },
            error: function () {
                alert('通信エラーが発生しました。');
            }
        });
    });

    $('#chatFeed').on('change', '.addTagSelect', function () {
        const tagId = $(this).val();
        const tweetId = $(this).data('tweet-id');
        const $select = $(this);

        if (!tagId) {
            return;
        }

        $select.prop('disabled', true);

        $.ajax({
            url: 'api/addTagToTweet.php',
            method: 'POST',
            data: { tweetId: tweetId, tagId: tagId },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.tags) {
                    updateTweetTags(tweetId, response.tags);
                } else {
                    alert('タグの追加に失敗しました。');
                    $select.prop('disabled', false).val('');
                }
            },
            error: function () {
                alert('通信エラーが発生しました。');
                $select.prop('disabled', false).val('');
            }
        });
    });

    $('#chatFeed').on('click', '.removeTagBtn', function () {
        const tweetId = $(this).data('tweet-id');
        const tagName = $(this).data('tag-name');
        const $button = $(this);

        $button.prop('disabled', true);

        $.ajax({
            url: 'api/removeTagFromTweet.php',
            method: 'POST',
            data: { tweetId: tweetId, tagName: tagName },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.tags) {
                    updateTweetTags(tweetId, response.tags);
                    fetchAllTags(renderTagSuggestions);
                } else {
                    alert('タグの削除に失敗しました。');
                    $button.prop('disabled', false);
                }
            },
            error: function () {
                alert('通信エラーが発生しました。');
                $button.prop('disabled', false);
            }
        });
    });

    $('#chatFeed').on('click', '.tweetImage', function () {
        $('#modalImage').attr('src', $(this).attr('src'));
        $('#imageModal').css('display', 'flex').hide().fadeIn(200);
    });

    $('#imageModal').on('click', function (e) {
        if (e.target !== $('#modalImage')[0]) {
            $(this).fadeOut(200);
        }
    });

    $('.closeModalBtn').on('click', function () {
        $('#imageModal').fadeOut(200);
    });

    function setCurrentDate(ymd, shouldLoad) {
        currentDate = ymd;
        $('#calendarInput').val(ymd);
        $('#currentDateDisplay').text(formatYmdText(ymd));
        if (shouldLoad) {
            clearSearch();
            loadTweets(false);
        }
    }

    function shiftDate(days) {
        const date = parseYmd(currentDate);
        date.setDate(date.getDate() + days);
        setCurrentDate(toYmd(date), true);
    }

    function clearSearch() {
        $('#searchInput').val('');
        currentSearch = '';
        $('#searchClearBtn').hide();
        $('#searchBarContainer').slideUp(200);
    }

    function fetchAllTags(callback) {
        $.ajax({
            url: 'api/getTags.php',
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.tags) {
                    allTags = response.tags;
                }
                if (callback) callback();
            },
            error: function () {
                if (callback) callback();
            }
        });
    }

    function loadTweets(isAppend) {
        if (isLoading || (isAppend && !hasMore)) {
            return;
        }

        isLoading = true;

        if (!isAppend) {
            hasMore = true;
            $('#chatFeed').html('<div class="loading">Loading...</div>');
        }

        let url = 'api/getTweets.php?';

        if (currentSearch !== '') {
            url += 'search=' + encodeURIComponent(currentSearch);
            if (isAppend && loadedTweets.length > 0) {
                url += '&before_id=' + loadedTweets[0].id;
            }
        } else if (isAppend) {
            if (loadedTweets.length > 0) {
                url += 'before_id=' + loadedTweets[0].id;
            } else if (currentDate) {
                url += 'before_date=' + currentDate;
            }
        } else if (currentDate) {
            url += 'date=' + currentDate;
        }

        const topTweetId = isAppend && loadedTweets.length > 0 ? loadedTweets[0].id : null;
        if (topTweetId) {
            $('.loadMoreBtn').text('Loading...');
        }

        $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.error) {
                    if (!isAppend) {
                        $('#chatFeed').html('<div class="errorMsg">エラー: ' + escapeHtml(response.error) + '</div>');
                    }
                    isLoading = false;
                    return;
                }

                hasMore = response.hasMore;
                loadedTweets = isAppend
                    ? (response.tweets || []).concat(loadedTweets)
                    : (response.tweets || []);

                renderAllTweets();

                if (topTweetId) {
                    const elem = $('#tweet-' + topTweetId)[0];
                    const container = $('#chatFeed')[0];
                    if (elem && container) {
                        container.scrollTop = elem.offsetTop - container.offsetTop - 50;
                    }
                } else if (!isAppend) {
                    scrollToBottom();
                }

                isLoading = false;
            },
            error: function () {
                if (!isAppend) {
                    $('#chatFeed').html('<div class="noTweets">通信エラーが発生しました。</div>');
                }
                isLoading = false;
            }
        });
    }

    function renderAllTweets() {
        $('#chatFeed').empty();

        if (loadedTweets.length === 0) {
            const label = currentSearch
                ? '一致する記録はありません。'
                : 'この日の記録はありません。';
            $('#chatFeed').html(`<div class="noTweets"><i class="fas fa-pen-nib"></i><span>${label}</span></div>`);

            if (hasMore) {
                $('#chatFeed').prepend(loadMoreButtonHtml());
            }
            return;
        }

        if (hasMore) {
            $('#chatFeed').append(loadMoreButtonHtml());
        }

        let lastDate = null;
        loadedTweets.forEach(function (tweet) {
            const tweetDateStr = tweet.createdAt.substring(0, 10);

            if (tweetDateStr !== lastDate) {
                $('#chatFeed').append(`
                    <div class="dateDivider">
                        <span>${formatYmdText(tweetDateStr)}</span>
                    </div>
                `);
                lastDate = tweetDateStr;
            }

            renderTweet(tweet);
        });
    }

    function renderTweet(tweet) {
        const textContent = escapeHtml(tweet.content).replace(/\n/g, '<br>');
        const timeStr = formatTimeStr(tweet.createdAt);
        const tags = tweet.tags || [];
        const imageHtml = tweet.imageFile
            ? `<img src="uploads/${tweet.imageFile}" class="tweetImage" alt="投稿画像" onload="adjustScroll()" loading="lazy">`
            : '';

        $('#chatFeed').append(`
            <div class="tweet" data-id="${tweet.id}" id="tweet-${tweet.id}">
                <div class="tweetBubble">
                    <div class="tweetActions">
                        <button class="tweetMenuBtn" type="button" title="メニュー"><i class="fas fa-ellipsis-h"></i></button>
                        <div class="tweetMenu">
                            <button class="deleteBtn" type="button" data-id="${tweet.id}"><i class="fas fa-trash"></i> 削除</button>
                        </div>
                    </div>
                    <div class="tweetContent">${textContent}</div>
                    ${imageHtml}
                    <div class="tagsRow" id="tags-container-${tweet.id}">
                        <div class="tagsContainer">
                            ${tagsHtml(tweet.id, tags)}
                            ${generateTagSelectHtml(tweet.id, tags)}
                        </div>
                    </div>
                    <div class="tweetMeta">
                        <span class="tweetTime">${timeStr}</span>
                    </div>
                </div>
            </div>
        `);
    }

    function updateTweetTags(tweetId, tags) {
        const tweetObj = loadedTweets.find(function (tweet) {
            return String(tweet.id) === String(tweetId);
        });
        if (tweetObj) {
            tweetObj.tags = tags;
        }

        $('#tags-container-' + tweetId + ' .tagsContainer').html(
            tagsHtml(tweetId, tags) + generateTagSelectHtml(tweetId, tags)
        );
    }

    function tagsHtml(tweetId, tags) {
        return tags.map(function (tagName) {
            const escapedName = escapeHtml(tagName);
            return `<span class="tagBadge">#${escapedName}<button class="removeTagBtn" data-tweet-id="${tweetId}" data-tag-name="${escapedName}" title="タグ削除">&times;</button></span>`;
        }).join('');
    }

    function generateTagSelectHtml(tweetId, currentTags) {
        let optionsHtml = '<option value="">+</option>';
        let addedCount = 0;

        allTags.forEach(function (tag) {
            if (!currentTags.includes(tag.name)) {
                optionsHtml += `<option value="${tag.id}">${escapeHtml(tag.name)}</option>`;
                addedCount++;
            }
        });

        if (addedCount === 0) {
            return '';
        }

        return `
            <div class="addTagWrap">
                <select class="addTagSelect" data-tweet-id="${tweetId}" title="タグを追加">
                    ${optionsHtml}
                </select>
            </div>
        `;
    }

    function addComposerTag(rawTag) {
        const normalizedTags = String(rawTag || '')
            .replace(/,/g, ' ')
            .split(/\s+/)
            .map(function (tag) {
                return tag.trim().replace(/^#/, '');
            })
            .filter(Boolean);

        normalizedTags.forEach(function (tag) {
            if (!selectedTags.includes(tag)) {
                selectedTags.push(tag);
            }
        });

        renderComposerTags();
    }

    function renderComposerTags() {
        $('#composerTagChips').html(selectedTags.map(function (tag) {
            const escapedTag = escapeHtml(tag);
            return `<span class="composerTagChip">#${escapedTag}<button type="button" class="composerTagRemove" data-tag-name="${escapedTag}" title="タグを外す">&times;</button></span>`;
        }).join(''));

        syncTagsInput();
    }

    function syncTagsInput() {
        $('#tagsHiddenInput').val(selectedTags.join(' '));
    }

    function renderTagSuggestions() {
        const suggestionHtml = allTags.slice(0, 10).map(function (tag) {
            return `<button type="button" class="tagSuggestionBtn" data-tag-name="${escapeHtml(tag.name)}">#${escapeHtml(tag.name)}</button>`;
        }).join('');

        $('#tagSuggestions').html(suggestionHtml).toggle(suggestionHtml !== '');
    }

    function checkSubmitState() {
        const hasText = $('#tweetText').val().trim().length > 0;
        const hasImage = $('#imageUpload').val() !== '';
        $('#submitBtn').prop('disabled', !(hasText || hasImage));
    }

    function resetComposer() {
        selectedTags = [];
        renderComposerTags();
        $('#tweetText').val('').css('height', '46px');
        $('#tagsInput').val('');
        $('#imageUpload').val('');
        $('#imagePreview').attr('src', '');
        $('#imagePreviewContainer').hide();
        checkSubmitState();
    }

    function scrollToBottom() {
        const chatFeed = $('#chatFeed')[0];
        chatFeed.scrollTop = chatFeed.scrollHeight;
    }

    function loadMoreButtonHtml() {
        return `
            <div class="loadMoreBtnWrap">
                <button class="loadMoreBtn" onclick="triggerLoadMore()">さらに過去の記録を読み込む</button>
            </div>
        `;
    }

    function toYmd(date) {
        return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
    }

    function parseYmd(ymd) {
        const parts = ymd.split('-').map(Number);
        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    function formatYmdText(ymd) {
        const date = parseYmd(ymd);
        return date.getFullYear() + '年' + (date.getMonth() + 1) + '月' + date.getDate() + '日';
    }

    function formatTimeStr(isoString) {
        const parts = isoString.split(' ');
        if (parts.length < 2) {
            return '';
        }

        const timeParts = parts[1].split(':');
        return parseInt(timeParts[0], 10) + '時' + timeParts[1] + '分';
    }

    function pad2(value) {
        return ('0' + value).slice(-2);
    }

    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }
});
