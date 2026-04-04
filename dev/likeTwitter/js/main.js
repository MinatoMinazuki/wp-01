$(document).ready(function() {
    let currentDate = null;
    let allTags = []; 
    let loadedTweets = [];
    let currentOffset = 0;
    let isLoading = false;
    let hasMore = true;

    fetchAllTags();
    loadTweets(false);

    const today = new Date();
    $('#currentDateDisplay').text(formatDateYMD(today));

    $('#calendarBtn').click(function() {
        $('#calendarInput').click();
    });

    $('#calendarInput').change(function() {
        if ($(this).val()) {
            currentDate = $(this).val();
            let parts = currentDate.split('-');
            $('#currentDateDisplay').text(parts[0] + '年' + parseInt(parts[1]) + '月' + parseInt(parts[2]) + '日');
            loadTweets(false);
        } else {
            currentDate = null;
            $('#currentDateDisplay').text(formatDateYMD(new Date()));
            loadTweets(false);
        }
    });

    function formatDateYMD(date) {
        return date.getFullYear() + '年' + (date.getMonth()+1) + '月' + date.getDate() + '日';
    }

    function formatTimeStr(isoString) {
        let parts = isoString.split(' ');
        if(parts.length < 2) return '';
        let timeParts = parts[1].split(':');
        return parseInt(timeParts[0]) + '時' + timeParts[1] + '分';
    }

    function fetchAllTags() {
        $.ajax({
            url: 'api/getTags.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.tags) {
                    allTags = response.tags;
                }
            }
        });
    }

    function generateTagSelectHtml(tweetId, currentTags) {
        let optionsHtml = '<option value="">+ タグ追加</option>';
        allTags.forEach(function(tag) {
            if (!currentTags.includes(tag.name)) {
                optionsHtml += `<option value="${tag.id}">${escapeHtml(tag.name)}</option>`;
            }
        });
        if (optionsHtml.indexOf('value=') === optionsHtml.lastIndexOf('value=')) return '';
        return `
            <div class="addTagWrap">
                <select class="addTagSelect" data-tweet-id="${tweetId}">
                    ${optionsHtml}
                </select>
            </div>
        `;
    }

    // Export so button can call it
    window.triggerLoadMore = function() {
        loadTweets(true);
    };

    function loadTweets(isAppend) {
        if (isLoading || (!isAppend && !hasMore)) return;
        isLoading = true;

        if (!isAppend) {
            currentOffset = 0;
            hasMore = true;
            $('#chatFeed').html('<div class="loading">Loading...</div>');
        }

        let url = 'api/getTweets.php?offset=' + currentOffset;
        if (currentDate) {
            url += '&date=' + currentDate;
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
            success: function(response) {
                if (response.error) {
                    if(!isAppend) $('#chatFeed').html('<div class="errorMsg">エラー: ' + escapeHtml(response.error) + '</div>');
                    isLoading = false;
                    return;
                }

                hasMore = response.hasMore;
                currentOffset += (response.tweets ? response.tweets.length : 0);

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
            error: function() {
                if(!isAppend) {
                    $('#chatFeed').html('<div class="noTweets">通信エラーが発生しました。</div>');
                }
                isLoading = false;
            }
        });
    }

    function renderAllTweets() {
        $('#chatFeed').empty();

        if (loadedTweets.length === 0) {
            let label = currentDate ? "この日の記録はありません。" : "まだ記録がありません。";
            $('#chatFeed').html(`<div class="noTweets">${label}</div>`);
            return;
        }

        // Add 'Load More' button at the top if there are more older tweets
        if (hasMore) {
            $('#chatFeed').append(`
                <div class="loadMoreBtnWrap" style="text-align:center; padding:10px 0;">
                    <button class="loadMoreBtn" onclick="triggerLoadMore()">さらに過去の記録を読み込む</button>
                </div>
            `);
        }

        let lastDate = null;
        loadedTweets.forEach(function(tweet) {
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
        
        if (tweet.tags.length > 0) {
            tweet.tags.forEach(function(tagName) {
                tagsHtml += `<span class="tagBadge">#${escapeHtml(tagName)}</span>`;
            });
        }
        
        let tagDropdownHtml = generateTagSelectHtml(tweet.id, tweet.tags);

        let html = `
            <div class="tweet" data-id="${tweet.id}" id="tweet-${tweet.id}">
                <div class="tweetBubble">
                    ${textContent}
                    ${imageHtml}
                    <div class="tagsRow" id="tags-container-${tweet.id}">
                        <div class="tagsContainer">
                            ${tagsHtml}
                            ${tagDropdownHtml}
                        </div>
                    </div>
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
    window.adjustScroll = scrollToBottom; 

    // Auto-load more when scrolling to top
    $('#chatFeed').on('scroll', function() {
        if ($(this).scrollTop() === 0 && hasMore && !isLoading && loadedTweets.length > 0) {
            loadTweets(true);
        }
    });

    $('#tweetText').on('input', function() {
        checkSubmitState();
        $(this).css('height', '46px');
        $(this).css('height', Math.min(this.scrollHeight, 150) + 'px');
    });

    $('#imageUpload').change(function() {
        if (this.files && this.files[0]) {
            let reader = new FileReader();
            reader.onload = function(e) {
                $('#imagePreview').attr('src', e.target.result);
                $('#imagePreviewContainer').show();
                checkSubmitState();
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    $('#removeImageBtn').click(function() {
        $('#imageUpload').val('');
        $('#imagePreview').attr('src', '');
        $('#imagePreviewContainer').hide();
        checkSubmitState();
    });

    $('#tagsInput').on('input', function() {
        checkSubmitState();
    });

    function checkSubmitState() {
        let hasText = $('#tweetText').val().trim().length > 0;
        let hasImage = $('#imageUpload').val() !== '';
        $('#submitBtn').prop('disabled', !(hasText || hasImage));
    }

    $('#tweetForm').submit(function(e) {
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
            success: function(response) {
                if (response.success && response.tweet) {
                    // Update top local state
                    loadedTweets.push(response.tweet);
                    currentOffset++; // offset shifts by 1
                    
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
            error: function(xhr) {
                alert('通信エラーが発生しました。');
                checkSubmitState();
            }
        });
    });

    $('#chatFeed').on('click', '.deleteBtn', function() {
        let tweetId = $(this).data('id');
        if (confirm("この記録を削除しますか？")) {
            $.ajax({
                url: 'api/deleteTweet.php',
                method: 'POST',
                data: { tweetId: tweetId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#tweet-' + tweetId).fadeOut(300, function() {
                            $(this).remove();
                            // Update local array
                            loadedTweets = loadedTweets.filter(t => t.id !== tweetId);
                            currentOffset--;
                        });
                    } else {
                        alert('削除に失敗しました: ' + (response.error || '不明なエラー'));
                    }
                },
                error: function() {
                    alert('通信エラーが発生しました。');
                }
            });
        }
    });

    $('#chatFeed').on('change', '.addTagSelect', function() {
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
            success: function(response) {
                if (response.success && response.tags) {
                    // Update array element
                    let tweetObj = loadedTweets.find(t => t.id === tweetId);
                    if (tweetObj) tweetObj.tags = response.tags;

                    let tagsHtml = '';
                    response.tags.forEach(function(tagName) {
                        tagsHtml += `<span class="tagBadge">#${escapeHtml(tagName)}</span>`;
                    });
                    
                    let tagDropdownHtml = generateTagSelectHtml(tweetId, response.tags);
                    $('#tags-container-' + tweetId + ' .tagsContainer').html(tagsHtml + tagDropdownHtml);
                } else {
                    alert('タグの追加に失敗しました。');
                    $select.prop('disabled', false).val('');
                }
            },
            error: function() {
                alert('通信エラーが発生しました。');
                $select.prop('disabled', false).val('');
            }
        });
    });
});
