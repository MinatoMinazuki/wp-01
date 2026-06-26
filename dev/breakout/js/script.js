(function (window, document) {
    'use strict';

    var jq = window.jQuery || null;

    function ready(callback) {
        if (jq) {
            jq(callback);
            return;
        }

        if (document.readyState !== 'loading') {
            callback();
            return;
        }

        document.addEventListener('DOMContentLoaded', callback);
    }

    function bind(target, eventName, handler) {
        if (jq && typeof target === 'string') {
            jq(target).on(eventName, handler);
            return;
        }

        var elements = typeof target === 'string' ? document.querySelectorAll(target) : [target];
        Array.prototype.forEach.call(elements, function (element) {
            element.addEventListener(eventName, handler);
        });
    }

    function setText(selector, value) {
        if (jq) {
            jq(selector).text(value);
            return;
        }

        document.querySelector(selector).textContent = value;
    }

    ready(function () {
        var canvas = document.getElementById('gameCanvas');
        var ctx = canvas.getContext('2d');
        var overlay = document.getElementById('stageOverlay');
        var overlayTitle = document.getElementById('overlayTitle');
        var overlayText = document.getElementById('overlayText');
        var speedRange = document.getElementById('speedRange');
        var paddleRange = document.getElementById('paddleRange');
        var backgroundInput = document.getElementById('backgroundInput');
        var imageName = document.getElementById('imageName');
        var params = new URLSearchParams(window.location.search);
        var demoMode = params.get('demo') === '1';
        var autoStart = params.get('autostart') === '1' || demoMode;

        var field = {
            width: canvas.width,
            height: canvas.height
        };

        var colors = {
            background: '#fbfcff',
            grid: '#edf1f7',
            ball: '#111827',
            paddle: '#2563eb',
            shadow: 'rgba(17, 24, 39, 0.16)',
            bricks: ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6'],
            items: {
                triple: '#7c3aed',
                add: '#059669',
                wide: '#f59e0b',
                pierce: '#dc2626'
            }
        };

        var settings = {
            rowCount: 5,
            columnCount: 9,
            brickGap: 0,
            brickTop: 72,
            brickSide: 36,
            brickHeight: 30,
            paddleHeight: 14,
            paddleSpeed: 11,
            ballRadius: 8,
            startLives: 3,
            defaultSpeed: 0.5,
            defaultPaddleWidth: 120,
            itemDropRate: 0.35,
            itemSize: 28,
            itemFallSpeed: 2.6,
            wideBonus: 70,
            wideDuration: 12 * 60,
            pierceDuration: 10 * 60,
            comboWindow: 90,
            maxMultiplier: 6,
            particleCount: 12
        };

        var state = {};
        var animationId = null;
        var backgroundImage = null;
        var backgroundObjectUrl = '';

        function createBricks() {
            var bricks = [];
            var totalGap = settings.brickGap * (settings.columnCount - 1);
            var brickWidth = (field.width - settings.brickSide * 2 - totalGap) / settings.columnCount;

            for (var row = 0; row < settings.rowCount; row++) {
                for (var column = 0; column < settings.columnCount; column++) {
                    bricks.push({
                        x: settings.brickSide + column * (brickWidth + settings.brickGap),
                        y: settings.brickTop + row * (settings.brickHeight + settings.brickGap),
                        width: brickWidth,
                        height: settings.brickHeight,
                        color: colors.bricks[row % colors.bricks.length],
                        active: true
                    });
                }
            }

            return bricks;
        }

        function resetGame() {
            state = {
                running: false,
                paused: false,
                gameOver: false,
                cleared: false,
                score: 0,
                lives: settings.startLives,
                speedMultiplier: getSpeedMultiplier(),
                basePaddleWidth: getPaddleWidth(),
                wideTimer: 0,
                pierceTimer: 0,
                comboCount: 0,
                comboTimer: 0,
                comboMultiplier: 1,
                demoMode: demoMode,
                keys: {
                    left: false,
                    right: false
                },
                paddle: {
                    x: 0,
                    y: field.height - 36,
                    width: getPaddleWidth(),
                    height: settings.paddleHeight
                },
                balls: [],
                bricks: createBricks(),
                items: [],
                particles: [],
                lastFrame: 0
            };

            centerPaddle();
            resetBalls();
            updateHud();
            setOverlay('START', 'Click or press Space', false);
            draw();
        }

        function resetBalls() {
            state.balls = [createBall(field.width / 2, field.height - 74, 3.7, -4.4)];
            centerPaddle();
        }

        function createBall(x, y, dx, dy) {
            return {
                x: x,
                y: y,
                dx: dx,
                dy: dy,
                radius: settings.ballRadius
            };
        }

        function centerPaddle() {
            state.paddle.x = (field.width - state.paddle.width) / 2;
        }

        function updateHud() {
            setText('#scoreValue', state.score);
            setText('#livesValue', state.lives);
            setText('#ballsValue', state.balls.length);
            setText('#comboValue', 'x' + state.comboMultiplier);
            setText('#speedValue', state.speedMultiplier.toFixed(1) + 'x');
            setText('#paddleValue', state.basePaddleWidth + 'px');
        }

        function setOverlay(title, text, hidden) {
            overlayTitle.textContent = title;
            overlayText.textContent = text;
            overlay.classList.toggle('is-hidden', hidden);
        }

        function startGame() {
            if (state.gameOver || state.cleared) {
                resetGame();
            }

            state.running = true;
            state.paused = false;
            state.lastFrame = 0;
            setOverlay('', '', true);
            canvas.focus();
            requestLoop();
        }

        function togglePause() {
            if (!state.running || state.gameOver || state.cleared) {
                return;
            }

            state.paused = !state.paused;
            if (state.paused) {
                setOverlay('PAUSE', 'Click or press Space', false);
                cancelAnimationFrame(animationId);
                animationId = null;
            } else {
                state.lastFrame = 0;
                setOverlay('', '', true);
                canvas.focus();
                requestLoop();
            }
        }

        function requestLoop() {
            if (animationId !== null) {
                return;
            }

            animationId = requestAnimationFrame(loop);
        }

        function loop(timestamp) {
            animationId = null;

            if (!state.running || state.paused) {
                return;
            }

            if (!state.lastFrame) {
                state.lastFrame = timestamp;
            }

            var delta = Math.min((timestamp - state.lastFrame) / 16.6667, 2.4);
            state.lastFrame = timestamp;

            update(delta);
            draw();

            if (state.running && !state.paused) {
                animationId = requestAnimationFrame(loop);
            }
        }

        function update(delta) {
            updatePaddleWidth(delta);
            updateCombo(delta);
            movePaddle(delta);
            moveBalls(delta);
            updateItems(delta);
            updateParticles(delta);
        }

        function updatePaddleWidth(delta) {
            var center = state.paddle.x + state.paddle.width / 2;

            if (state.wideTimer > 0) {
                state.wideTimer = Math.max(0, state.wideTimer - delta);
            }

            if (state.pierceTimer > 0) {
                state.pierceTimer = Math.max(0, state.pierceTimer - delta);
            }

            state.paddle.width = getCurrentPaddleWidth();
            state.paddle.x = clamp(center - state.paddle.width / 2, 0, field.width - state.paddle.width);
        }

        function updateCombo(delta) {
            if (state.comboTimer > 0) {
                state.comboTimer = Math.max(0, state.comboTimer - delta);
            }

            if (state.comboTimer === 0 && state.comboCount !== 0) {
                state.comboCount = 0;
                state.comboMultiplier = 1;
                updateHud();
            }
        }

        function getCurrentPaddleWidth() {
            var bonus = state.wideTimer > 0 ? settings.wideBonus : 0;
            return clamp(state.basePaddleWidth + bonus, 60, field.width);
        }

        function movePaddle(delta) {
            if (state.demoMode && state.running && !state.paused && state.balls.length > 0) {
                autoMovePaddle(delta);
                return;
            }

            var direction = 0;

            if (state.keys.left) {
                direction -= 1;
            }

            if (state.keys.right) {
                direction += 1;
            }

            if (direction !== 0) {
                movePaddleBy(direction * settings.paddleSpeed * delta);
            }
        }

        function movePaddleBy(amount) {
            state.paddle.x = clamp(state.paddle.x + amount, 0, field.width - state.paddle.width);
        }

        function autoMovePaddle(delta) {
            var targetBall = state.balls[0];
            var bestY = -Infinity;

            for (var i = 0; i < state.balls.length; i++) {
                if (state.balls[i].y > bestY) {
                    bestY = state.balls[i].y;
                    targetBall = state.balls[i];
                }
            }

            var targetX = targetBall.x - state.paddle.width / 2;
            var distance = targetX - state.paddle.x;
            var step = clamp(distance, -settings.paddleSpeed * 1.15 * delta, settings.paddleSpeed * 1.15 * delta);
            movePaddleBy(step);
        }

        function moveBalls(delta) {
            var activeBalls = [];

            for (var i = 0; i < state.balls.length; i++) {
                var ball = state.balls[i];
                ball.x += ball.dx * state.speedMultiplier * delta;
                ball.y += ball.dy * state.speedMultiplier * delta;

                hitWalls(ball);
                hitPaddle(ball);
                hitBricks(ball);

                if (ball.y - ball.radius <= field.height + 28) {
                    activeBalls.push(ball);
                }
            }

            state.balls = activeBalls;

            if (state.balls.length === 0 && state.running && !state.cleared) {
                loseLife();
            }

            updateHud();
        }

        function hitWalls(ball) {
            if (ball.x - ball.radius <= 0 || ball.x + ball.radius >= field.width) {
                ball.dx *= -1;
                ball.x = clamp(ball.x, ball.radius, field.width - ball.radius);
            }

            if (ball.y - ball.radius <= 0) {
                ball.dy *= -1;
                ball.y = ball.radius;
            }
        }

        function hitPaddle(ball) {
            var paddle = state.paddle;
            var isInsideX = ball.x + ball.radius >= paddle.x && ball.x - ball.radius <= paddle.x + paddle.width;
            var isInsideY = ball.y + ball.radius >= paddle.y && ball.y - ball.radius <= paddle.y + paddle.height;

            if (!isInsideX || !isInsideY || ball.dy <= 0) {
                return;
            }

            var hitPosition = (ball.x - (paddle.x + paddle.width / 2)) / (paddle.width / 2);
            var angle = hitPosition * (Math.PI / 3);
            var speed = Math.min(getBallSpeed(ball) + 0.08, 8.2);

            ball.dx = speed * Math.sin(angle);
            ball.dy = -Math.abs(speed * Math.cos(angle));
            ball.y = paddle.y - ball.radius;
        }

        function hitBricks(ball) {
            for (var i = 0; i < state.bricks.length; i++) {
                var brick = state.bricks[i];

                if (!brick.active || !circleRectOverlap(ball, brick)) {
                    continue;
                }

                brick.active = false;
                onBrickBroken(ball, brick);
                maybeDropItem(brick);
                if (state.pierceTimer <= 0) {
                    bounceFromBrick(ball, brick);
                } else {
                    accelerateBall(ball, 0.08);
                }

                if (getRemainingBricks() === 0) {
                    clearGame();
                }

                return;
            }
        }

        function bounceFromBrick(ball, brick) {
            var overlapLeft = Math.abs((ball.x + ball.radius) - brick.x);
            var overlapRight = Math.abs((brick.x + brick.width) - (ball.x - ball.radius));
            var overlapTop = Math.abs((ball.y + ball.radius) - brick.y);
            var overlapBottom = Math.abs((brick.y + brick.height) - (ball.y - ball.radius));
            var minOverlap = Math.min(overlapLeft, overlapRight, overlapTop, overlapBottom);

            if (minOverlap === overlapLeft || minOverlap === overlapRight) {
                ball.dx *= -1;
            } else {
                ball.dy *= -1;
            }
        }

        function onBrickBroken(ball, brick) {
            state.comboCount++;
            state.comboTimer = settings.comboWindow;
            state.comboMultiplier = clamp(1 + Math.floor(state.comboCount / 3), 1, settings.maxMultiplier);
            state.score += 10 * state.comboMultiplier;
            spawnParticles(brick, ball);
        }

        function accelerateBall(ball, boost) {
            var speed = Math.min(getBallSpeed(ball) + boost, 8.8);
            var angle = Math.atan2(ball.dy, ball.dx);
            ball.dx = Math.cos(angle) * speed;
            ball.dy = Math.sin(angle) * speed;
        }

        function maybeDropItem(brick) {
            if (Math.random() > settings.itemDropRate) {
                return;
            }

            var itemTypes = ['triple', 'add', 'wide', 'pierce'];
            var type = itemTypes[Math.floor(Math.random() * itemTypes.length)];

            state.items.push({
                type: type,
                x: brick.x + brick.width / 2,
                y: brick.y + brick.height / 2,
                width: settings.itemSize,
                height: settings.itemSize,
                dy: settings.itemFallSpeed
            });
        }

        function updateItems(delta) {
            var activeItems = [];

            for (var i = 0; i < state.items.length; i++) {
                var item = state.items[i];
                item.y += item.dy * delta;

                if (itemHitsPaddle(item)) {
                    applyItem(item.type);
                    continue;
                }

                if (item.y - item.height / 2 < field.height + 40) {
                    activeItems.push(item);
                }
            }

            state.items = activeItems;
        }

        function itemHitsPaddle(item) {
            var paddle = state.paddle;
            var left = item.x - item.width / 2;
            var right = item.x + item.width / 2;
            var top = item.y - item.height / 2;
            var bottom = item.y + item.height / 2;

            return right >= paddle.x &&
                left <= paddle.x + paddle.width &&
                bottom >= paddle.y &&
                top <= paddle.y + paddle.height;
        }

        function applyItem(type) {
            if (type === 'triple') {
                tripleBalls();
            } else if (type === 'add') {
                addBalls(3);
            } else if (type === 'wide') {
                state.wideTimer = settings.wideDuration;
            } else if (type === 'pierce') {
                state.pierceTimer = settings.pierceDuration;
            }

            updateHud();
        }

        function tripleBalls() {
            var originalBalls = state.balls.slice();

            for (var i = 0; i < originalBalls.length; i++) {
                cloneBall(originalBalls[i], -0.42);
                cloneBall(originalBalls[i], 0.42);
            }
        }

        function addBalls(count) {
            var source = state.balls[0] || createBall(field.width / 2, field.height - 74, 3.7, -4.4);

            for (var i = 0; i < count; i++) {
                cloneBall(source, (i - 1) * 0.48);
            }
        }

        function cloneBall(source, angleOffset) {
            if (state.balls.length >= 24) {
                return;
            }

            var speed = Math.max(getBallSpeed(source), 4.2);
            var angle = Math.atan2(source.dy, source.dx) + angleOffset;

            state.balls.push(createBall(
                source.x,
                source.y,
                Math.cos(angle) * speed,
                Math.sin(angle) * speed
            ));
        }

        function loseLife() {
            state.lives--;
            state.items = [];
            state.wideTimer = 0;
            state.pierceTimer = 0;
            updateHud();

            if (state.lives <= 0) {
                state.gameOver = true;
                state.running = false;
                setOverlay('GAME OVER', 'Restart to try again', false);
                cancelAnimationFrame(animationId);
                animationId = null;
                return;
            }

            state.running = false;
            resetBalls();
            state.comboCount = 0;
            state.comboTimer = 0;
            state.comboMultiplier = 1;
            draw();
            updateHud();
            setOverlay('READY', 'Click or press Space', false);
        }

        function clearGame() {
            state.cleared = true;
            state.running = false;
            spawnCelebration();
            setOverlay('CLEAR', 'Restart to play again', false);
            cancelAnimationFrame(animationId);
            animationId = null;
            draw();
        }

        function getRemainingBricks() {
            return state.bricks.filter(function (brick) {
                return brick.active;
            }).length;
        }

        function draw() {
            drawBackground();
            drawBricks();
            drawItems();
            drawParticles();
            drawPaddle();
            drawBalls();
        }

        function drawBackground() {
            ctx.clearRect(0, 0, field.width, field.height);
            ctx.fillStyle = colors.background;
            ctx.fillRect(0, 0, field.width, field.height);

            if (backgroundImage) {
                drawImageCover(backgroundImage, getBrickBounds());
            } else {
                drawGrid();
            }
        }

        function drawGrid() {
            ctx.strokeStyle = colors.grid;
            ctx.lineWidth = 1;

            for (var x = 40; x < field.width; x += 40) {
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x, field.height);
                ctx.stroke();
            }

            for (var y = 40; y < field.height; y += 40) {
                ctx.beginPath();
                ctx.moveTo(0, y);
                ctx.lineTo(field.width, y);
                ctx.stroke();
            }
        }

        function drawImageCover(image, area) {
            var imageRatio = image.width / image.height;
            var areaRatio = area.width / area.height;
            var sourceWidth = image.width;
            var sourceHeight = image.height;
            var sourceX = 0;
            var sourceY = 0;

            if (imageRatio > areaRatio) {
                sourceWidth = image.height * areaRatio;
                sourceX = (image.width - sourceWidth) / 2;
            } else {
                sourceHeight = image.width / areaRatio;
                sourceY = (image.height - sourceHeight) / 2;
            }

            ctx.drawImage(
                image,
                sourceX,
                sourceY,
                sourceWidth,
                sourceHeight,
                area.x,
                area.y,
                area.width,
                area.height
            );
        }

        function getBrickBounds() {
            var totalGap = settings.brickGap * (settings.columnCount - 1);
            var brickWidth = (field.width - settings.brickSide * 2 - totalGap) / settings.columnCount;

            return {
                x: settings.brickSide,
                y: settings.brickTop,
                width: brickWidth * settings.columnCount + totalGap,
                height: settings.brickHeight * settings.rowCount + settings.brickGap * (settings.rowCount - 1)
            };
        }

        function drawBricks() {
            state.bricks.forEach(function (brick) {
                if (!brick.active) {
                    return;
                }

                ctx.fillStyle = brick.color;
                ctx.fillRect(brick.x, brick.y, brick.width, brick.height);
            });
        }

        function drawItems() {
            state.items.forEach(function (item) {
                var label = getItemLabel(item.type);

                ctx.save();
                ctx.fillStyle = colors.items[item.type];
                ctx.shadowColor = colors.shadow;
                ctx.shadowBlur = 8;
                ctx.beginPath();
                roundRect(item.x - item.width / 2, item.y - item.height / 2, item.width, item.height, 8);
                ctx.fill();
                ctx.shadowBlur = 0;
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 13px Arial';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(label, item.x, item.y + 1);
                ctx.restore();
            });
        }

        function getItemLabel(type) {
            if (type === 'triple') {
                return 'x3';
            }

            if (type === 'add') {
                return '+3';
            }

            if (type === 'pierce') {
                return 'P';
            }

            return 'W';
        }

        function drawPaddle() {
            var paddle = state.paddle;
            ctx.fillStyle = colors.paddle;
            roundRect(paddle.x, paddle.y, paddle.width, paddle.height, 7);
            ctx.fill();

            if (state.pierceTimer > 0) {
                ctx.fillStyle = 'rgba(255, 255, 255, 0.18)';
                roundRect(paddle.x + 6, paddle.y + 2, paddle.width - 12, paddle.height - 4, 5);
                ctx.fill();
            }
        }

        function drawBalls() {
            state.balls.forEach(function (ball) {
                ctx.beginPath();
                ctx.arc(ball.x, ball.y, ball.radius, 0, Math.PI * 2);
                ctx.fillStyle = state.pierceTimer > 0 ? '#dc2626' : colors.ball;
                ctx.fill();
                ctx.closePath();

                if (state.pierceTimer > 0) {
                    ctx.beginPath();
                    ctx.arc(ball.x, ball.y, ball.radius + 4, 0, Math.PI * 2);
                    ctx.strokeStyle = 'rgba(220, 38, 38, 0.28)';
                    ctx.lineWidth = 2;
                    ctx.stroke();
                    ctx.closePath();
                }
            });
        }

        function spawnParticles(brick, ball) {
            for (var i = 0; i < settings.particleCount; i++) {
                var angle = (Math.PI * 2 * i) / settings.particleCount;
                var speed = 1.2 + Math.random() * 2.6;
                state.particles.push({
                    x: ball.x,
                    y: ball.y,
                    dx: Math.cos(angle) * speed,
                    dy: Math.sin(angle) * speed,
                    radius: 1.5 + Math.random() * 2.4,
                    life: 24 + Math.random() * 18,
                    color: brick.color
                });
            }
        }

        function updateParticles(delta) {
            var next = [];

            for (var i = 0; i < state.particles.length; i++) {
                var particle = state.particles[i];
                particle.x += particle.dx * delta;
                particle.y += particle.dy * delta;
                particle.dy += 0.04 * delta;
                particle.life -= delta;

                if (particle.life > 0) {
                    next.push(particle);
                }
            }

            state.particles = next;
        }

        function drawParticles() {
            for (var i = 0; i < state.particles.length; i++) {
                var particle = state.particles[i];
                ctx.beginPath();
                ctx.arc(particle.x, particle.y, particle.radius, 0, Math.PI * 2);
                ctx.fillStyle = particle.color;
                ctx.globalAlpha = Math.max(0, particle.life / 42);
                ctx.fill();
                ctx.closePath();
            }

            ctx.globalAlpha = 1;
        }

        function spawnCelebration() {
            for (var i = 0; i < 90; i++) {
                state.particles.push({
                    x: 60 + Math.random() * (field.width - 120),
                    y: 80 + Math.random() * 120,
                    dx: -3 + Math.random() * 6,
                    dy: -2 + Math.random() * 5,
                    radius: 2 + Math.random() * 2.8,
                    life: 36 + Math.random() * 30,
                    color: colors.bricks[i % colors.bricks.length]
                });
            }
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

        function circleRectOverlap(circle, rect) {
            var closestX = clamp(circle.x, rect.x, rect.x + rect.width);
            var closestY = clamp(circle.y, rect.y, rect.y + rect.height);
            var dx = circle.x - closestX;
            var dy = circle.y - closestY;

            return dx * dx + dy * dy <= circle.radius * circle.radius;
        }

        function getBallSpeed(ball) {
            return Math.sqrt(ball.dx * ball.dx + ball.dy * ball.dy);
        }

        function getSpeedMultiplier() {
            var value = parseFloat(speedRange.value);

            if (isNaN(value)) {
                return settings.defaultSpeed;
            }

            return value;
        }

        function getPaddleWidth() {
            var value = parseInt(paddleRange.value, 10);

            if (isNaN(value)) {
                return settings.defaultPaddleWidth;
            }

            return value;
        }

        function changeSpeed() {
            state.speedMultiplier = getSpeedMultiplier();
            updateHud();
        }

        function changePaddleWidth() {
            var center = state.paddle.x + state.paddle.width / 2;
            state.basePaddleWidth = getPaddleWidth();
            state.paddle.width = getCurrentPaddleWidth();
            state.paddle.x = clamp(center - state.paddle.width / 2, 0, field.width - state.paddle.width);
            updateHud();
            if (!state.running || state.paused) {
                draw();
            }
        }

        function clamp(value, min, max) {
            return Math.max(min, Math.min(max, value));
        }

        function movePaddleToClientX(clientX) {
            var rect = canvas.getBoundingClientRect();
            var scaleX = field.width / rect.width;
            var x = (clientX - rect.left) * scaleX;

            state.paddle.x = clamp(x - state.paddle.width / 2, 0, field.width - state.paddle.width);
            if (!state.running || state.paused) {
                draw();
            }
        }

        function handleKeyDown(event) {
            if (event.key === 'ArrowLeft' || event.key === 'a' || event.key === 'A') {
                state.keys.left = true;
                if (!event.repeat) {
                    movePaddleBy(-settings.paddleSpeed * 0.6);
                }
                preventGameKey(event);
            } else if (event.key === 'ArrowRight' || event.key === 'd' || event.key === 'D') {
                state.keys.right = true;
                if (!event.repeat) {
                    movePaddleBy(settings.paddleSpeed * 0.6);
                }
                preventGameKey(event);
            } else if (event.key === ' ') {
                if (state.running) {
                    togglePause();
                } else {
                    startGame();
                }
                preventGameKey(event);
            }

            if (!state.running || state.paused) {
                draw();
            }
        }

        function handleKeyUp(event) {
            if (event.key === 'ArrowLeft' || event.key === 'a' || event.key === 'A') {
                state.keys.left = false;
                preventGameKey(event);
            } else if (event.key === 'ArrowRight' || event.key === 'd' || event.key === 'D') {
                state.keys.right = false;
                preventGameKey(event);
            }
        }

        function preventGameKey(event) {
            event.preventDefault();
            event.stopPropagation();
        }

        function clearKeys() {
            state.keys.left = false;
            state.keys.right = false;
        }

        function toggleDemoMode() {
            state.demoMode = !state.demoMode;
            if (state.demoMode) {
                clearKeys();
            }
        }

        function handleBackgroundChange() {
            var file = backgroundInput.files && backgroundInput.files[0];

            if (!file) {
                return;
            }

            if (backgroundObjectUrl) {
                URL.revokeObjectURL(backgroundObjectUrl);
            }

            backgroundObjectUrl = URL.createObjectURL(file);
            backgroundImage = new Image();
            backgroundImage.onload = function () {
                imageName.textContent = file.name;
                draw();
            };
            backgroundImage.src = backgroundObjectUrl;
        }

        function clearBackground() {
            if (backgroundObjectUrl) {
                URL.revokeObjectURL(backgroundObjectUrl);
            }

            backgroundObjectUrl = '';
            backgroundImage = null;
            backgroundInput.value = '';
            imageName.textContent = 'No image';
            draw();
        }

        window.addEventListener('keydown', handleKeyDown, true);
        window.addEventListener('keyup', handleKeyUp, true);
        window.addEventListener('blur', clearKeys);

        bind(canvas, 'mousemove', function (event) {
            movePaddleToClientX(event.clientX);
        });

        bind(canvas, 'touchmove', function (event) {
            if (event.touches.length > 0) {
                movePaddleToClientX(event.touches[0].clientX);
            }
            event.preventDefault();
        });

        bind('#startBtn', 'click', startGame);
        bind('#pauseBtn', 'click', togglePause);
        bind('#demoBtn', 'click', function () {
            toggleDemoMode();
            if (!state.running) {
                startGame();
            }
        });
        bind('#restartBtn', 'click', resetGame);
        bind('#speedRange', 'input', changeSpeed);
        bind('#speedRange', 'change', changeSpeed);
        bind('#paddleRange', 'input', changePaddleWidth);
        bind('#paddleRange', 'change', changePaddleWidth);
        bind('#backgroundInput', 'change', handleBackgroundChange);
        bind('#clearBackgroundBtn', 'click', clearBackground);
        bind('#stageOverlay', 'click', function () {
            if (state.paused) {
                togglePause();
                return;
            }

            startGame();
        });

        resetGame();

        if (autoStart) {
            startGame();
        }
    });
})(window, document);
