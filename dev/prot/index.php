<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>類語ワードチャット — UI Prototype</title>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <style>
    :root{
      --bg:#0f1724;
      --card:#0b1220;
      --muted:#94a3b8;
      --accent:#7c3aed;
      --accent-2:#06b6d4;
      --user:#1e293b;
      --ai:#07203a;
      --glass: rgba(255,255,255,0.03);
      --radius:14px;
      --maxw:980px;
      font-family: Inter, "Segoe UI", Roboto, Noto Sans JP, system-ui, -apple-system, "Hiragino Kaku Gothic ProN", "Hiragino Sans", "Yu Gothic", "Meiryo", sans-serif;
    }

    *{box-sizing:border-box}
    html,body{height:100%;margin:0;background:linear-gradient(180deg,#071427 0%, #07142a 60%), var(--bg);color:#e6eef8}

    .app{
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:32px 20px;
    }

    .shell{
      width:100%;max-width:var(--maxw);
      background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
      border-radius:20px;
      padding:18px;
      box-shadow:0 8px 30px rgba(2,6,23,0.6);
      display:grid;
      grid-template-columns:260px 1fr;
      gap:16px;
    }

    .sidebar{
      background:var(--card);
      border-radius:12px;
      padding:14px;
      display:flex;
      flex-direction:column;
      gap:12px;
    }

    .brand{
      display:flex;
      align-items:center;
      gap:10px;
    }

    .logo{
      width:42px;
      height:42px;
      border-radius:10px;
      background:linear-gradient(135deg, var(--accent), var(--accent-2));
      display:flex;
      align-items:center;
      justify-content:center;
      font-weight:700;
      color:white;
      font-size:18px;
    }

    .title{
      font-size:16px;
      font-weight:600;
    }

    .muted{
      color:var(--muted);
      font-size:13px;
    }

    .controls{
      margin-top:8px;
      display:flex;
      flex-direction:column;
      gap:8px;
    }

    label{
      font-size:13px;
      color:var(--muted);
    }

    select,
    input[type=text]{
      width:100%;
      padding:8px;
      border-radius:8px;
      border:1px solid rgba(255, 255, 255, 0.03);
      background:var(--glass);
      color:inherit;
    }

    .examples{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
    }

    .chip{
      background:transparent;
      border:1px dashed rgba(255,255,255,0.05);
      padding:6px 8px;
      border-radius:999px;
      font-size:13px;
      color:var(--muted);
      cursor:pointer;
    }

    .chat-wrap{
      display:flex;
      flex-direction:column;
      height:70vh;
      min-height:480px;
    }

    .chat-header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:8px 12px;
      border-radius:12px;
      background:linear-gradient(180deg, rgba(255, 255, 255, 0.01), rgba(255, 255, 255, 0.005));
      border:1px solid rgba(255,255,255,0.02);
    }

    .chat-body{
      flex:1;
      overflow:auto;
      padding:18px;
      display:flex;
      flex-direction:column;
      gap:12px;
    }

    .msg{
      max-width:76%;
      padding:12px 14px;
      border-radius:14px;
      line-height:1.45;
      box-shadow:0 2px 8px rgba(2,6,23,0.6);
    }

    .msg.user{
      align-self:flex-end;
      background:linear-gradient(180deg, var(--user), #0c1a2b);
      border:1px solid rgba(255, 255, 255, 0.02);
    }

    .msg.ai{
      align-self:flex-start;
      background:linear-gradient(180deg, var(--ai), #05233a);
      border:1px solid rgba(124, 58, 237, 0.12);
    }

    .meta{
      font-size:12px;
      color:var(--muted);
      margin-top:6px;
    }

    .input-area{
      display:flex;
      gap:10px;
      padding:12px;
      margin-top:8px;
      border-radius:12px;
      background:linear-gradient(180deg, rgba(255, 255, 255, 0.01), rgba(255, 255, 255, 0.005));
      border:1px solid rgba(255,255,255,0.02);
    }

    textarea{
      flex:1;
      min-height:46px;
      max-height:140px;
      padding:10px;
      border-radius:10px;
      background:transparent;
      color:inherit;
      border:1px solid rgba(255, 255, 255, 0.03);
      resize:none;
    }

    button.send{
      background:linear-gradient(90deg,var(--accent),var(--accent-2));
      border:none;
      padding:10px 14px;
      border-radius:10px;
      color:white;
      font-weight:600;
      cursor:pointer;
    }

    button.send[disabled]{
      opacity:0.5;
      cursor:not-allowed;
    }

    .empty{
      flex:1;
      display:flex;
      align-items:center;
      justify-content:center;
      color:var(--muted);
      font-size:15px;
    }

    @media (max-width:900px){
      .shell{
        grid-template-columns:1fr;
      }

      .sidebar{
        order:2;
        flex-direction:row;
        overflow:auto;
        padding:10px;
      }

      .chat-wrap{
        order:1;
        height:72vh;
      }
    }

    .sr-only{
      position:absolute;
      width:1px;
      height:1px;
      padding:0;
      margin:-1px;
      overflow:hidden;
      clip:rect(0,0,0,0);
      border:0;
    }

  </style>
</head>
<body>
  <div class="app">
    <main class="shell" role="main">

      <aside class="sidebar" aria-label="Controls">
        <div class="brand">
          <div class="logo">AI</div>
          <div>
            <div class="title">類語ワードチャット</div>
            <div class="muted">プロトタイプ UI</div>
          </div>
        </div>

        <div class="controls">

          <label for="max">候補数（max）</label>
          <select id="max" type="select" value="6" aria-label="候補数">
            <option>1</option>
            <option>2</option>
            <option>3</option>
            <option>4</option>
            <option>5</option>
            <option selected>6</option>
          </select>

          <label>例</label>
          <div class="examples">
            <button class="chip" data-text="海">海</button>
            <button class="chip" data-text="本">本</button>
            <button class="chip" data-text="夜">夜</button>
            <button class="chip" data-text="猫">猫</button>
          </div>
        </div>
      </aside>

      <section class="chat-wrap" aria-label="Chat">
        <div class="chat-header">
          <div>
            <strong>類語ワード</strong>
            <div class="muted" style="font-size:13px">言葉を入力するとAIが類語ワードを返します</div>
          </div>
          <div class="muted">Status: Ready</div>
        </div>

        <div class="chat-body" id="chatBody" role="log" aria-live="polite">
          <div class="empty" id="empty">ここに類語が表示されます。例をクリックするか、下で言葉を入力して送信してください。</div>
        </div>

        <form id="chatForm" class="input-area" onsubmit="return false;" aria-label="入力エリア">
          <label for="query" class="sr-only">言葉</label>
          <textarea id="query" placeholder="例：海" aria-label="言葉入力"></textarea>
          <button class="send" id="sendBtn" type="button">送信</button>
        </form>
      </section>

    </main>
  </div>

  <script>
    var chatBody = $("#chatBody"),
        chatForm = $("#chatForm"),
        query = $("#query"),
        sendBtn = $("#sendBtn"),
        chips = $(".chip"),
        empty = $("#empty");

    var isFake = false;

    function addMessage(text, who='ai')
    {
        if(empty.length) empty.hide();
        var wrap = $('<div>').addClass('msg ' + (who === 'user' ? 'user' : 'ai'));
        wrap.html(`<div>${escapeHtml(text)}</div><div class="meta">${who==='user' ? 'あなた' : 'AI'} : ${new Date().toLocaleTimeString()}</div>`);
        chatBody.append(wrap);
        chatBody.scrollTop(chatBody.prop("scrollHeight"));
    }

    function escapeHtml(s)
    {
        return s.replace(/[&<>"']/g, function(c) {
            return {
              '&':'&amp;',
              '<':'&lt;',
              '>':'&gt;',
              '"':'&quot;',
              '\'':'&#39;'
            }[c];
        });
    }

    function fakeAIResponse(input)
    {
        var seeds = {
        '海':['波','浜辺','潮風','船','青'],
        '本':['ページ','作家','図書館','物語','表紙'],
        '夜':['月','星','静けさ','闇','夜景'],
        '猫':['毛','鳴き声','昼寝','じゃらし','気まぐれ']
        };

        var base = seeds[input];
        return base.join(' ・ ');
    }

    function connectAI( input, num = 6 )
    {
        var deferred = $.Deferred();

        var result = "foo";
        var url = "ai_connect.php";

        $.ajax({
            url: url,
            type: "post",
            data: {
              "input": input,
              "num": num,
            }
        })
        .done(function( res ){
            deferred.resolve(res);
        })
        .fail(function(xhr){
            deferred.resolve(xhr);
        });

        return deferred.promise();

    }

    sendBtn.on('click', function(){
        var v = query.val().trim();
        if(!v) return;
        addMessage(v, 'user');
        query.val('');
        sendBtn.prop('disabled', true);

        setTimeout(() => {
            if( isFake ){
                var response = fakeAIResponse(v);
                addMessage(response, 'ai');
                sendBtn.prop('disabled', false);

                isFake = false;
            } else {
                var number = $("#max").val();
                connectAI(v, number)
                    .done(function(res){
                        addMessage(res, "ai");
                    })
                    .fail(function(xhr){
                        console.log(xhr);
                        addMessage("エラーが発生しました", "ai");
                    })
                    .always(function(){
                        sendBtn.prop("disabled", false);
                        isFake = false;
                    });
            }

        }, 600);
    });

    chips.on("click", function(){
        var text = $(this).attr("data-text");
        query.val(text);
        isFake = true;

        sendBtn.click();
    });

    query.on("keydown", function(e){
        if( (e.ctrlKey || e.metaKey) && e.key === "enter" ){
            sendBtn.click();
        }
    });
  </script>
</body>
</html>
