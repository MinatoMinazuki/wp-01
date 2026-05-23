<?php
    require_once __DIR__.'/auth.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Diary</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="appContainer">
        <!-- Header -->
        <header class="appHeader">
            <div class="headerLeft">
                <input type="date" id="calendarInput">
                <button id="calendarBtn" title="日付を選択"><i class="fas fa-calendar-alt"></i></button>
            </div>
            <div class="headerTitle">Diary</div>
            <div class="headerRight">
                <button id="searchToggleBtn" title="検索" class="searchToggleBtn"><i class="fas fa-search"></i></button>
                <span id="currentDateDisplay">Today</span>
                <a href="logout.php" class="logoutBtn" title="ログアウト"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>

        <!-- Search Bar -->
        <div id="searchBarContainer" class="searchBarContainer">
            <div class="searchInputWrap">
                <input type="text" id="searchInput" class="searchInput" placeholder="キーワードまたはタグで検索..." autocomplete="off">
                <button id="searchClearBtn" class="searchClearBtn" title="検索解除"><i class="fas fa-times"></i></button>
            </div>
        </div>

        <!-- Chat Feed Area -->
        <main class="chatFeed" id="chatFeed">
            <div class="loading">Loading...</div>
        </main>

        <!-- Input Area -->
        <footer class="appFooter">
            <form id="tweetForm" enctype="multipart/form-data">

                <div class="tagsWrapper">
                    <i class="fas fa-tags tagIcon"></i>
                    <input type="text" id="tagsInput" name="tags" placeholder="タグ (スペース区切り)" autocomplete="off">
                </div>

                <div class="inputWrapper">
                    <label for="imageUpload" class="uploadBtn">
                        <i class="fas fa-image"></i>
                    </label>
                    <input type="file" id="imageUpload" name="image" accept="image/*">

                    <textarea id="tweetText" name="content" placeholder="今どうしてる？" rows="1"></textarea>

                    <button type="submit" id="submitBtn" disabled><i class="fas fa-paper-plane"></i></button>
                </div>
                <div id="imagePreviewContainer">
                    <img id="imagePreview" src="">
                    <button type="button" id="removeImageBtn"><i class="fas fa-times"></i></button>
                </div>
            </form>
        </footer>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="imageModal">
        <span class="closeModalBtn">&times;</span>
        <img class="imageModalContent" id="modalImage">
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
