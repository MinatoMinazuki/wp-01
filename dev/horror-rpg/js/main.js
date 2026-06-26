(function (window, document) {
    'use strict';

    var STORAGE_KEY = 'night-echoes-save-v2';
    var TILE_SIZE = 48;
    var COLS = 15;
    var ROWS = 12;
    var INPUT_COOLDOWN = 110;
    var PLAYER_MOVE_DURATION = 160;
    var PLAYER_DRAW_HEIGHT = 44;

    var canvas;
    var ctx;
    var images = {};
    var playerSpriteFrames = null;
    var state;
    var animationId = null;
    var lastFrame = 0;
    var sceneTimeout = null;

    var DIRECTIONS = {
        up: { x: 0, y: -1 },
        down: { x: 0, y: 1 },
        left: { x: -1, y: 0 },
        right: { x: 1, y: 0 }
    };

    var ITEM_DATA = {
        schoolMap: { name: '古い校内図', description: '美浜中学校の手描き校内図。保健室と美術室に丸が付いている。', category: 'quest' },
        officeKey: { name: '保健室の鍵', description: '学校の事務用の鍵。雨で少し錆びている。', category: 'quest' },
        artKey: { name: '美術室の鍵', description: '青いタグが付いた古い鍵。', category: 'quest' },
        galleryPass: { name: '展示室パス', description: '無人の展示室に入るためのパスカード。', category: 'quest' },
        photoFragment: { name: '濡れた写真片', description: '姉と誰かが祠の前で写っている。記憶として数える。', category: 'memory' },
        mirrorShard: { name: '鏡の欠片', description: '美術室の鏡から外れた鋭い欠片。記憶として数える。', category: 'memory' },
        wardCharm: { name: '護符', description: '一度だけ影から身を守る。メニューではなく自動で使われる。', category: 'utility' },
        redRibbon: { name: '赤いリボン', description: '泣いていた子どもが残したもの。祠に近い匂いがする。', category: 'quest' },
        dogCollar: { name: '犬の首輪', description: '小さな墓石に結ばれていた首輪。記憶として数える。', category: 'memory' },
        bellRope: { name: '鈴緒の切れ端', description: '排水路の奥に落ちていた祠の鈴緒。結界を越える鍵になる。', category: 'quest' },
        sisterLetter: { name: '姉の手紙', description: '「雨の夜に祠へ来ないで」とだけ書かれている。', category: 'note-item' }
    };

    var PORTRAITS = {
        protagonist: 'assets/generated/protagonist-raincoat-girl.png',
        sister: 'assets/generated/older-sister-portrait.png',
        child: 'assets/generated/crying-child-red-ribbon.png',
        shadow: 'assets/generated/shadow-pursuer.png'
    };

    var SPRITES = {
        playerSheet: 'assets/sprites/player-chibi-sheet.png'
    };

    var BACKGROUNDS = {
        title: 'assets/generated/title-key-art.png',
        town: 'assets/generated/rainy-alley-background.png',
        apartment: 'assets/generated/apartment-room-background.png',
        school: 'assets/generated/school-corridor-background.png',
        gallery: 'assets/generated/gallery-room-background.png',
        cemetery: 'assets/generated/cemetery-path-background.png',
        drain: 'assets/generated/drain-tunnel-background.png',
        shrine: 'assets/generated/shrine-courtyard-background.png'
    };

    var MAPS = {
        apartment: {
            name: '302号室',
            chapter: 'Prologue',
            chapterTitle: '雨の夜、302号室',
            chapterBody: '姉が残した電話と手紙を頼りに、雨の町へ出る。',
            background: BACKGROUNDS.apartment,
            theme: 'apartment',
            rows: [
                '###############',
                '#.............#',
                '#.............#',
                '#...###.###...#',
                '#...#.....#...#',
                '#...#.....#...#',
                '#...###.###...#',
                '#.............#',
                '#.....###.....#',
                '#.............#',
                '#.............#',
                '###############'
            ],
            spawn: { x: 7, y: 9, dir: 'up' }
        },
        townWest: {
            name: '雨町 西通り',
            chapter: 'Chapter 1',
            chapterTitle: '西通り',
            chapterBody: '学校、美術館、墓地、排水路。姉は町じゅうに痕跡を残している。',
            background: BACKGROUNDS.town,
            theme: 'town',
            rows: [
                '###############',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#...#.....#...#',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#...#.....#...#',
                '#.............#',
                '#.............#',
                '###############'
            ],
            spawn: { x: 7, y: 9, dir: 'up' }
        },
        school1: {
            name: '美浜中学校 1階',
            chapter: 'Chapter 2',
            chapterTitle: '雨の校舎',
            chapterBody: '消灯した校舎には、姉の靴音だけが残っている。',
            background: BACKGROUNDS.school,
            theme: 'school',
            rows: [
                '###############',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#..#.......#..#',
                '#.............#',
                '#..#.......#..#',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#.............#',
                '###############'
            ],
            spawn: { x: 7, y: 9, dir: 'up' }
        },
        school2: {
            name: '美浜中学校 2階',
            chapter: 'Chapter 2',
            chapterTitle: '絵の匂いが残る階',
            chapterBody: '美術室の鏡だけが、雨の夜を別の場所へ映していた。',
            background: BACKGROUNDS.school,
            theme: 'school',
            rows: [
                '###############',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#..#.......#..#',
                '#.............#',
                '#..#.......#..#',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#.............#',
                '###############'
            ],
            spawn: { x: 13, y: 9, dir: 'left' }
        },
        gallery: {
            name: '旧展示室',
            chapter: 'Chapter 3',
            chapterTitle: '沈黙する展示室',
            chapterBody: '絵の順番が変わるたび、町の真ん中にあったはずのものがずれていく。',
            background: BACKGROUNDS.gallery,
            theme: 'gallery',
            rows: [
                '###############',
                '#.............#',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#....#...#....#',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#.............#',
                '#.............#',
                '###############'
            ],
            spawn: { x: 7, y: 9, dir: 'up' }
        },
        cemetery: {
            name: '坂上墓地',
            chapter: 'Chapter 4',
            chapterTitle: '坂上墓地',
            chapterBody: '泣き声の主と向き合わなければ、最後の記憶は戻らない。',
            background: BACKGROUNDS.cemetery,
            theme: 'cemetery',
            rows: [
                '###############',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#..#.......#..#',
                '#.............#',
                '#..#.......#..#',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#.............#',
                '###############'
            ],
            spawn: { x: 7, y: 9, dir: 'up' }
        },
        drain: {
            name: '排水路',
            chapter: 'Chapter 4',
            chapterTitle: '排水路の奥',
            chapterBody: '祠へ繋がるものは、いつも見えない所に流れ着く。',
            background: BACKGROUNDS.drain,
            theme: 'drain',
            rows: [
                '###############',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#..#.......#..#',
                '#.............#',
                '#..#.......#..#',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#.............#',
                '###############'
            ],
            spawn: { x: 7, y: 9, dir: 'up' }
        },
        shrineRoad: {
            name: '祠への坂道',
            chapter: 'Chapter 5',
            chapterTitle: '祠への坂道',
            chapterBody: '三つの記憶を抱えたまま、もう一度あの鳥居を目指す。',
            background: BACKGROUNDS.town,
            theme: 'town',
            rows: [
                '###############',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#...#.....#...#',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#...#.....#...#',
                '#.............#',
                '#.............#',
                '###############'
            ],
            spawn: { x: 7, y: 9, dir: 'up' }
        },
        shrineCourt: {
            name: '雨祠',
            chapter: 'Finale',
            chapterTitle: '雨祠',
            chapterBody: '返すか、焼くか、差し出すか。ここで夜の形が決まる。',
            background: BACKGROUNDS.shrine,
            theme: 'shrine',
            rows: [
                '###############',
                '#.............#',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#....#...#....#',
                '#.............#',
                '#..###...###..#',
                '#.............#',
                '#.............#',
                '#.............#',
                '###############'
            ],
            spawn: { x: 7, y: 9, dir: 'up' }
        }
    };

    function ready(callback) {
        if (document.readyState !== 'loading') {
            callback();
        } else {
            document.addEventListener('DOMContentLoaded', callback);
        }
    }

    function qs(selector) {
        return document.querySelector(selector);
    }

    function qsa(selector) {
        return Array.prototype.slice.call(document.querySelectorAll(selector));
    }

    function setText(selector, value) {
        var node = qs(selector);
        if (node) {
            node.textContent = value;
        }
    }

    function setHtml(selector, value) {
        var node = qs(selector);
        if (node) {
            node.innerHTML = value;
        }
    }

    function setProp(selector, propName, value) {
        qsa(selector).forEach(function (node) {
            node[propName] = value;
        });
    }

    function addClass(selector, className) {
        qsa(selector).forEach(function (node) {
            node.classList.add(className);
        });
    }

    function removeClass(selector, className) {
        qsa(selector).forEach(function (node) {
            node.classList.remove(className);
        });
    }

    function toggleClass(selector, className, force) {
        qsa(selector).forEach(function (node) {
            node.classList.toggle(className, force);
        });
    }

    function bind(selector, eventName, handler) {
        qsa(selector).forEach(function (node) {
            node.addEventListener(eventName, handler);
        });
    }

    function loadImage(path) {
        var image = new Image();
        image.src = path;
        return image;
    }

    function setupAssets() {
        Object.keys(BACKGROUNDS).forEach(function (key) {
            images[BACKGROUNDS[key]] = loadImage(BACKGROUNDS[key]);
        });

        Object.keys(PORTRAITS).forEach(function (key) {
            images[PORTRAITS[key]] = loadImage(PORTRAITS[key]);
        });

        Object.keys(SPRITES).forEach(function (key) {
            images[SPRITES[key]] = loadImage(SPRITES[key]);
        });
    }

    function buildInitialState() {
        return {
            scene: 'title',
            currentMap: 'apartment',
            player: cloneSpawn(MAPS.apartment.spawn),
            checkpoint: { mapId: 'apartment', x: 7, y: 9, dir: 'up' },
            chapter: MAPS.apartment.chapter,
            objective: '部屋を調べ、姉の残した手掛かりを探す。',
            fear: 'Quiet',
            steps: 0,
            playSeconds: 0,
            lastInputAt: 0,
            matches: 2,
            charms: 0,
            inventory: [],
            notes: [],
            clues: [],
            switches: {},
            variables: {
                compassion: 0,
                paintingOrder: [],
                checkpointName: '302号室'
            },
            dialog: null,
            menuOpen: false,
            choiceIndex: 0,
            queuedInteract: false,
            chase: null,
            ending: null
        };
    }

    function cloneSpawn(spawn) {
        var player = { x: spawn.x, y: spawn.y, dir: spawn.dir || 'down' };
        syncPlayerRender(player);
        return player;
    }

    function syncPlayerRender(player) {
        player.renderX = player.x;
        player.renderY = player.y;
        player.fromX = player.x;
        player.fromY = player.y;
        player.toX = player.x;
        player.toY = player.y;
        player.moving = false;
        player.moveElapsed = 0;
        player.walkCycle = player.walkCycle || 0;
    }

    function currentMap() {
        return MAPS[state.currentMap];
    }

    function createEvents() {
        return {
            apartment: [
                pointEvent(4, 2, function () {
                    if (!state.switches.phoneSeen) {
                        state.switches.phoneSeen = true;
                        addNote('着信履歴', '姉からの留守電。「もし雨が降っていたら、祠には来ないで。学校と展示室だけ見て」');
                        openDialog('留守電', null, [
                            'ノイズ混じりの留守電が残っている。',
                            '「もし雨が降っていたら、祠には来ないで。学校と展示室だけ見て。私の痕跡はそこに置くから」'
                        ]);
                        return;
                    }

                    openDialog('留守電', null, ['古い留守電がもう一度再生される。姉の声は途中で雨音に飲まれる。']);
                }),
                pointEvent(9, 2, function () {
                    if (!state.switches.deskSeen) {
                        state.switches.deskSeen = true;
                        gainItem('sisterLetter');
                        gainItem('schoolMap');
                        addClue('姉の手紙: 最初の手掛かりは学校にある。');
                        state.objective = '学校へ行き、保健室と美術室を調べる。';
                        openDialog('机の手紙', 'sister', [
                            '封筒の中に、濡れないよう何度も折られた手紙と校内図が入っている。',
                            '「保健室の戸棚と、美術室の鏡。そこまではまだ夜に奪われていない」'
                        ]);
                        return;
                    }

                    openDialog('机', null, ['校内図には保健室と美術室にだけ細い丸が描かれている。']);
                }),
                pointEvent(6, 5, function () {
                    openDialog('ベッド', null, ['毛布は冷えたままだ。誰かが慌てて立ち上がった形だけが残っている。']);
                }),
                pointEvent(7, 10, function () {
                    if (!state.switches.phoneSeen || !state.switches.deskSeen) {
                        openDialog('玄関', null, ['まだ出られない。姉が何を残したのか、部屋の中を見ておくべきだ。']);
                        return;
                    }

                    requestTransfer('townWest', 7, 9, 'up', true);
                })
            ],
            townWest: [
                pointEvent(7, 10, function () {
                    requestTransfer('apartment', 7, 9, 'up', false);
                }),
                pointEvent(2, 1, function () {
                    requestTransfer('school1', 7, 9, 'up', true);
                }),
                pointEvent(12, 1, function () {
                    if (!hasItem('galleryPass')) {
                        openDialog('展示室の扉', null, ['扉の横の読み取り機が赤く光っている。パスカードが必要だ。']);
                        return;
                    }

                    requestTransfer('gallery', 7, 9, 'up', true);
                }),
                pointEvent(2, 10, function () {
                    if (!state.switches.galleryPuzzleDone) {
                        openDialog('墓地への坂', null, ['まだ早い。姉が展示室に残したものを先に見つけたい。']);
                        return;
                    }

                    requestTransfer('cemetery', 7, 9, 'up', true);
                }),
                pointEvent(12, 10, function () {
                    if (!state.switches.childSceneDone) {
                        openDialog('排水路の蓋', null, ['重い蓋の縁に赤い糸が挟まっている。墓地を見てからでないと、ここを開ける気にはなれない。']);
                        return;
                    }

                    requestTransfer('drain', 7, 9, 'up', true);
                }),
                pointEvent(7, 1, function () {
                    if (memoryCount() < 3 || !hasItem('bellRope')) {
                        openDialog('祠への坂', null, ['坂道の上に冷たい霧が下りていて、先が見えない。記憶も鈴緒も、まだ足りない。']);
                        return;
                    }

                    requestTransfer('shrineRoad', 7, 9, 'up', true);
                }),
                pointEvent(7, 5, function () {
                    setCheckpoint('townWest', 7, 9, 'up', '西通りの街灯');
                    openDialog('街灯', null, ['街灯の下だけ、少しだけ呼吸が整う。ここを中継点として覚えておく。']);
                })
            ],
            school1: [
                pointEvent(7, 10, function () {
                    requestTransfer('townWest', 2, 2, 'down', false);
                }),
                pointEvent(13, 2, function () {
                    requestTransfer('school2', 13, 9, 'up', false);
                }),
                pointEvent(3, 3, function () {
                    if (!state.switches.codeSeen) {
                        state.switches.codeSeen = true;
                        addClue('黒板の走り書き: 4-1-3。下駄箱と同じ数字。');
                    }

                    openDialog('黒板', null, ['白墨で「4-1-3」とだけ殴り書きされている。すぐ下に、下駄箱の列番号が書かれている。']);
                }),
                pointEvent(11, 3, function () {
                    if (!state.switches.codeSeen) {
                        openDialog('下駄箱', null, ['鍵穴がある。番号を知っていれば開きそうだ。']);
                        return;
                    }

                    if (!hasItem('officeKey')) {
                        gainItem('officeKey');
                        openDialog('下駄箱', null, ['413番の扉の奥に、保健室の鍵が引っ掛けられていた。']);
                        return;
                    }

                    openDialog('下駄箱', null, ['空になった下駄箱に雨の匂いだけが残っている。']);
                }),
                pointEvent(3, 8, function () {
                    if (!hasItem('officeKey')) {
                        openDialog('保健室', null, ['鍵が掛かっている。職員用の鍵が必要だ。']);
                        return;
                    }

                    if (!state.switches.officeLooted) {
                        state.switches.officeLooted = true;
                        gainItem('artKey');
                        gainItem('galleryPass');
                        gainItem('photoFragment');
                        addNote('保健室メモ', '姉の字で「展示室の順番は、朝・昼・夜。鏡は夜に返す」と書かれている。');
                        state.objective = '2階の美術室で鏡の欠片を探し、その後に展示室へ向かう。';
                        openDialog('保健室', 'sister', [
                            '戸棚の奥に、パスカードと美術室の鍵、それから濡れた写真片が置かれている。',
                            '姉のメモにはこうある。「展示室の順番は、朝・昼・夜。鏡は夜に返す」'
                        ]);
                        return;
                    }

                    openDialog('保健室', null, ['薬品棚が静かに並んでいる。必要なものはもう持ち出した。']);
                }),
                pointEvent(7, 5, function () {
                    setCheckpoint('school1', 7, 9, 'up', '学校1階の踊り場');
                    openDialog('踊り場', null, ['ここなら影に見つかっても立て直せる。深呼吸して進む。']);
                })
            ],
            school2: [
                pointEvent(13, 10, function () {
                    if (state.chase && state.chase.id === 'school') {
                        stopChase();
                        state.switches.schoolChaseDone = true;
                        state.objective = '展示室で、姉が残した順番通りに絵を調べる。';
                        openDialog('階段', null, ['影の気配が遠ざかった。次は展示室だ。']);
                        return;
                    }

                    requestTransfer('school1', 13, 3, 'down', false);
                }),
                pointEvent(3, 2, function () {
                    if (!hasItem('artKey')) {
                        openDialog('美術室', null, ['扉が動かない。保健室に鍵があるはずだ。']);
                        return;
                    }

                    if (!hasItem('mirrorShard')) {
                        gainItem('mirrorShard');
                        state.objective = '影から逃げて1階へ戻る。';
                        openDialog('美術室の鏡', null, [
                            '曇った鏡の角だけが割れている。欠片を握ると、冷たすぎて皮膚が痺れた。',
                            '次の瞬間、廊下の向こうで誰かが立ち上がる音がする。'
                        ], function () {
                            startChase({
                                id: 'school',
                                mapId: 'school2',
                                shadowStart: { x: 11, y: 2 },
                                checkpointText: '学校2階の廊下へ戻された。'
                            });
                        });
                        return;
                    }

                    openDialog('美術室の鏡', null, ['割れた鏡はもう何も映さない。ただ、自分の呼吸だけが遅れて返ってくる。']);
                }),
                pointEvent(10, 4, function () {
                    openDialog('掲示板', null, ['文化祭の古いポスターに、展示室の案内が残っている。矢印は西通りの裏手を指している。']);
                }),
                pointEvent(7, 8, function () {
                    setCheckpoint('school2', 13, 9, 'up', '学校2階の窓際');
                    openDialog('窓際', null, ['雨の校庭が見える。逃げ道を頭の中でなぞっておく。']);
                })
            ],
            gallery: [
                pointEvent(7, 10, function () {
                    requestTransfer('townWest', 12, 2, 'down', false);
                }),
                pointEvent(3, 3, function () {
                    inspectPainting('morning', '朝の絵', '朝焼けの川辺を描いた絵。フレームの裏に小さく「1」と刻まれている。');
                }),
                pointEvent(7, 3, function () {
                    inspectPainting('noon', '昼の絵', '真昼の教室を描いた絵。何かを待っているような明るさだけが不自然だ。');
                }),
                pointEvent(11, 3, function () {
                    inspectPainting('night', '夜の絵', '夜の祠を描いた絵。空だけが雨雲で塗りつぶされている。');
                }),
                pointEvent(7, 6, function () {
                    if (!state.switches.galleryPuzzleDone) {
                        openDialog('中央の額', null, ['三枚の絵を正しい順番で見れば、この額も何か返してくる気がする。']);
                        return;
                    }

                    openDialog('中央の額', null, ['裏張りは外れ、奥は空になっている。ここにあったものはもう持ち出した。']);
                }),
                pointEvent(7, 8, function () {
                    setCheckpoint('gallery', 7, 9, 'up', '展示室の中央');
                    openDialog('展示室', null, ['この部屋は静かすぎる。足音を整えるにはちょうどいい。']);
                })
            ],
            cemetery: [
                pointEvent(7, 10, function () {
                    requestTransfer('townWest', 2, 9, 'down', false);
                }),
                pointEvent(7, 4, function () {
                    if (state.switches.childSceneDone) {
                        openDialog('墓地', null, ['赤いリボンの気配だけがまだここに残っている。']);
                        return;
                    }

                    state.switches.childSceneDone = true;
                    openChoiceDialog('泣いている子ども', 'child', [
                        '古い墓石の前で、小さな子どもが濡れたまましゃがみ込んでいる。',
                        '泣き声は弱いのに、耳ではなく胸のあたりだけが痛くなる。'
                    ], [
                        {
                            label: 'そばにしゃがむ',
                            action: function () {
                                state.variables.compassion += 2;
                                gainItem('redRibbon');
                                addClue('赤いリボン: 子どもは排水路の奥に鈴緒が落ちていると言った。');
                                openDialog('赤いリボン', 'child', [
                                    '「返してあげて。あの鈴を戻せば、食べられた声が少しだけ帰ってくる」',
                                    '子どもはそう言って、赤いリボンをあなたの手に押し込んだ。'
                                ]);
                            }
                        },
                        {
                            label: '距離を取って話す',
                            action: function () {
                                state.variables.compassion += 1;
                                addClue('泣き声は祠ではなく排水路を指していた。');
                                openDialog('墓地', null, ['返事はなかった。ただ、泣き声だけが排水路の方へ引いていく。']);
                            }
                        },
                        {
                            label: '背を向ける',
                            action: function () {
                                state.variables.compassion -= 2;
                                openDialog('墓地', null, ['背を向けた瞬間、泣き声が止んだ。静かすぎて、かえって嫌な沈黙になる。']);
                            }
                        }
                    ]);
                }),
                pointEvent(10, 5, function () {
                    if (!hasItem('dogCollar')) {
                        gainItem('dogCollar');
                        state.objective = '排水路の奥で鈴緒を回収し、祠への坂道へ向かう。';
                        openDialog('小さな墓石', null, ['墓石に結ばれた首輪を解くと、雨の匂いに混じって犬の体温だけが残った。']);
                        return;
                    }

                    openDialog('小さな墓石', null, ['首輪はもう持っている。雨粒だけが同じ場所に落ち続ける。']);
                }),
                pointEvent(4, 7, function () {
                    addNote('墓地の供物', '供物の皿に「鈴を戻せ」と刻まれている。祠の鈴緒は排水路に落ちたらしい。');
                    openDialog('供物台', null, ['供物台に残った墨文字は滲んでいるが、「鈴を戻せ」だけは読める。']);
                }),
                pointEvent(7, 8, function () {
                    setCheckpoint('cemetery', 7, 9, 'up', '墓地の石灯籠');
                    openDialog('石灯籠', null, ['灯りは消えている。それでも、ここを越えるときだけは足が少し軽い。']);
                })
            ],
            drain: [
                pointEvent(7, 10, function () {
                    requestTransfer('townWest', 12, 9, 'down', false);
                }),
                pointEvent(7, 2, function () {
                    if (!hasItem('bellRope')) {
                        gainItem('bellRope');
                        state.objective = '影から逃げて排水路を脱出し、祠への坂道へ向かう。';
                        openDialog('排水路の奥', null, [
                            '錆びた金網の向こうに、祠の鈴緒が泥に絡まっている。',
                            '引き抜いた瞬間、水音に混じって重い足音が近づく。'
                        ], function () {
                            startChase({
                                id: 'drain',
                                mapId: 'drain',
                                shadowStart: { x: 11, y: 2 },
                                checkpointText: '排水路の入口まで押し戻された。'
                            });
                        });
                        return;
                    }

                    openDialog('排水路の奥', null, ['鈴緒を抜いた後の泥跡だけが残っている。']);
                }),
                pointEvent(11, 6, function () {
                    addNote('排水路の壁', '「返す者がいなければ、夜は新しい声を選ぶ」');
                    openDialog('壁の文字', null, ['壁に爪で引っかいたような字が残っている。「返す者がいなければ、夜は新しい声を選ぶ」']);
                }),
                pointEvent(4, 8, function () {
                    setCheckpoint('drain', 7, 9, 'up', '排水路の分岐');
                    openDialog('分岐', null, ['ここなら気持ちだけは立て直せる。水面に映る自分の顔は疲れて見える。']);
                })
            ],
            shrineRoad: [
                pointEvent(7, 10, function () {
                    requestTransfer('townWest', 7, 2, 'down', false);
                }),
                pointEvent(7, 1, function () {
                    if (state.chase && state.chase.id === 'shrineRoad') {
                        stopChase();
                    }

                    requestTransfer('shrineCourt', 7, 9, 'up', true);
                }),
                pointEvent(7, 6, function () {
                    openDialog('坂道', null, ['ここから先はもう引き返せる気がしない。雨の匂いまで鳥居の内側へ吸われていく。']);
                })
            ],
            shrineCourt: [
                pointEvent(7, 3, function () {
                    if (state.switches.finalChoiceDone) {
                        openDialog('祠', null, ['祠はもう、夜の入口ではない。ただ濡れた木の匂いだけが残る。']);
                        return;
                    }

                    state.switches.finalChoiceDone = true;
                    openChoiceDialog('雨祠', 'sister', [
                        '鈴緒を戻すと、祠の前に姉の影と、泣いていた子どもの輪郭が重なって立つ。',
                        '「返して。焼いて。あるいは、おまえがここに残るか」'
                    ], [
                        {
                            label: '記憶を返す',
                            action: function () {
                                if (memoryCount() >= 3 && state.variables.compassion >= 1) {
                                    finishGame('Mercy Route', 'Morning Promise', '三つの記憶を祠へ返した瞬間、夜はあなたではなく雨だけを抱えてほどけた。姉は戻らないが、残された声はもう食われない。朝に近い色の空の下で、あなたは一人で坂を下りる。');
                                } else {
                                    finishGame('Fragile Route', 'Still Water', '祠は静まったが、返せたのは形だけだった。朝は来る。それでも、あなたの中にひとつだけ名前のない空洞が残る。');
                                }
                            }
                        },
                        {
                            label: '祠ごと焼く',
                            action: function () {
                                finishGame('Escape Route', 'Ashen Exit', 'マッチの火は鈴緒から木へ、木から雨へと燃え広がった。夜の口は焼け落ち、何が救われたのか曖昧なまま出口だけが残る。あなたは振り返らずに坂を下りた。');
                            }
                        },
                        {
                            label: '自分を差し出す',
                            action: function () {
                                finishGame('No Mercy Route', 'Hollow Feast', '祠は迷わずあなたを選んだ。追ってきた影は消える。代わりに、次の雨の夜にはあなた自身が新しい泣き声を待つ側へ回る。');
                            }
                        }
                    ]);
                }),
                pointEvent(5, 6, function () {
                    openDialog('狐像', null, ['濡れた狐像の目が、ずっと誰かを待っていたことだけは分かる。']);
                })
            ]
        };
    }

    function pointEvent(x, y, action) {
        return {
            x: x,
            y: y,
            action: action
        };
    }

    function rebalanceShortRunContent() {
        if (mapEvents.school1 && mapEvents.school1[3]) {
            mapEvents.school1[3].action = function () {
                if (!state.switches.codeSeen) {
                    state.switches.codeSeen = true;
                    addClue('ロッカーのメモ: 4-1-3');
                }

                if (!hasItem('artKey')) {
                    gainItem('officeKey');
                    gainItem('artKey');
                    gainItem('galleryPass');
                    gainItem('photoFragment');
                    state.objective = '美術室を調べたあと、展示室へ向かう。';
                    openDialog('ロッカー', null, [
                        'ロッカーは、ひどく軽い音を立てて開いた。',
                        '中には保健室の鍵、美術室の鍵、展示室パス、それから破れた写真がまとめて入っていた。'
                    ]);
                    return;
                }

                openDialog('ロッカー', null, ['湿ったノートと、雨に混じった金属の匂いだけが残っている。']);
            };
        }
    }

    function decorateEventMetadata() {
        applyEventHints('apartment', [
            { label: '電話', type: 'inspect', icon: '?' },
            { label: '机', type: 'inspect', icon: '!' },
            { label: 'ベッド', type: 'inspect', icon: '…' },
            { label: '外へ', type: 'exit', icon: '>' }
        ]);

        applyEventHints('townWest', [
            { label: '部屋へ', type: 'exit', icon: '<' },
            { label: '学校へ', type: 'exit', icon: '>' },
            { label: '展示室へ', type: 'exit', icon: '>' },
            { label: '墓地へ', type: 'exit', icon: '>' },
            { label: '排水路へ', type: 'exit', icon: '>' },
            { label: '祠の坂へ', type: 'exit', icon: '>' },
            { label: '中継点', type: 'checkpoint', icon: 'S' }
        ]);

        applyEventHints('school1', [
            { label: '外へ', type: 'exit', icon: '<' },
            { label: '2階へ', type: 'exit', icon: '>' },
            { label: '黒板', type: 'inspect', icon: '?' },
            { label: 'ロッカー', type: 'inspect', icon: '!' },
            { label: '保健室', type: 'inspect', icon: '?' },
            { label: '中継点', type: 'checkpoint', icon: 'S' }
        ]);

        applyEventHints('school2', [
            { label: '1階へ', type: 'exit', icon: '<' },
            { label: '美術室', type: 'inspect', icon: '!' },
            { label: '掲示板', type: 'inspect', icon: '?' },
            { label: '中継点', type: 'checkpoint', icon: 'S' }
        ]);

        applyEventHints('gallery', [
            { label: '外へ', type: 'exit', icon: '<' },
            { label: '朝の絵', type: 'inspect', icon: '?' },
            { label: '昼の絵', type: 'inspect', icon: '?' },
            { label: '夜の絵', type: 'inspect', icon: '?' },
            { label: '中央の額', type: 'inspect', icon: '!' },
            { label: '中継点', type: 'checkpoint', icon: 'S' }
        ]);

        applyEventHints('cemetery', [
            { label: '路地へ', type: 'exit', icon: '<' },
            { label: '泣く子ども', type: 'inspect', icon: '!' },
            { label: '犬の墓', type: 'inspect', icon: '?' },
            { label: '古いメモ', type: 'inspect', icon: '?' },
            { label: '中継点', type: 'checkpoint', icon: 'S' }
        ]);

        applyEventHints('drain', [
            { label: '路地へ', type: 'exit', icon: '<' },
            { label: '排水路の奥', type: 'inspect', icon: '!' },
            { label: '壁のメモ', type: 'inspect', icon: '?' },
            { label: '中継点', type: 'checkpoint', icon: 'S' }
        ]);

        applyEventHints('shrineRoad', [
            { label: '路地へ', type: 'exit', icon: '<' },
            { label: '祠へ', type: 'exit', icon: '>' },
            { label: '坂道', type: 'inspect', icon: '?' }
        ]);

        applyEventHints('shrineCourt', [
            { label: '祭壇', type: 'inspect', icon: '!' },
            { label: '手水鉢', type: 'inspect', icon: '?' }
        ]);
    }

    function applyEventHints(mapId, hints) {
        var events = mapEvents[mapId] || [];
        hints.forEach(function (hint, index) {
            if (events[index]) {
                events[index].hint = hint;
            }
        });
    }

    function isIndoorMap(mapId) {
        return ['apartment', 'school1', 'school2', 'gallery'].indexOf(mapId) !== -1;
    }

    function shouldPromptTransfer(fromMapId, toMapId) {
        if (!fromMapId || !toMapId || fromMapId === toMapId) {
            return false;
        }

        return isIndoorMap(fromMapId) !== isIndoorMap(toMapId);
    }

    var mapEvents = {};

    function hasItem(id) {
        return state.inventory.indexOf(id) !== -1;
    }

    function gainItem(id) {
        if (hasItem(id)) {
            return;
        }

        state.inventory.push(id);
        if (id === 'wardCharm') {
            state.charms += 1;
        }
    }

    function removeItem(id) {
        state.inventory = state.inventory.filter(function (entry) {
            return entry !== id;
        });
    }

    function addNote(title, body) {
        var exists = state.notes.some(function (entry) {
            return entry.title === title;
        });

        if (!exists) {
            state.notes.push({ title: title, body: body });
        }
    }

    function addClue(text) {
        if (state.clues.indexOf(text) === -1) {
            state.clues.push(text);
        }
    }

    function memoryCount() {
        return state.inventory.filter(function (id) {
            return ITEM_DATA[id] && ITEM_DATA[id].category === 'memory';
        }).length;
    }

    function updateHud() {
        setText('#locationText', currentMap().name);
        setText('#chapterText', currentMap().chapter);
        setText('#fearText', state.fear);
        setText('#resourceText', 'マッチ ' + state.matches + ' / 護符 ' + state.charms);
        setText('#objectiveText', state.objective);
        updateMenu();
        refreshLoadButton();
    }

    function updateMenu() {
        var inventoryHtml = state.inventory.length ? state.inventory.map(function (id) {
            var item = ITEM_DATA[id];
            return '<li><strong>' + escapeHtml(item.name) + '</strong><br>' + escapeHtml(item.description) + '</li>';
        }).join('') : '<li>何も持っていない。</li>';

        var notesHtml = state.notes.length ? state.notes.map(function (entry) {
            return '<li><strong>' + escapeHtml(entry.title) + '</strong><br>' + escapeHtml(entry.body) + '</li>';
        }).join('') : '<li>まだ記録はない。</li>';

        var statusHtml = [
            '<li><strong>歩数</strong><br>' + state.steps + '</li>',
            '<li><strong>プレイ時間</strong><br>' + formatTime(state.playSeconds) + '</li>',
            '<li><strong>記憶</strong><br>' + memoryCount() + ' / 3</li>',
            '<li><strong>中継点</strong><br>' + escapeHtml(state.variables.checkpointName) + '</li>'
        ].join('');

        setHtml('#inventoryList', inventoryHtml);
        setHtml('#notesList', notesHtml);
        setHtml('#statusList', statusHtml);
    }

    function refreshLoadButton() {
        var hasSave = !!window.localStorage.getItem(STORAGE_KEY);
        setProp('#loadGameBtn, #loadBtn', 'disabled', !hasSave);
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function openDialog(speaker, portraitKey, pages, onClose) {
        state.dialog = {
            speaker: speaker,
            portraitKey: portraitKey,
            pages: pages,
            pageIndex: 0,
            choices: null,
            onClose: onClose || null
        };
        state.menuOpen = false;
        renderDialog();
    }

    function openChoiceDialog(speaker, portraitKey, pages, choices) {
        state.dialog = {
            speaker: speaker,
            portraitKey: portraitKey,
            pages: pages,
            pageIndex: 0,
            choices: choices,
            onClose: null
        };
        state.choiceIndex = 0;
        state.menuOpen = false;
        renderDialog();
    }

    function closeDialog() {
        if (!state.dialog) {
            return;
        }

        var callback = state.dialog.onClose;
        state.dialog = null;
        addClass('#dialogWindow', 'is-hidden');
        setHtml('#choiceList', '');
        if (callback) {
            callback();
        }
        updateHud();
    }

    function renderDialog() {
        if (!state.dialog) {
            addClass('#dialogWindow', 'is-hidden');
            return;
        }

        var portraitPath = state.dialog.portraitKey ? PORTRAITS[state.dialog.portraitKey] : '';
        setText('#speakerText', state.dialog.speaker || '');
        setText('#dialogText', state.dialog.pages[state.dialog.pageIndex]);

        if (portraitPath) {
            qs('#portraitImage').setAttribute('src', portraitPath);
            removeClass('#portraitImage', 'is-hidden');
        } else {
            addClass('#portraitImage', 'is-hidden');
        }

        renderChoices();
        removeClass('#dialogWindow', 'is-hidden');
    }

    function renderChoices() {
        var container = qs('#choiceList');
        container.innerHTML = '';

        if (!state.dialog || !state.dialog.choices || state.dialog.pageIndex !== state.dialog.pages.length - 1) {
            return;
        }

        state.dialog.choices.forEach(function (choice, index) {
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = choice.label;
            if (index === state.choiceIndex) {
                button.classList.add('active');
            }
            button.addEventListener('click', function () {
                chooseDialogOption(index);
            });
            container.appendChild(button);
        });
    }

    function advanceDialog() {
        if (!state.dialog) {
            return false;
        }

        if (state.dialog.choices && state.dialog.pageIndex === state.dialog.pages.length - 1) {
            chooseDialogOption(state.choiceIndex);
            return true;
        }

        if (state.dialog.pageIndex < state.dialog.pages.length - 1) {
            state.dialog.pageIndex += 1;
            renderDialog();
            return true;
        }

        closeDialog();
        return true;
    }

    function chooseDialogOption(index) {
        if (!state.dialog || !state.dialog.choices || !state.dialog.choices[index]) {
            return;
        }

        var action = state.dialog.choices[index].action;
        state.dialog = null;
        addClass('#dialogWindow', 'is-hidden');
        if (action) {
            action();
        }
        updateHud();
    }

    function formatTime(totalSeconds) {
        var hours = Math.floor(totalSeconds / 3600);
        var minutes = Math.floor((totalSeconds % 3600) / 60);
        var seconds = totalSeconds % 60;
        var parts = [];

        if (hours > 0) {
            parts.push(pad(hours));
        }

        parts.push(pad(minutes));
        parts.push(pad(seconds));
        return parts.join(':');
    }

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function startGame(fromSave) {
        if (!fromSave) {
            state = buildInitialState();
            showSceneCard(MAPS.apartment.chapter, MAPS.apartment.chapterTitle, MAPS.apartment.chapterBody, 2200);
            openDialog('302号室', null, [
                '雨音で目が覚めた。姉は帰っていない。',
                '部屋に残された電話と机を調べれば、どこへ向かったのか分かるかもしれない。'
            ]);
        }

        state.scene = 'game';
        addClass('#titleOverlay', 'is-hidden');
        addClass('#endingOverlay', 'is-hidden');
        updateHud();
        draw();
    }

    function returnToTitle() {
        state = buildInitialState();
        removeClass('#titleOverlay', 'is-hidden');
        addClass('#endingOverlay', 'is-hidden');
        addClass('#menuOverlay', 'is-hidden');
        addClass('#dialogWindow', 'is-hidden');
        updateHud();
        draw();
    }

    function showSceneCard(chapter, title, body, duration) {
        clearTimeout(sceneTimeout);
        setText('#sceneChapter', chapter);
        setText('#sceneTitle', title);
        setText('#sceneBody', body);
        removeClass('#sceneOverlay', 'is-hidden');

        sceneTimeout = window.setTimeout(function () {
            addClass('#sceneOverlay', 'is-hidden');
        }, duration || 2000);
    }

    function saveGame() {
        var payload = {
            currentMap: state.currentMap,
            player: state.player,
            checkpoint: state.checkpoint,
            chapter: state.chapter,
            objective: state.objective,
            fear: state.fear,
            steps: state.steps,
            playSeconds: state.playSeconds,
            matches: state.matches,
            charms: state.charms,
            inventory: state.inventory,
            notes: state.notes,
            clues: state.clues,
            switches: state.switches,
            variables: state.variables
        };

        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        openDialog('セーブ', null, ['現在の進行を保存した。']);
        refreshLoadButton();
    }

    function loadGame(fromTitle) {
        var raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            openDialog('ロード', null, ['保存データが見つからない。']);
            return;
        }

        try {
            var parsed = JSON.parse(raw);
            state = buildInitialState();
            state.scene = 'game';
            state.currentMap = parsed.currentMap;
            state.player = parsed.player;
            syncPlayerRender(state.player);
            state.checkpoint = parsed.checkpoint;
            state.chapter = parsed.chapter;
            state.objective = parsed.objective;
            state.fear = parsed.fear;
            state.steps = parsed.steps;
            state.playSeconds = parsed.playSeconds;
            state.matches = parsed.matches;
            state.charms = parsed.charms;
            state.inventory = parsed.inventory || [];
            state.notes = parsed.notes || [];
            state.clues = parsed.clues || [];
            state.switches = parsed.switches || {};
            state.variables = parsed.variables || state.variables;
            state.menuOpen = false;
            state.dialog = null;
            state.chase = null;
            addClass('#titleOverlay', 'is-hidden');
            addClass('#menuOverlay', 'is-hidden');
            if (fromTitle) {
                showSceneCard(currentMap().chapter, currentMap().chapterTitle, currentMap().chapterBody, 1800);
            }
            updateHud();
            draw();
        } catch (error) {
            openDialog('ロード', null, ['保存データの読み込みに失敗した。']);
        }
    }

    function setCheckpoint(mapId, x, y, dir, label) {
        state.checkpoint = { mapId: mapId, x: x, y: y, dir: dir };
        state.variables.checkpointName = label;
    }

    function movePlayer(direction) {
        if (state.scene !== 'game' || state.menuOpen || state.dialog || state.ending || state.player.moving) {
            return;
        }

        state.player.dir = direction;
        var next = {
            x: state.player.x + DIRECTIONS[direction].x,
            y: state.player.y + DIRECTIONS[direction].y
        };

        if (!isWalkable(next.x, next.y)) {
            draw();
            return;
        }

        state.player.fromX = state.player.renderX;
        state.player.fromY = state.player.renderY;
        state.player.toX = next.x;
        state.player.toY = next.y;
        state.player.moveElapsed = 0;
        state.player.moving = true;
        state.player.x = next.x;
        state.player.y = next.y;
        state.player.walkCycle += 1;
        state.steps += 1;

        handleTouchEvent();
        if (state.chase) {
            advanceChase();
        }
        updateFear();
        updateHud();
        draw();
    }

    function interact() {
        if (state.scene !== 'game' || state.menuOpen || state.ending) {
            return;
        }

        if (state.player.moving) {
            state.queuedInteract = true;
            return;
        }

        if (state.dialog) {
            advanceDialog();
            return;
        }

        var front = facingTile();
        var event = findEvent(front.x, front.y) || findEvent(state.player.x, state.player.y);
        if (event) {
            event.action();
            updateHud();
            draw();
            return;
        }

        openDialog('雨音', null, ['特に何も起きない。ただ、濡れた夜だけがこちらを見ている。']);
    }

    function facingTile() {
        return {
            x: state.player.x + DIRECTIONS[state.player.dir].x,
            y: state.player.y + DIRECTIONS[state.player.dir].y
        };
    }

    function findEvent(x, y) {
        var events = mapEvents[state.currentMap] || [];
        return events.find(function (entry) {
            return entry.x === x && entry.y === y;
        }) || null;
    }

    function handleTouchEvent() {
        if (state.currentMap === 'shrineRoad' && !state.switches.shrineRoadStarted) {
            state.switches.shrineRoadStarted = true;
            state.objective = '影から逃げて鳥居をくぐる。';
            startChase({
                id: 'shrineRoad',
                mapId: 'shrineRoad',
                shadowStart: { x: 12, y: 9 },
                checkpointText: '坂道の下まで押し戻された。'
            });
            openDialog('坂道', null, ['鳥居の上から、あの影が雨と一緒に落ちてくる。走るしかない。']);
        }
    }

    function startChase(config) {
        state.chase = {
            id: config.id,
            mapId: config.mapId,
            shadow: {
                x: config.shadowStart.x,
                y: config.shadowStart.y
            },
            stun: 0,
            failText: config.checkpointText
        };
        updateFear();
    }

    function stopChase() {
        state.chase = null;
        updateFear();
        draw();
    }

    function advanceChase() {
        if (!state.chase || state.currentMap !== state.chase.mapId) {
            return;
        }

        if (state.chase.stun > 0) {
            state.chase.stun -= 1;
            updateFear();
            return;
        }

        var options = [];
        Object.keys(DIRECTIONS).forEach(function (key) {
            var nextX = state.chase.shadow.x + DIRECTIONS[key].x;
            var nextY = state.chase.shadow.y + DIRECTIONS[key].y;
            if (isWalkable(nextX, nextY)) {
                options.push({
                    x: nextX,
                    y: nextY,
                    distance: Math.abs(nextX - state.player.x) + Math.abs(nextY - state.player.y)
                });
            }
        });

        options.sort(function (a, b) {
            return a.distance - b.distance;
        });

        if (options[0]) {
            state.chase.shadow.x = options[0].x;
            state.chase.shadow.y = options[0].y;
        }

        if (state.chase.shadow.x === state.player.x && state.chase.shadow.y === state.player.y) {
            if (state.charms > 0) {
                state.charms -= 1;
                removeItem('wardCharm');
                state.chase.stun = 3;
                openDialog('護符', null, ['護符が砕け、影の輪郭を少しだけ引き剥がした。']);
            } else {
                resetToCheckpoint(state.chase.failText);
            }
        }

        updateFear();
    }

    function useMatch() {
        if (state.scene !== 'game' || state.menuOpen || state.dialog || !state.chase || state.player.moving) {
            return;
        }

        if (state.matches <= 0) {
            openDialog('マッチ', null, ['もう火は残っていない。']);
            return;
        }

        state.matches -= 1;
        state.chase.stun = 2;
        updateHud();
        draw();
        openDialog('マッチ', null, ['火花が影の輪郭を焼き、数歩だけ足を止めた。']);
    }

    function updateFear() {
        if (!state.chase || state.currentMap !== state.chase.mapId) {
            state.fear = 'Quiet';
            return;
        }

        var distance = Math.abs(state.player.x - state.chase.shadow.x) + Math.abs(state.player.y - state.chase.shadow.y);
        if (distance <= 1) {
            state.fear = 'Caught';
        } else if (distance <= 3) {
            state.fear = 'Heartbeat';
        } else if (distance <= 5) {
            state.fear = 'Uneasy';
        } else {
            state.fear = 'Quiet';
        }
    }

    function resetToCheckpoint(message) {
        state.currentMap = state.checkpoint.mapId;
        state.player = {
            x: state.checkpoint.x,
            y: state.checkpoint.y,
            dir: state.checkpoint.dir
        };
        syncPlayerRender(state.player);
        state.chase = null;
        updateFear();
        draw();
        openDialog('雨', null, [message || '中継点まで戻された。']);
    }

    function requestTransfer(mapId, x, y, dir, showChapter) {
        var destination = MAPS[mapId];
        if (!destination) {
            return;
        }

        if (!shouldPromptTransfer(state.currentMap, mapId)) {
            transferTo(mapId, x, y, dir, showChapter);
            return;
        }

        openChoiceDialog('移動', null, [
            destination.name + ' へ移動しますか。 現在地: ' + currentMap().name
        ], [
            {
                label: 'はい',
                action: function () {
                    transferTo(mapId, x, y, dir, showChapter);
                }
            },
            {
                label: 'いいえ',
                action: function () {
                    draw();
                }
            }
        ]);
    }

    function transferTo(mapId, x, y, dir, showChapter) {
        state.currentMap = mapId;
        state.player = { x: x, y: y, dir: dir || 'down' };
        syncPlayerRender(state.player);
        state.chase = null;
        state.chapter = MAPS[mapId].chapter;
        updateFear();
        if (showChapter) {
            showSceneCard(MAPS[mapId].chapter, MAPS[mapId].chapterTitle, MAPS[mapId].chapterBody, 1700);
        }
        updateHud();
        draw();
    }

    function updatePlayerMotion(deltaMs) {
        if (!state.player || !state.player.moving) {
            return;
        }

        state.player.moveElapsed += deltaMs;
        var progress = Math.min(1, state.player.moveElapsed / PLAYER_MOVE_DURATION);
        var eased = progress < 0.5
            ? 2 * progress * progress
            : 1 - Math.pow(-2 * progress + 2, 2) / 2;

        state.player.renderX = state.player.fromX + (state.player.toX - state.player.fromX) * eased;
        state.player.renderY = state.player.fromY + (state.player.toY - state.player.fromY) * eased;

        if (progress >= 1) {
            state.player.moving = false;
            state.player.renderX = state.player.x;
            state.player.renderY = state.player.y;
            state.player.fromX = state.player.x;
            state.player.fromY = state.player.y;
            if (state.queuedInteract) {
                state.queuedInteract = false;
                interact();
                return;
            }
        }
    }

    function inspectPainting(id, label, description) {
        if (state.switches.galleryPuzzleDone) {
            openDialog(label, null, [description]);
            return;
        }

        var expected = ['morning', 'noon', 'night'];
        var progress = state.variables.paintingOrder.slice();
        progress.push(id);

        if (progress[progress.length - 1] !== expected[progress.length - 1]) {
            state.variables.paintingOrder = [];
            openDialog(label, null, [
                description,
                '額縁の奥で何かが外れかけたが、すぐに元へ戻ってしまった。順番が違う。'
            ]);
            return;
        }

        state.variables.paintingOrder = progress;

        if (progress.length === expected.length) {
            state.switches.galleryPuzzleDone = true;
            gainItem('wardCharm');
            addNote('展示室の順番', '朝・昼・夜の順で絵を見た時、中央の額から護符が落ちた。');
            addClue('墓地への道は開いた。姉の次の痕跡は坂上墓地にある。');
            state.objective = '坂上墓地へ向かい、最後の記憶を探す。';
            openDialog('中央の額', null, [
                '額の裏から護符が滑り落ちる。姉の走り書きも挟まっている。',
                '「次は坂上墓地。泣き声を置き去りにしないで」'
            ]);
            return;
        }

        openDialog(label, null, [description, '順番は合っている。もう少しだ。']);
    }

    function isWalkable(x, y) {
        var map = currentMap();
        if (x < 0 || y < 0 || x >= COLS || y >= ROWS) {
            return false;
        }

        return map.rows[y].charAt(x) !== '#';
    }

    function draw() {
        drawBackground();
        drawTiles();
        drawDecor();
        drawMapEvents();
        if (state.chase && state.currentMap === state.chase.mapId) {
            drawShadow(state.chase.shadow.x, state.chase.shadow.y);
        }
        drawPlayer();
        drawAtmosphere();
        drawGuidanceOverlay();
        drawVignette();
    }

    function drawBackground() {
        var map = currentMap();
        var image = images[map.background];
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        if (image && image.complete) {
            drawImageCover(image, 0.1);
        } else {
            var gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
            gradient.addColorStop(0, '#172120');
            gradient.addColorStop(1, '#060909');
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }
    }

    function drawImageCover(image, overlayAlpha) {
        var sourceWidth = image.width;
        var sourceHeight = image.height;
        var sourceX = 0;
        var sourceY = 0;
        var imageRatio = image.width / image.height;
        var canvasRatio = canvas.width / canvas.height;

        if (imageRatio > canvasRatio) {
            sourceWidth = image.height * canvasRatio;
            sourceX = (image.width - sourceWidth) / 2;
        } else {
            sourceHeight = image.width / canvasRatio;
            sourceY = (image.height - sourceHeight) / 2;
        }

        ctx.drawImage(image, sourceX, sourceY, sourceWidth, sourceHeight, 0, 0, canvas.width, canvas.height);
        ctx.fillStyle = 'rgba(6, 9, 9, ' + overlayAlpha + ')';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }

    function drawTiles() {
        var map = currentMap();
        for (var y = 0; y < ROWS; y++) {
            for (var x = 0; x < COLS; x++) {
                var tile = map.rows[y].charAt(x);
                var px = x * TILE_SIZE;
                var py = y * TILE_SIZE;

                if (tile === '#') {
                    ctx.fillStyle = wallColor(map.theme, x, y);
                    ctx.fillRect(px, py, TILE_SIZE, TILE_SIZE);
                    ctx.strokeStyle = 'rgba(0, 0, 0, 0.32)';
                    ctx.lineWidth = 1;
                    ctx.strokeRect(px + 0.5, py + 0.5, TILE_SIZE - 1, TILE_SIZE - 1);
                    ctx.fillStyle = 'rgba(255, 255, 255, 0.02)';
                    ctx.fillRect(px + 8, py + 8, TILE_SIZE - 16, 6);
                } else {
                    ctx.fillStyle = floorColor(map.theme, x, y);
                    ctx.fillRect(px, py, TILE_SIZE, TILE_SIZE);
                    ctx.strokeStyle = 'rgba(245, 235, 210, 0.22)';
                    ctx.lineWidth = 1;
                    ctx.strokeRect(px + 0.5, py + 0.5, TILE_SIZE - 1, TILE_SIZE - 1);
                    ctx.strokeStyle = 'rgba(8, 12, 12, 0.46)';
                    ctx.strokeRect(px + 3.5, py + 3.5, TILE_SIZE - 7, TILE_SIZE - 7);
                    ctx.fillStyle = 'rgba(255, 245, 222, 0.08)';
                    ctx.fillRect(px + 6, py + 6, TILE_SIZE - 12, 2);
                    ctx.fillRect(px + 6, py + 6, 2, TILE_SIZE - 12);
                    ctx.fillStyle = 'rgba(255, 255, 255, 0.035)';
                    ctx.fillRect(px + 10, py + 10, TILE_SIZE - 20, TILE_SIZE - 20);
                }
            }
        }
    }

    function floorColor(theme, x, y) {
        if (theme === 'apartment') {
            return (x + y) % 2 === 0 ? 'rgba(104, 85, 61, 0.74)' : 'rgba(84, 68, 48, 0.72)';
        }
        if (theme === 'school') {
            return (x + y) % 2 === 0 ? 'rgba(92, 107, 116, 0.68)' : 'rgba(76, 90, 99, 0.66)';
        }
        if (theme === 'gallery') {
            return (x + y) % 2 === 0 ? 'rgba(111, 84, 58, 0.64)' : 'rgba(91, 66, 45, 0.62)';
        }
        if (theme === 'cemetery') {
            return (x + y) % 2 === 0 ? 'rgba(73, 81, 75, 0.64)' : 'rgba(60, 67, 62, 0.62)';
        }
        if (theme === 'drain') {
            return (x + y) % 2 === 0 ? 'rgba(70, 82, 82, 0.68)' : 'rgba(55, 66, 66, 0.66)';
        }
        if (theme === 'shrine') {
            return (x + y) % 2 === 0 ? 'rgba(88, 75, 59, 0.66)' : 'rgba(71, 59, 45, 0.64)';
        }
        return (x + y) % 2 === 0 ? 'rgba(59, 75, 73, 0.66)' : 'rgba(47, 61, 60, 0.64)';
    }

    function wallColor(theme) {
        if (theme === 'apartment') {
            return 'rgba(34, 28, 22, 0.92)';
        }
        if (theme === 'school') {
            return 'rgba(29, 37, 43, 0.94)';
        }
        if (theme === 'gallery') {
            return 'rgba(35, 27, 22, 0.94)';
        }
        if (theme === 'cemetery') {
            return 'rgba(27, 31, 29, 0.94)';
        }
        if (theme === 'drain') {
            return 'rgba(28, 34, 34, 0.95)';
        }
        if (theme === 'shrine') {
            return 'rgba(34, 29, 24, 0.94)';
        }
        return 'rgba(24, 31, 31, 0.94)';
    }

    function drawDecor() {
        var map = currentMap();
        ctx.save();

        if (map.theme === 'town') {
            drawStreetLamps();
        } else if (map.theme === 'school') {
            drawSchoolDecor();
        } else if (map.theme === 'gallery') {
            drawGalleryDecor();
        } else if (map.theme === 'cemetery') {
            drawCemeteryDecor();
        } else if (map.theme === 'drain') {
            drawDrainDecor();
        } else if (map.theme === 'shrine') {
            drawShrineDecor();
        } else if (map.theme === 'apartment') {
            drawApartmentDecor();
        }

        ctx.restore();
    }

    function drawStreetLamps() {
        [2, 7, 12].forEach(function (col, index) {
            var x = col * TILE_SIZE + 24;
            var y = (index % 2 === 0 ? 96 : 240);
            ctx.fillStyle = 'rgba(219, 183, 107, 0.12)';
            ctx.beginPath();
            ctx.arc(x, y, 42, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = 'rgba(222, 205, 156, 0.28)';
            ctx.beginPath();
            ctx.moveTo(x, y - 34);
            ctx.lineTo(x, y + 26);
            ctx.stroke();
        });
    }

    function drawSchoolDecor() {
        for (var y = 1; y < ROWS - 1; y += 3) {
            ctx.fillStyle = 'rgba(233, 240, 245, 0.07)';
            ctx.fillRect(2, y * TILE_SIZE + 8, 28, 16);
            ctx.fillRect(canvas.width - 30, y * TILE_SIZE + 8, 28, 16);
        }
    }

    function drawGalleryDecor() {
        [2, 7, 12].forEach(function (col) {
            ctx.strokeStyle = 'rgba(219, 189, 133, 0.3)';
            ctx.lineWidth = 3;
            ctx.strokeRect(col * TILE_SIZE - 12, 116, 72, 52);
        });
    }

    function drawCemeteryDecor() {
        [2, 5, 9, 12].forEach(function (col, index) {
            ctx.fillStyle = 'rgba(210, 219, 214, 0.1)';
            ctx.fillRect(col * TILE_SIZE, 100 + index * 46, 18, 26);
            ctx.fillStyle = 'rgba(229, 182, 101, 0.06)';
            ctx.beginPath();
            ctx.arc(col * TILE_SIZE + 9, 100 + index * 46, 18, 0, Math.PI * 2);
            ctx.fill();
        });
    }

    function drawDrainDecor() {
        for (var i = 0; i < ROWS; i++) {
            ctx.fillStyle = 'rgba(76, 110, 108, 0.12)';
            ctx.fillRect(TILE_SIZE * 5, i * TILE_SIZE + 10, TILE_SIZE * 5, 10);
        }
    }

    function drawShrineDecor() {
        ctx.strokeStyle = 'rgba(211, 150, 89, 0.28)';
        ctx.lineWidth = 6;
        ctx.strokeRect(290, 70, 140, 80);
        ctx.lineWidth = 2;
    }

    function drawApartmentDecor() {
        ctx.fillStyle = 'rgba(233, 209, 154, 0.08)';
        ctx.fillRect(430, 70, 86, 54);
        ctx.fillRect(180, 76, 62, 28);
        ctx.fillRect(290, 250, 120, 72);
    }

    function drawMapEvents() {
        var events = mapEvents[state.currentMap] || [];
        events.forEach(function (event) {
            var px = event.x * TILE_SIZE;
            var py = event.y * TILE_SIZE;
            var hint = event.hint || { type: 'inspect', icon: '!' };
            var focused = isFocusedEvent(event);
            var palette = markerPalette(hint.type, focused);
            var pulse = 0.82 + Math.sin(Date.now() / 220 + event.x + event.y) * 0.18;
            ctx.save();
            ctx.globalAlpha = pulse;
            ctx.fillStyle = palette.glow;
            ctx.beginPath();
            ctx.arc(px + 24, py + 24, focused ? 12 : 9, 0, Math.PI * 2);
            ctx.fill();
            ctx.globalAlpha = 1;
            ctx.fillStyle = palette.fill;
            ctx.beginPath();
            ctx.arc(px + 24, py + 24, focused ? 8 : 6, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = palette.stroke;
            ctx.lineWidth = 2;
            ctx.stroke();
            ctx.fillStyle = '#f4efe2';
            ctx.font = 'bold 12px "Yu Gothic"';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(hint.icon || '!', px + 24, py + 24);
            ctx.restore();
        });
    }

    function drawGuidanceOverlay() {
        drawPlayerTileHighlight();
        drawEventLabels();
        drawActionPrompt();
        drawLocationBadge();
    }

    function drawPlayerTileHighlight() {
        var px = state.player.x * TILE_SIZE;
        var py = state.player.y * TILE_SIZE;
        ctx.save();
        ctx.fillStyle = 'rgba(215, 186, 117, 0.12)';
        ctx.fillRect(px + 4, py + 4, TILE_SIZE - 8, TILE_SIZE - 8);
        ctx.strokeStyle = 'rgba(244, 231, 190, 0.92)';
        ctx.lineWidth = 2;
        ctx.strokeRect(px + 3.5, py + 3.5, TILE_SIZE - 7, TILE_SIZE - 7);

        var front = facingTile();
        var frontEvent = findEvent(front.x, front.y) || findEvent(state.player.x, state.player.y);
        if (frontEvent) {
            var fx = front.x * TILE_SIZE;
            var fy = front.y * TILE_SIZE;
            ctx.strokeStyle = 'rgba(129, 188, 205, 0.9)';
            ctx.setLineDash([5, 4]);
            ctx.strokeRect(fx + 6.5, fy + 6.5, TILE_SIZE - 13, TILE_SIZE - 13);
        }
        ctx.restore();
    }

    function drawEventLabels() {
        var events = mapEvents[state.currentMap] || [];
        events.forEach(function (event) {
            var hint = event.hint || { label: '調べる', type: 'inspect' };
            if (!shouldRevealEventLabel(event)) {
                return;
            }

            drawEventLabel(event, hint, isFocusedEvent(event));
        });
    }

    function drawEventLabel(event, hint, focused) {
        var px = event.x * TILE_SIZE + 24;
        var py = event.y * TILE_SIZE - (focused ? 18 : 10);
        var text = hint.label || '調べる';
        var width = Math.max(54, text.length * 14);
        var left = Math.max(12, Math.min(canvas.width - width - 12, px - width / 2));
        var top = Math.max(14, py - 18);
        var palette = markerPalette(hint.type, focused);

        ctx.save();
        ctx.fillStyle = 'rgba(7, 10, 11, 0.9)';
        ctx.strokeStyle = palette.stroke;
        ctx.lineWidth = focused ? 2 : 1.5;
        roundRect(left, top, width, 24, 7);
        ctx.fill();
        ctx.stroke();
        ctx.fillStyle = '#f5efe2';
        ctx.font = '12px "Yu Gothic"';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, left + width / 2, top + 12);
        ctx.restore();
    }

    function drawActionPrompt() {
        var event = findEvent(facingTile().x, facingTile().y) || findEvent(state.player.x, state.player.y);
        if (!event) {
            return;
        }

        var hint = event.hint || { label: '調べる' };
        var text = 'E / Enter : ' + (hint.label || '調べる');
        var width = Math.max(170, text.length * 10);
        var left = Math.round((canvas.width - width) / 2);

        ctx.save();
        ctx.fillStyle = 'rgba(8, 11, 12, 0.9)';
        ctx.strokeStyle = 'rgba(240, 226, 188, 0.5)';
        ctx.lineWidth = 2;
        roundRect(left, 16, width, 30, 8);
        ctx.fill();
        ctx.stroke();
        ctx.fillStyle = '#f5efe2';
        ctx.font = 'bold 13px "Yu Gothic"';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, left + width / 2, 31);
        ctx.restore();
    }

    function drawLocationBadge() {
        var text = '現在地  ' + currentMap().name;
        var width = Math.max(156, text.length * 12);
        var left = canvas.width - width - 16;
        var top = 16;

        ctx.save();
        ctx.fillStyle = 'rgba(8, 11, 12, 0.88)';
        ctx.strokeStyle = 'rgba(215, 186, 117, 0.48)';
        ctx.lineWidth = 2;
        roundRect(left, top, width, 32, 8);
        ctx.fill();
        ctx.stroke();
        ctx.fillStyle = '#d7ba75';
        ctx.font = '11px "Yu Gothic"';
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, left + 12, top + 16);
        ctx.restore();
    }

    function markerPalette(type, focused) {
        if (type === 'exit') {
            return {
                fill: focused ? 'rgba(199, 153, 79, 0.98)' : 'rgba(167, 129, 67, 0.92)',
                stroke: focused ? 'rgba(255, 224, 161, 0.96)' : 'rgba(231, 195, 132, 0.7)',
                glow: 'rgba(215, 186, 117, 0.24)'
            };
        }

        if (type === 'checkpoint') {
            return {
                fill: focused ? 'rgba(89, 142, 116, 0.98)' : 'rgba(74, 118, 96, 0.92)',
                stroke: focused ? 'rgba(183, 232, 204, 0.96)' : 'rgba(146, 206, 171, 0.72)',
                glow: 'rgba(103, 173, 138, 0.24)'
            };
        }

        return {
            fill: focused ? 'rgba(89, 126, 146, 0.98)' : 'rgba(70, 99, 115, 0.92)',
            stroke: focused ? 'rgba(193, 226, 241, 0.94)' : 'rgba(151, 188, 205, 0.72)',
            glow: 'rgba(129, 188, 205, 0.22)'
        };
    }

    function isFocusedEvent(event) {
        var front = facingTile();
        return (event.x === front.x && event.y === front.y) ||
            (event.x === state.player.x && event.y === state.player.y);
    }

    function shouldRevealEventLabel(event) {
        if (isFocusedEvent(event)) {
            return true;
        }

        var dx = Math.abs(event.x - state.player.x);
        var dy = Math.abs(event.y - state.player.y);
        return dx <= 1 && dy <= 1;
    }

    function roundRect(x, y, width, height, radius) {
        ctx.beginPath();
        ctx.moveTo(x + radius, y);
        ctx.lineTo(x + width - radius, y);
        ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
        ctx.lineTo(x + width, y + height - radius);
        ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
        ctx.lineTo(x + radius, y + height);
        ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
        ctx.lineTo(x, y + radius);
        ctx.quadraticCurveTo(x, y, x + radius, y);
        ctx.closePath();
    }

    function drawPlayer() {
        var px = state.player.renderX * TILE_SIZE;
        var py = state.player.renderY * TILE_SIZE;
        var bob = state.player.moving ? Math.sin((state.player.moveElapsed / PLAYER_MOVE_DURATION) * Math.PI) * 2.4 : 0;
        var frames = getPlayerSpriteFrames();

        if (frames && frames[state.player.dir]) {
            drawPlayerSpriteFrame(frames[state.player.dir], px, py - bob);
            return;
        }

        ctx.save();
        ctx.fillStyle = '#d6b05c';
        ctx.beginPath();
        ctx.arc(px + 24, py + 18 - bob, 10, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#8d6330';
        ctx.fillRect(px + 16, py + 28 - bob, 16, 14);
        ctx.fillStyle = '#2d2b2c';
        ctx.fillRect(px + 18, py + 40 - bob, 12, 6);
        drawFacingMarker(px, py - bob, '#f3e8c8');
        ctx.restore();
    }

    function getPlayerSpriteFrames() {
        if (playerSpriteFrames) {
            return playerSpriteFrames;
        }

        var source = images[SPRITES.playerSheet];
        if (!source || !source.complete || !source.naturalWidth) {
            return null;
        }

        var sheetCanvas = document.createElement('canvas');
        sheetCanvas.width = source.naturalWidth || source.width;
        sheetCanvas.height = source.naturalHeight || source.height;
        var sheetCtx = sheetCanvas.getContext('2d');
        sheetCtx.drawImage(source, 0, 0);

        var imageData = sheetCtx.getImageData(0, 0, sheetCanvas.width, sheetCanvas.height);
        var data = imageData.data;
        var keyR = data[0];
        var keyG = data[1];
        var keyB = data[2];

        for (var i = 0; i < data.length; i += 4) {
            var distance = Math.abs(data[i] - keyR) + Math.abs(data[i + 1] - keyG) + Math.abs(data[i + 2] - keyB);
            if (distance < 70) {
                data[i + 3] = 0;
            }
        }

        sheetCtx.putImageData(imageData, 0, 0);

        var frameWidth = Math.floor(sheetCanvas.width / 4);
        var frameHeight = sheetCanvas.height;
        playerSpriteFrames = {
            down: cropPlayerFrame(sheetCanvas, 0, frameWidth, frameHeight),
            left: cropPlayerFrame(sheetCanvas, 1, frameWidth, frameHeight),
            right: cropPlayerFrame(sheetCanvas, 2, frameWidth, frameHeight),
            up: cropPlayerFrame(sheetCanvas, 3, frameWidth, frameHeight)
        };

        return playerSpriteFrames;
    }

    function cropPlayerFrame(sheetCanvas, index, frameWidth, frameHeight) {
        var frameCanvas = document.createElement('canvas');
        frameCanvas.width = frameWidth;
        frameCanvas.height = frameHeight;
        var frameCtx = frameCanvas.getContext('2d');
        frameCtx.drawImage(sheetCanvas, index * frameWidth, 0, frameWidth, frameHeight, 0, 0, frameWidth, frameHeight);
        return frameCanvas;
    }

    function drawPlayerSpriteFrame(frame, px, py) {
        var scale = PLAYER_DRAW_HEIGHT / frame.height;
        var drawWidth = Math.round(frame.width * scale);
        var drawHeight = Math.round(frame.height * scale);
        var drawX = Math.round(px + (TILE_SIZE - drawWidth) / 2);
        var drawY = Math.round(py + TILE_SIZE - drawHeight - 1);

        ctx.save();
        ctx.imageSmoothingEnabled = false;
        ctx.drawImage(frame, drawX, drawY, drawWidth, drawHeight);
        ctx.restore();
    }

    function drawFacingMarker(px, py, color) {
        ctx.fillStyle = color;
        ctx.beginPath();
        if (state.player.dir === 'up') {
            ctx.moveTo(px + 24, py + 10);
            ctx.lineTo(px + 18, py + 18);
            ctx.lineTo(px + 30, py + 18);
        } else if (state.player.dir === 'down') {
            ctx.moveTo(px + 24, py + 44);
            ctx.lineTo(px + 18, py + 36);
            ctx.lineTo(px + 30, py + 36);
        } else if (state.player.dir === 'left') {
            ctx.moveTo(px + 10, py + 24);
            ctx.lineTo(px + 18, py + 18);
            ctx.lineTo(px + 18, py + 30);
        } else {
            ctx.moveTo(px + 38, py + 24);
            ctx.lineTo(px + 30, py + 18);
            ctx.lineTo(px + 30, py + 30);
        }
        ctx.closePath();
        ctx.fill();
    }

    function drawShadow(x, y) {
        var px = x * TILE_SIZE;
        var py = y * TILE_SIZE;
        var alpha = state.chase && state.chase.stun > 0 ? 0.35 : 0.78;
        ctx.save();
        ctx.globalAlpha = alpha;
        ctx.fillStyle = '#050606';
        ctx.beginPath();
        ctx.arc(px + 24, py + 20, 12, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillRect(px + 16, py + 28, 16, 14);
        ctx.fillStyle = '#8f4343';
        ctx.fillRect(px + 19, py + 17, 4, 3);
        ctx.fillRect(px + 25, py + 17, 4, 3);
        ctx.restore();
    }

    function drawAtmosphere() {
        var t = Date.now() / 1000;
        var wisps = [
            { x: 120, y: 78, r: 56, a: 0.055, dx: 8, dy: 3 },
            { x: 332, y: 168, r: 74, a: 0.04, dx: -6, dy: 5 },
            { x: 602, y: 106, r: 62, a: 0.048, dx: 5, dy: -4 },
            { x: 548, y: 402, r: 88, a: 0.032, dx: -7, dy: 6 }
        ];

        wisps.forEach(function (wisp, index) {
            var px = wisp.x + Math.sin(t * 0.35 + index) * wisp.dx;
            var py = wisp.y + Math.cos(t * 0.28 + index) * wisp.dy;
            var gradient = ctx.createRadialGradient(px, py, 0, px, py, wisp.r);
            gradient.addColorStop(0, 'rgba(196, 208, 214, ' + wisp.a + ')');
            gradient.addColorStop(1, 'rgba(196, 208, 214, 0)');
            ctx.fillStyle = gradient;
            ctx.beginPath();
            ctx.arc(px, py, wisp.r, 0, Math.PI * 2);
            ctx.fill();
        });
    }

    function drawVignette() {
        ctx.fillStyle = 'rgba(0, 0, 0, 0.18)';
        ctx.fillRect(0, 0, canvas.width, 18);
        ctx.fillRect(0, canvas.height - 18, canvas.width, 18);
        ctx.fillRect(0, 0, 18, canvas.height);
        ctx.fillRect(canvas.width - 18, 0, 18, canvas.height);

        if (state.fear === 'Heartbeat') {
            ctx.fillStyle = 'rgba(157, 81, 81, 0.11)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        } else if (state.fear === 'Caught') {
            ctx.fillStyle = 'rgba(157, 81, 81, 0.18)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }
    }

    function finishGame(route, title, body) {
        state.ending = { route: route, title: title, body: body };
        setText('#endingRoute', route);
        setText('#endingTitle', title);
        setText('#endingBody', body);
        removeClass('#endingOverlay', 'is-hidden');
        addClass('#menuOverlay', 'is-hidden');
        addClass('#dialogWindow', 'is-hidden');
    }

    function toggleMenu(forceOpen) {
        if (state.scene !== 'game' || state.dialog || state.ending) {
            return;
        }

        state.menuOpen = typeof forceOpen === 'boolean' ? forceOpen : !state.menuOpen;
        toggleClass('#menuOverlay', 'is-hidden', !state.menuOpen);
        updateMenu();
    }

    function handleKeyDown(event) {
        var key = event.key;

        if (state.dialog) {
            if (key === 'ArrowLeft' && state.dialog.choices && state.dialog.pageIndex === state.dialog.pages.length - 1) {
                state.choiceIndex = Math.max(0, state.choiceIndex - 1);
                renderChoices();
                event.preventDefault();
                return;
            }

            if (key === 'ArrowRight' && state.dialog.choices && state.dialog.pageIndex === state.dialog.pages.length - 1) {
                state.choiceIndex = Math.min(state.dialog.choices.length - 1, state.choiceIndex + 1);
                renderChoices();
                event.preventDefault();
                return;
            }

            if (key === 'Enter' || key === 'e' || key === 'E' || key === ' ') {
                advanceDialog();
                event.preventDefault();
                return;
            }

            if (key === 'Escape') {
                closeDialog();
                event.preventDefault();
                return;
            }
        }

        if (state.scene === 'title') {
            if (key === 'Enter' || key === ' ') {
                startGame(false);
                event.preventDefault();
            }
            return;
        }

        if (state.ending) {
            if (key === 'Enter' || key === 'Escape') {
                returnToTitle();
                event.preventDefault();
            }
            return;
        }

        if (state.menuOpen) {
            if (key === 'Escape' || key === 'Tab' || key === 'x' || key === 'X') {
                toggleMenu(false);
                event.preventDefault();
            }
            return;
        }

        var now = Date.now();
        if (now - state.lastInputAt < INPUT_COOLDOWN && (
            key === 'ArrowUp' || key === 'ArrowDown' || key === 'ArrowLeft' || key === 'ArrowRight' ||
            key === 'w' || key === 'W' || key === 'a' || key === 'A' || key === 's' || key === 'S' || key === 'd' || key === 'D'
        )) {
            event.preventDefault();
            return;
        }

        if (key === 'ArrowUp' || key === 'w' || key === 'W') {
            state.lastInputAt = now;
            movePlayer('up');
            event.preventDefault();
        } else if (key === 'ArrowDown' || key === 's' || key === 'S') {
            state.lastInputAt = now;
            movePlayer('down');
            event.preventDefault();
        } else if (key === 'ArrowLeft' || key === 'a' || key === 'A') {
            state.lastInputAt = now;
            movePlayer('left');
            event.preventDefault();
        } else if (key === 'ArrowRight' || key === 'd' || key === 'D') {
            state.lastInputAt = now;
            movePlayer('right');
            event.preventDefault();
        } else if (key === 'Enter' || key === 'e' || key === 'E') {
            interact();
            event.preventDefault();
        } else if (key === ' ') {
            useMatch();
            event.preventDefault();
        } else if (key === 'Tab' || key === 'x' || key === 'X') {
            toggleMenu();
            event.preventDefault();
        }
    }

    function stepClock(timestamp) {
        if (!lastFrame) {
            lastFrame = timestamp;
        }

        var deltaMs = timestamp - lastFrame;
        lastFrame = timestamp;

        if (state.scene === 'game' && !state.dialog && !state.menuOpen && !state.ending) {
            state.playAccumulator = (state.playAccumulator || 0) + deltaMs;
            if (state.playAccumulator >= 1000) {
                state.playSeconds += Math.floor(state.playAccumulator / 1000);
                state.playAccumulator = state.playAccumulator % 1000;
                updateMenu();
            }
        }

        updatePlayerMotion(deltaMs);
        draw();
        animationId = window.requestAnimationFrame(stepClock);
    }

    function bindUi() {
        bind('#startGameBtn', 'click', function () {
            startGame(false);
        });

        bind('#loadGameBtn', 'click', function () {
            loadGame(true);
        });

        bind('#howToBtn', 'click', function () {
            openDialog('操作説明', null, [
                '矢印キーまたはWASDで移動。EまたはEnterで調べる。',
                'TabまたはXでメニューを開く。追跡中はSpaceでマッチを使って影を止められる。'
            ]);
        });

        bind('#endingRestartBtn', 'click', returnToTitle);
        bind('#menuBtn', 'click', function () { toggleMenu(); });
        bind('#interactBtn', 'click', interact);
        bind('#matchBtn', 'click', useMatch);
        bind('#saveBtn', 'click', saveGame);
        bind('#loadBtn', 'click', function () { loadGame(false); });
        bind('#closeMenuBtn', 'click', function () { toggleMenu(false); });

        bind('.pad-btn', 'click', function (event) {
            var move = event.currentTarget.getAttribute('data-move');
            var action = event.currentTarget.getAttribute('data-action');

            if (move) {
                movePlayer(move);
                return;
            }

            if (action === 'interact') {
                interact();
            } else if (action === 'match') {
                useMatch();
            }
        });

        window.addEventListener('keydown', handleKeyDown);
    }

    ready(function () {
        var params = new URLSearchParams(window.location.search);
        canvas = document.getElementById('gameCanvas');
        ctx = canvas.getContext('2d');
        setupAssets();
        mapEvents = createEvents();
        rebalanceShortRunContent();
        decorateEventMetadata();
        state = buildInitialState();
        bindUi();
        refreshLoadButton();
        updateHud();
        draw();
        animationId = window.requestAnimationFrame(stepClock);

        if (params.get('autostart') === '1') {
            startGame(false);
        } else if (params.get('autoload') === '1' && window.localStorage.getItem(STORAGE_KEY)) {
            loadGame(true);
        }
    });
})(window, document);
