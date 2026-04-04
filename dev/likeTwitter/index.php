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
            <div class="headerLeft" style="position: relative; width: 40px; height: 40px; display: flex; justify-content: center; align-items: center;">
                <input type="date" id="calendarInput" style="position: absolute; top:0; left:0; width:100%; height:100%; opacity: 0; cursor: pointer;">
                <button id="calendarBtn" title="日付を選択" style="pointer-events: none;"><i class="fas fa-calendar-alt"></i></button>
            </div>
            <div class="headerTitle">Diary</div>
            <div class="headerRight">
                <span id="currentDateDisplay">Today</span>
                <a href="logout.php" class="logoutBtn" title="ログアウト"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>

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
                    <input type="file" id="imageUpload" name="image" accept="image/*" style="display: none;">

                    <textarea id="tweetText" name="content" placeholder="今どうしてる？" rows="1"></textarea>

                    <button type="submit" id="submitBtn" disabled><i class="fas fa-paper-plane"></i></button>
                </div>
                <div id="imagePreviewContainer" style="display: none;">
                    <img id="imagePreview" src="">
                    <button type="button" id="removeImageBtn"><i class="fas fa-times"></i></button>
                </div>
            </form>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
