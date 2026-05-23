$(document).ready(function () {
    const today = new Date();
    let currentDate = today.getFullYear() + '-' + ('0' + (today.getMonth() + 1)).slice(-2) + '-' + ('0' + today.getDate()).slice(-2);
    let currentSearch = '';
    let allTags = [];
    let loadedTweets = [];
    let isLoading = false;
    let hasMore = true;

    fetchAllTags(function () {
        loadTweets(false);
    });

    $('#currentDateDisplay').text(formatDateYMD(today));

    $('#calendarBtn').click(function () {
        $('#calendarInput').click();
    });

    $('#searchToggleBtn').click(function () {
        $('#searchBarContainer').slideToggle(200);
        if ($('#searchBarContainer').is(':visible')) {
            $('#searchInput').focus();
        }
    });

    $('#searchInput').on('keydown', function (e) {
        if ((e.key === 'Enter' || e.keyCode === 13) && !e.isComposing) {
            e.preventDefault();
            let sq = $(this).val().trim();
            currentSearch = sq;
            if (currentSearch !== '') {
                $('#searchClearBtn').show();
            } else {
                $('#searchClearBtn').hide();
            }
            loadTweets(false);
        }
    });

    $('#searchClearBtn').click(function () {
        $('#searchInput').val('');
        currentSearch = '';
        $(this).hide();
        loadTweets(false);
    });

    $('#calendarInput').change(function () {
        $('#searchInput').val('');
        currentSearch = '';
        $('#searchClearBtn').hide();
        $('#searchBarContainer').slideUp(200);

        if ($(this).val()) {
            currentDate = $(this).val();
            let parts = currentDate.split('-');
            $('#currentDateDisplay').text(parts[0] + '年' + parseInt(parts[1]) + '月' + parseInt(parts[2]) + '日');
            loadTweets(false);
        } else {
            const tempDate = new Date();
            currentDate = tempDate.getFullYear() + '-' + ('0' + (tempDate.getMonth() + 1)).slice(-2) + '-' + ('0' + tempDate.getDate()).slice(-2);
            $('#currentDateDisplay').text(formatDateYMD(tempDate));
            loadTweets(false);
        }
    });

    function formatDateYMD(date) {
        return date.getFullYear() + '年' + (date.getMonth() + 1) + '月' + date.getDate() + '日';
    }

    function formatTimeStr(isoString) {
        let parts = isoString.split(' ');
        if (parts.length < 2) return '';
        let timeParts = parts[1].split(':');
        return parseInt(timeParts[0]) + '時' + timeParts[1] + '分';
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
                <select class="addTagSelect" data-tweet-id="${tweetId}">
                    ${optionsHtml}
                </select>
            </div>
        `;
    }

    // Export so button can call it
    window.triggerLoadMore = function () {
        loadTweets(true);
    };

    function loadTweets(isAppend) {
        if (isLoading || (isAppend && !hasMore)) return;
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
        } else {
            if (isAppend) {
                if (loadedTweets.length > 0) {
                    url += 'before_id=' + loadedTweets[0].id;
                } else if (currentDate) {
                    url += 'before_date=' + currentDate;
                }
            } else if (currentDate) {
                url += 'date=' + currentDate;
            }
        }

        let topTweetId = null;
        if (isAppend && loadedTweets.length > 0) {
            topTweetId = loadedTweets[0].id; // Keep track of current top item
            $('.loadMoreBtn').text('Loading...');
        }

        $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.error) {
                    if (!isAppend) $('#chatFeed').html('<div class="errorMsg">エラー: ' + escapeHtml(response.error) + '</div>');
                    isLoading = false;
                    return;
                }

                hasMore = response.hasMore;

                if (isAppend) {
                    loadedTweets = (response.tweets || []).concat(loadedTweets);
                } else {
                    loadedTweets = response.tweets || [];
                }

                renderAllTweets();

                // Restore scroll position when appending
                if (isAppend && topTweetId) {
                    let elem = $('#tweet-' + topTweetId)[0];
                    if (elem) {
                        let container = $('#chatFeed')[0];
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
            let label = currentSearch ? "一致する記録はありません。" : (currentDate ? "この日の記録はありません。" : "まだ記録がありません。");
            $('#chatFeed').html(`<div class="noTweets">${label}</div>`);

            if (hasMore) {
                $('#chatFeed').prepend(`
                    <div class="loadMoreBtnWrap">
                        <button class="loadMoreBtn" onclick="triggerLoadMore()">さらに過去の記録を読み込む</button>
                    </div>
                `);
            }
            return;
        }

        // Add 'Load More' button at the top if there are more older tweets
        if (hasMore) {
            $('#chatFeed').append(`
                <div class="loadMoreBtnWrap">
                    <button class="loadMoreBtn" onclick="triggerLoadMore()">さらに過去の記録を読み込む</button>
                </div>
            `);
        }

        let lastDate = null;
        loadedTweets.forEach(function (tweet) {
            let tweetDateStr = tweet.createdAt.substring(0, 10);
            let dParts = tweetDateStr.split('-');
            let formattedDivider = dParts[0] + '年' + parseInt(dParts[1]) + '月' + parseInt(dParts[2]) + '日';

            if (tweetDateStr !== lastDate) {
                $('#chatFeed').append(`
                    <div class="dateDivider">
                        <span>${formattedDivider}</span>
                    </div>
                `);
                lastDate = tweetDateStr;
            }
            renderTweet(tweet);
        });
    }

    function renderTweet(tweet) {
        let escapedContent = escapeHtml(tweet.content);
        let textContent = escapedContent.replace(/\n/g, '<br>');
        let timeStr = formatTimeStr(tweet.createdAt);

        let imageHtml = '';
        if (tweet.imageFile) {
            imageHtml = `<img src="uploads/${tweet.imageFile}" class="tweetImage" alt="uploaded image" onload="adjustScroll()" loading="lazy">`;
        }

        let tagsHtml = '';
        if (!tweet.tags) tweet.tags = [];

        let tagsSection = '';
        tweet.tags.forEach(function (tagName) {
            tagsHtml += `<span class="tagBadge">#${escapeHtml(tagName)}<button class="removeTagBtn" data-tweet-id="${tweet.id}" data-tag-name="${escapeHtml(tagName)}" title="タグ削除">&times;</button></span>`;
        });
        let tagDropdownHtml = generateTagSelectHtml(tweet.id, tweet.tags);
        tagsSection = `
                <div class="tagsRow" id="tags-container-${tweet.id}">
                    <div class="tagsContainer">
                        ${tagsHtml}
                        ${tagDropdownHtml}
                    </div>
                </div>`;

        let html = `
            <div class="tweet" data-id="${tweet.id}" id="tweet-${tweet.id}">
                <div class="tweetBubble">
                    ${textContent}
                    ${imageHtml}
                    ${tagsSection}
                    <div class="tweetMeta">
                        <span class="tweetTime">${timeStr}</span>
                        <button class="deleteBtn" data-id="${tweet.id}" title="削除"><i class="fas fa-trash"></i> 削除</button>
                    </div>
                </div>
            </div>
        `;
        $('#chatFeed').append(html);
    }

    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }

    function scrollToBottom() {
        let chatFeed = $('#chatFeed')[0];
        chatFeed.scrollTop = chatFeed.scrollHeight;
    }
    window.adjustScroll = function () {
        let chatFeed = $('#chatFeed')[0];
        // 500px以内なら下へスクロール（過去分追加時に画像がロードされても飛ばないようにするため）
        if (chatFeed.scrollTop + chatFeed.clientHeight >= chatFeed.scrollHeight - 500) {
            chatFeed.scrollTop = chatFeed.scrollHeight;
        }
    };

    // Auto-load more when scrolling to top
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
        let isPC = window.matchMedia("(any-hover: hover)").matches;
        if (isPC && e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!$('#submitBtn').prop('disabled')) {
                $('#tweetForm').submit();
            }
        }
    });

    $('#imageUpload').change(function () {
        if (this.files && this.files[0]) {
            let reader = new FileReader();
            reader.onload = function (e) {
                $('#imagePreview').attr('src', e.target.result);
                $('#imagePreviewContainer').show();
                checkSubmitState();
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    $('#removeImageBtn').click(function () {
        $('#imageUpload').val('');
        $('#imagePreview').attr('src', '');
        $('#imagePreviewContainer').hide();
        checkSubmitState();
    });

    $('#tagsInput').on('input', function () {
        checkSubmitState();
    });

    function checkSubmitState() {
        let hasText = $('#tweetText').val().trim().length > 0;
        let hasImage = $('#imageUpload').val() !== '';
        $('#submitBtn').prop('disabled', !(hasText || hasImage));
    }

    $('#tweetForm').submit(function (e) {
        e.preventDefault();
        let formData = new FormData(this);
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
                    // Update top local state
                    loadedTweets.push(response.tweet);

                    renderAllTweets();
                    scrollToBottom();

                    $('#tweetText').val('').css('height', '46px');
                    $('#tagsInput').val('');
                    $('#imageUpload').val('');
                    $('#imagePreviewContainer').hide();

                    fetchAllTags();
                } else {
                    alert('投稿に失敗しました: ' + (response.error || '不明なエラー'));
                    checkSubmitState();
                }
            },
            error: function (xhr) {
                alert('通信エラーが発生しました。');
                checkSubmitState();
            }
        });
    });

    $('#chatFeed').on('click', '.deleteBtn', function () {
        let tweetId = $(this).data('id');
        if (confirm("この記録を削除しますか？")) {
            $.ajax({
                url: 'api/deleteTweet.php',
                method: 'POST',
                data: { tweetId: tweetId },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        $('#tweet-' + tweetId).fadeOut(300, function () {
                            $(this).remove();
                            // Update local array
                            loadedTweets = loadedTweets.filter(t => t.id !== tweetId);
                        });
                    } else {
                        alert('削除に失敗しました: ' + (response.error || '不明なエラー'));
                    }
                },
                error: function () {
                    alert('通信エラーが発生しました。');
                }
            });
        }
    });

    $('#chatFeed').on('change', '.addTagSelect', function () {
        let tagId = $(this).val();
        let tweetId = $(this).data('tweet-id');
        let $select = $(this);

        if (!tagId) return;
        $select.prop('disabled', true);

        $.ajax({
            url: 'api/addTagToTweet.php',
            method: 'POST',
            data: { tweetId: tweetId, tagId: tagId },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.tags) {
                    // Update array element
                    let tweetObj = loadedTweets.find(t => t.id === tweetId);
                    if (tweetObj) tweetObj.tags = response.tags;

                    let tagsHtml = '';
                    response.tags.forEach(function (tagName) {
                        tagsHtml += `<span class="tagBadge">#${escapeHtml(tagName)}<button class="removeTagBtn" data-tweet-id="${tweetId}" data-tag-name="${escapeHtml(tagName)}" title="タグ削除">&times;</button></span>`;
                    });

                    let tagDropdownHtml = generateTagSelectHtml(tweetId, response.tags);
                    $('#tags-container-' + tweetId + ' .tagsContainer').html(tagsHtml + tagDropdownHtml);
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
        let tweetId = $(this).data('tweet-id');
        let tagName = $(this).data('tag-name');
        let $btn = $(this);

        $btn.prop('disabled', true);

        $.ajax({
            url: 'api/removeTagFromTweet.php',
            method: 'POST',
            data: { tweetId: tweetId, tagName: tagName },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.tags) {
                    let tweetObj = loadedTweets.find(t => t.id === tweetId);
                    if (tweetObj) tweetObj.tags = response.tags;

                    let tagsHtml = '';
                    response.tags.forEach(function (tName) {
                        tagsHtml += `<span class="tagBadge">#${escapeHtml(tName)}<button class="removeTagBtn" data-tweet-id="${tweetId}" data-tag-name="${escapeHtml(tName)}" title="タグ削除">&times;</button></span>`;
                    });
                    let tagDropdownHtml = generateTagSelectHtml(tweetId, response.tags);
                    $('#tags-container-' + tweetId + ' .tagsContainer').html(tagsHtml + tagDropdownHtml);
                } else {
                    alert('タグの削除に失敗しました。');
                    $btn.prop('disabled', false);
                }
            },
            error: function () {
                alert('通信エラーが発生しました。');
                $btn.prop('disabled', false);
            }
        });
    });

    // Image Modal Logic
    $('#chatFeed').on('click', '.tweetImage', function() {
        $('#modalImage').attr('src', $(this).attr('src'));
        $('#imageModal').css('display', 'flex').hide().fadeIn(200);
    });

    $('#imageModal').click(function(e) {
        if (e.target !== $('#modalImage')[0]) {
            $(this).fadeOut(200);
        }
    });

    $('.closeModalBtn').click(function() {
        $('#imageModal').fadeOut(200);
    });
});
