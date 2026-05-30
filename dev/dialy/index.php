<?php
require_once __DIR__ . '/auth.php';
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?= h($csrfToken) ?>">
    <title>Diary</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="appContainer">
        <header class="appHeader">
            <div class="headerLeft">
                <button id="prevDateBtn" class="dateNavBtn" type="button" title="前日"><i class="fas fa-chevron-left"></i></button>
                <button id="calendarBtn" class="calendarIconBtn" type="button" title="日付を選択"><i class="fas fa-calendar-alt"></i></button>
                <input type="date" id="calendarInput">
                <button id="nextDateBtn" class="dateNavBtn" type="button" title="翌日"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="headerTitle">Diary</div>
            <div class="headerRight">
                <button id="searchToggleBtn" title="検索" class="searchToggleBtn"><i class="fas fa-search"></i></button>
                <button id="todayBtn" title="今日へ戻る" class="todayBtn" type="button">今日</button>
                <span id="currentDateDisplay">Today</span>
                <a href="logout.php" class="logoutBtn" title="ログアウト"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>

        <div id="searchBarContainer" class="searchBarContainer">
            <div class="searchInputWrap">
                <input type="text" id="searchInput" class="searchInput" placeholder="キーワードまたはタグで検索..." autocomplete="off">
                <button id="searchClearBtn" class="searchClearBtn" title="検索をクリア"><i class="fas fa-times"></i></button>
            </div>
            <div id="searchStatus" class="searchStatus"></div>
        </div>

        <main class="chatFeed" id="chatFeed">
            <div class="loading">Loading...</div>
        </main>

        <footer class="appFooter">
            <form id="tweetForm" enctype="multipart/form-data">
                <input type="hidden" name="csrfToken" value="<?= h($csrfToken) ?>">
                <input type="hidden" id="tagsHiddenInput" name="tags" value="">

                <div class="tagsWrapper">
                    <i class="fas fa-tags tagIcon"></i>
                    <div id="composerTagChips" class="composerTagChips"></div>
                    <input type="text" id="tagsInput" placeholder="タグを入力してEnter" autocomplete="off">
                </div>

                <div id="tagSuggestions" class="tagSuggestions"></div>

                <div class="inputWrapper">
                    <label for="imageUpload" class="uploadBtn" title="画像を追加">
                        <i class="fas fa-image"></i>
                    </label>
                    <input type="file" id="imageUpload" name="image" accept="image/*">

                    <textarea id="tweetText" name="content" placeholder="今どうしてる？" rows="1"></textarea>

                    <button type="submit" id="submitBtn" title="投稿" disabled><i class="fas fa-paper-plane"></i></button>
                </div>
                <div id="imagePreviewContainer">
                    <img id="imagePreview" src="" alt="選択した画像のプレビュー">
                    <button type="button" id="removeImageBtn" title="画像を外す"><i class="fas fa-times"></i></button>
                </div>
            </form>
        </footer>
    </div>

    <div id="imageModal" class="imageModal">
        <span class="closeModalBtn">&times;</span>
        <img class="imageModalContent" id="modalImage" alt="拡大画像">
    </div>

    <div id="toast" class="toast" role="status" aria-live="polite"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
