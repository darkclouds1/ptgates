/**
 * PTGates Quiz - 드로잉 기능 모듈
 * 
 * 드로잉 툴바, 펜 설정, 자동 정렬 기능
 */

(function() {
    'use strict';

    /**
     * QuizState 안전하게 가져오기
     */
    function getQuizState() {
        return window.PTGQuiz?.QuizState;
    }

    // QuizState에 자동 정렬 관련 변수 추가 (초기화 시)
    const QuizState = getQuizState();
    if (QuizState) {
        QuizState.drawingPoints = QuizState.drawingPoints || [];
        QuizState.autoAlignTimeout = QuizState.autoAlignTimeout || null;
        QuizState.autoAlignEnabled = QuizState.autoAlignEnabled !== undefined ? QuizState.autoAlignEnabled : true;
        QuizState.currentStrokeStartIndex = QuizState.currentStrokeStartIndex || -1;
    }

    /**
     * localStorage에서 펜 설정 불러오기
     */
    function loadPenSettings() {
        const QuizState = getQuizState();
        if (!QuizState) return;

        try {
            // 색상 불러오기
            const savedColor = localStorage.getItem('ptg-quiz-pen-color');
            if (savedColor && savedColor !== 'null') {
                QuizState.penColor = savedColor;
            }

            // 두께 불러오기
            const savedWidth = localStorage.getItem('ptg-quiz-pen-width');
            if (savedWidth && !isNaN(parseInt(savedWidth))) {
                QuizState.penWidth = parseInt(savedWidth);
            }

            // 불투명도 불러오기
            const savedAlpha = localStorage.getItem('ptg-quiz-pen-alpha');
            if (savedAlpha && !isNaN(parseFloat(savedAlpha))) {
                QuizState.penAlpha = parseFloat(savedAlpha);
            }
        } catch (e) {
            // localStorage 사용 불가 시 기본값 유지
        }
    }

    /**
     * 펜 설정을 localStorage에 저장
     */
    function savePenSettings() {
        const QuizState = getQuizState();
        if (!QuizState) return;

        try {
            localStorage.setItem('ptg-quiz-pen-color', QuizState.penColor);
            localStorage.setItem('ptg-quiz-pen-width', String(QuizState.penWidth));
            localStorage.setItem('ptg-quiz-pen-alpha', String(QuizState.penAlpha));
        } catch (e) {
            // localStorage 사용 불가 시 무시
        }
    }

    /**
     * 펜 메뉴 초기화
     */
    function initializePenMenu() {
        const QuizState = getQuizState();
        if (!QuizState) return;

        // 저장된 색상 선택 (기본값: 빨강)
        const savedColor = QuizState.penColor || 'rgb(255, 0, 0)';
        const colorBtn = document.querySelector(`.ptg-pen-color-btn[data-color="${savedColor}"]`);
        if (colorBtn) {
            colorBtn.classList.add('active');
        } else {
            // 저장된 색상이 없으면 기본 색상 선택
            const redColorBtn = document.querySelector('.ptg-pen-color-btn[data-color="rgb(255, 0, 0)"]');
            if (redColorBtn) {
                redColorBtn.classList.add('active');
            }
        }

        // 두께 슬라이더 이벤트 설정 및 저장된 값 복원
        const widthSlider = document.getElementById('ptg-pen-width-slider');
        const widthValue = document.getElementById('ptg-pen-width-value');
        if (widthSlider && widthValue) {
            // 저장된 값으로 슬라이더 초기화
            widthSlider.value = QuizState.penWidth || 10;
            widthValue.textContent = QuizState.penWidth || 10;

            widthSlider.addEventListener('input', function (e) {
                const width = parseInt(e.target.value);
                setPenWidth(width);
                widthValue.textContent = width;
                savePenSettings();
            });

            widthSlider.addEventListener('change', function (e) {
                const width = parseInt(e.target.value);
                setPenWidth(width);
                widthValue.textContent = width;
                savePenSettings();
            });
        }

        // 불투명도 슬라이더 이벤트 설정 및 저장된 값 복원
        const alphaSlider = document.getElementById('ptg-pen-alpha-slider');
        const alphaValue = document.getElementById('ptg-pen-alpha-value');
        if (alphaSlider && alphaValue) {
            // 저장된 값으로 슬라이더 초기화 (기본값: 50%)
            const alphaPercent = Math.round((QuizState.penAlpha || 0.5) * 100);
            alphaSlider.value = alphaPercent;
            alphaValue.textContent = alphaPercent;

            alphaSlider.addEventListener('input', function (e) {
                const alphaPercent = parseInt(e.target.value);
                const alpha = alphaPercent / 100;
                setPenAlpha(alpha);
                alphaValue.textContent = alphaPercent;
                savePenSettings();
            });

            alphaSlider.addEventListener('change', function (e) {
                const alphaPercent = parseInt(e.target.value);
                const alpha = alphaPercent / 100;
                setPenAlpha(alpha);
                alphaValue.textContent = alphaPercent;
                savePenSettings();
            });
        }

        // 외부 클릭/터치 시 메뉴 닫기 (PC와 모바일 모두 지원)
        function closeMenuIfOutside(e) {
            const penMenu = document.getElementById('ptg-pen-menu');
            const penControls = document.querySelector('.ptg-pen-controls');
            if (penMenu && penControls &&
                !e.target.closest('.ptg-pen-controls') &&
                penMenu.style.display !== 'none') {
                penMenu.style.display = 'none';
            }
        }

        // 클릭 이벤트 (PC)
        document.addEventListener('click', closeMenuIfOutside);

        // 터치 이벤트 (아이패드/모바일)
        document.addEventListener('touchstart', function (e) {
            const penMenu = document.getElementById('ptg-pen-menu');
            const penControls = document.querySelector('.ptg-pen-controls');
            if (penMenu && penControls && penMenu.style.display !== 'none') {
                const touchTarget = e.target;
                if (!penControls.contains(touchTarget)) {
                    setTimeout(() => {
                        penMenu.style.display = 'none';
                    }, 100);
                }
            }
        }, { passive: true });

        // 포커스가 벗어날 때 메뉴 닫기 (PC)
        document.addEventListener('focusout', function (e) {
            const penMenu = document.getElementById('ptg-pen-menu');
            if (penMenu && penMenu.style.display !== 'none') {
                setTimeout(() => {
                    const activeElement = document.activeElement;
                    const penControls = document.querySelector('.ptg-pen-controls');
                    if (penControls && !penControls.contains(activeElement)) {
                        penMenu.style.display = 'none';
                    }
                }, 0);
            }
        });

        // blur 이벤트도 추가 (터치 디바이스 지원)
        const penControls = document.querySelector('.ptg-pen-controls');
        if (penControls) {
            penControls.addEventListener('blur', function (e) {
                const penMenu = document.getElementById('ptg-pen-menu');
                if (penMenu && penMenu.style.display !== 'none') {
                    setTimeout(() => {
                        const activeElement = document.activeElement;
                        if (!penControls.contains(activeElement)) {
                            penMenu.style.display = 'none';
                        }
                    }, 100);
                }
            }, true);
        }

        // 퀴즈 카드 클릭/터치 시 메뉴 닫기
        function closeMenuOnCardInteraction(e) {
            const penMenu = document.getElementById('ptg-pen-menu');
            if (penMenu && penMenu.style.display !== 'none') {
                penMenu.style.display = 'none';
            }
        }

        const quizCard = document.querySelector('.ptg-quiz-card');
        if (quizCard) {
            quizCard.addEventListener('click', closeMenuOnCardInteraction);
            quizCard.addEventListener('touchstart', closeMenuOnCardInteraction, { passive: true });
        }
    }

    /**
     * 드로잉 툴바 버튼 이벤트 설정
     */
    function setupDrawingToolbarEvents() {
        const toolbar = document.getElementById('ptg-drawing-toolbar');
        if (!toolbar) return;

        // 이벤트 위임 사용
        toolbar.addEventListener('click', function (e) {
            // 닫기 버튼 처리
            const closeBtn = e.target.closest('.ptg-btn-close-drawing');
            if (closeBtn) {
                e.preventDefault();
                e.stopPropagation();
                toggleDrawing(false);
                return;
            }

            // 펜 색상/두께 버튼 처리
            const colorBtn = e.target.closest('.ptg-pen-color-btn');
            if (colorBtn) {
                e.preventDefault();
                e.stopPropagation();
                const color = colorBtn.getAttribute('data-color');
                if (color) {
                    setPenColor(color);
                    // 선택된 색상 버튼 표시
                    toolbar.querySelectorAll('.ptg-pen-color-btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    colorBtn.classList.add('active');
                }
                return;
            }

            // 슬라이더 처리
            const widthSlider = e.target.closest('#ptg-pen-width-slider');
            if (widthSlider) {
                return;
            }

            // 드로잉 도구 버튼 처리
            const target = e.target.closest('.ptg-btn-draw');
            if (!target) {
                // 펜 메뉴 외부 클릭 시 메뉴 닫기
                const penMenu = document.getElementById('ptg-pen-menu');
                if (penMenu && !e.target.closest('.ptg-pen-controls')) {
                    penMenu.style.display = 'none';
                }
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            const tool = target.getAttribute('data-tool');
            if (!tool) return;

            // 펜 버튼 클릭 시 메뉴 토글
            if (tool === 'pen') {
                const penMenu = document.getElementById('ptg-pen-menu');
                if (penMenu) {
                    const isVisible = penMenu.style.display !== 'none';
                    penMenu.style.display = isVisible ? 'none' : 'block';
                }
            } else {
                // 다른 도구 선택 시 펜 메뉴 닫기
                const penMenu = document.getElementById('ptg-pen-menu');
                if (penMenu) {
                    penMenu.style.display = 'none';
                }
            }

            // 모든 버튼에서 active 클래스 제거
            toolbar.querySelectorAll('.ptg-btn-draw').forEach(btn => {
                btn.classList.remove('active');
            });

            // 선택된 버튼에 active 클래스 추가
            target.classList.add('active');

            // 도구에 따라 기능 실행
            switch (tool) {
                case 'pen':
                    setDrawingTool('pen');
                    break;
                case 'eraser':
                    setDrawingTool('eraser');
                    break;
                case 'undo':
                    undoDrawing();
                    break;
                case 'redo':
                    redoDrawing();
                    break;
                case 'clear':
                    clearDrawing();
                    break;
            }
        });
    }

    /**
     * 드로잉 토글
     */
    async function toggleDrawing(force = null) {
        const QuizState = getQuizState();
        if (!QuizState) {
            console.warn('[PTG Quiz Drawing] QuizState를 찾을 수 없습니다.');
            return;
        }

        // 모바일에서는 드로잉 기능 비활성화
        if (QuizState.deviceType === 'mobile') {
            return;
        }

        const overlay = document.getElementById('ptg-drawing-overlay');
        const toolbar = document.getElementById('ptg-drawing-toolbar');
        const btnDrawing = document.querySelector('.ptg-btn-drawing');

        const shouldEnable = force !== null ? force : !QuizState.drawingEnabled;

        if (shouldEnable) {
            QuizState.drawingEnabled = true;
            if (btnDrawing) {
                btnDrawing.classList.add('active');
            }
            if (overlay) {
                overlay.style.display = 'block';
            }
            if (toolbar) {
                toolbar.style.display = 'flex';
            }

            // 드로잉 캔버스 초기화
            initDrawingCanvas();
        } else {
            // 드로잉 모드 종료 시 저장된 드로잉을 서버에 저장 (완료까지 대기)
            if (QuizState.drawingEnabled) {
                if (QuizState.drawingSaveTimeout) {
                    clearTimeout(QuizState.drawingSaveTimeout);
                    QuizState.drawingSaveTimeout = null;
                }
                await saveDrawingToServer();
            }

            QuizState.drawingEnabled = false;
            if (overlay) {
                overlay.style.display = 'none';
            }
            if (toolbar) {
                toolbar.style.display = 'none';
            }
            if (btnDrawing) {
                btnDrawing.classList.remove('active');
            }
        }
    }

    /**
     * 드로잉 캔버스 초기화
     */
    function initDrawingCanvas() {
        const QuizState = getQuizState();
        if (!QuizState) return;

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;

        const card = document.getElementById('ptg-quiz-card');
        const toolbar = document.querySelector('.ptg-quiz-toolbar');

        if (!card) return;

        // 툴바 위치 확인 (툴바 아래부터 캔버스 시작)
        let toolbarBottom = 0;
        if (toolbar) {
            const toolbarRect = toolbar.getBoundingClientRect();
            toolbarBottom = toolbarRect.bottom + 5;
        }

        // 문제 카드의 위치와 크기 계산
        const cardRect = card.getBoundingClientRect();

        // 캔버스는 툴바 아래부터 시작, 카드 전체를 포함
        let startY = Math.max(cardRect.top, toolbarBottom);
        let endY = cardRect.bottom;

        // 캔버스 크기와 위치 설정
        const width = cardRect.width;
        const height = Math.max(0, endY - startY);
        const left = cardRect.left;
        const top = startY;

        // 오버레이 위치 설정
        const overlay = document.getElementById('ptg-drawing-overlay');
        if (overlay) {
            canvas.style.position = 'fixed';
            canvas.style.left = left + 'px';
            canvas.style.top = top + 'px';
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            canvas.style.zIndex = '101';
        }

        // 캔버스 실제 크기 설정 (고해상도 디스플레이 지원)
        const dpr = window.devicePixelRatio || 1;
        canvas.width = width * dpr;
        canvas.height = height * dpr;

        // 컨텍스트 설정
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        ctx.scale(dpr, dpr);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.lineWidth = QuizState.penWidth;
        ctx.globalAlpha = QuizState.penAlpha;
        ctx.globalCompositeOperation = 'source-over';
        ctx.strokeStyle = QuizState.penColor;

        QuizState.canvasContext = ctx;

        // 드로잉 이벤트 리스너 등록
        setupDrawingEvents(canvas);

        // 기존 드로잉 히스토리 로드
        loadDrawingHistory();

        // 기본 도구를 펜으로 설정
        setDrawingTool('pen');

        // 펜 버튼에 active 클래스 추가
        const penBtn = document.querySelector('.ptg-btn-draw[data-tool="pen"]');
        if (penBtn) {
            penBtn.classList.add('active');
        }

        // 펜 색상/두께 메뉴 초기화
        initializePenMenu();

        // 윈도우 리사이즈 시 캔버스 재조정
        const resizeHandler = function () {
            if (QuizState.drawingEnabled) {
                setTimeout(() => {
                    initDrawingCanvas();
                }, 100);
            }
        };

        // 기존 리사이즈 핸들러 제거 후 재등록 (중복 방지)
        if (window._ptgDrawingResizeHandler) {
            window.removeEventListener('resize', window._ptgDrawingResizeHandler);
        }
        window._ptgDrawingResizeHandler = resizeHandler;
        window.addEventListener('resize', resizeHandler);
    }

    /**
     * 드로잉 이벤트 설정
     */
    function setupDrawingEvents(canvas) {
        if (!canvas) return;

        // 기존 이벤트 리스너 제거 (중복 방지)
        canvas.removeEventListener('mousedown', handleMouseDown);
        canvas.removeEventListener('mousemove', handleMouseMove);
        canvas.removeEventListener('mouseup', handleMouseUp);
        canvas.removeEventListener('mouseleave', handleMouseUp);
        canvas.removeEventListener('touchstart', handleTouchStart);
        canvas.removeEventListener('touchmove', handleTouchMove);
        canvas.removeEventListener('touchend', handleTouchEnd);

        // 마우스 이벤트
        canvas.addEventListener('mousedown', handleMouseDown);
        canvas.addEventListener('mousemove', handleMouseMove);
        canvas.addEventListener('mouseup', handleMouseUp);
        canvas.addEventListener('mouseleave', handleMouseUp);

        // 터치 이벤트
        canvas.addEventListener('touchstart', handleTouchStart, { passive: false });
        canvas.addEventListener('touchmove', handleTouchMove, { passive: false });
        canvas.addEventListener('touchend', handleTouchEnd);
    }

    /**
     * 캔버스 좌표 가져오기 헬퍼 함수
     */
    function getCanvasPoint(e) {
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return null;

        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;

        let clientX, clientY;
        if (e.touches && e.touches.length > 0) {
            clientX = e.touches[0].clientX;
            clientY = e.touches[0].clientY;
        } else {
            clientX = e.clientX;
            clientY = e.clientY;
        }

        return {
            x: (clientX - rect.left),
            y: (clientY - rect.top)
        };
    }

    /**
     * 마우스 다운 이벤트
     */
    function handleMouseDown(e) {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.drawingEnabled || !QuizState.canvasContext) return;

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;

        // 지우개 모드일 때는 스마트 지우개로 처리
        if (QuizState.drawingTool === 'eraser') {
            eraseStrokeAtPoint(e);
            return;
        }

        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;

        QuizState.isDrawing = true;
        QuizState.lastX = (e.clientX - rect.left) * dpr;
        QuizState.lastY = (e.clientY - rect.top) * dpr;

        // 점 배열 초기화 및 첫 점 추가
        QuizState.drawingPoints = [];
        const point = getCanvasPoint(e);
        if (point) {
            QuizState.drawingPoints.push(point);
        }

        // 히스토리 저장 (새로운 선 시작 전 상태 저장)
        saveHistoryState();
        QuizState.currentStrokeStartIndex = QuizState.drawingHistoryIndex;

        // 새 선 시작: strokes 배열에 추가
        QuizState.currentStrokeId = QuizState.nextStrokeId++;
        QuizState.strokes.push({
            id: QuizState.currentStrokeId,
            points: point ? [{...point}] : [],
            startHistoryIndex: QuizState.drawingHistoryIndex,
            endHistoryIndex: -1, // 아직 완료되지 않음
            color: QuizState.penColor,
            width: QuizState.penWidth,
            alpha: QuizState.penAlpha,
            timestamp: Date.now()
        });
    }

    /**
     * 마우스 이동 이벤트
     */
    function handleMouseMove(e) {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.isDrawing || !QuizState.canvasContext) return;

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;

        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const x = (e.clientX - rect.left) * dpr;
        const y = (e.clientY - rect.top) * dpr;

        const ctx = QuizState.canvasContext;

        // 이전 위치와 현재 위치 사이의 거리 계산
        const lastXNormalized = QuizState.lastX / dpr;
        const lastYNormalized = QuizState.lastY / dpr;
        const currentXNormalized = x / dpr;
        const currentYNormalized = y / dpr;
        const distance = Math.sqrt(
            Math.pow(currentXNormalized - lastXNormalized, 2) +
            Math.pow(currentYNormalized - lastYNormalized, 2)
        );

        // 최소 거리 체크
        const minDistance = Math.max(2.0, QuizState.penWidth * 0.3);
        if (distance < minDistance) {
            return;
        }

        // 점 추가
        QuizState.drawingPoints.push({
            x: currentXNormalized,
            y: currentYNormalized
        });

        // 현재 선의 points 배열에도 추가
        if (QuizState.currentStrokeId !== null) {
            const currentStroke = QuizState.strokes.find(s => s.id === QuizState.currentStrokeId);
            if (currentStroke) {
                currentStroke.points.push({
                    x: currentXNormalized,
                    y: currentYNormalized
                });
            }
        }

        ctx.beginPath();
        ctx.moveTo(lastXNormalized, lastYNormalized);
        ctx.lineTo(currentXNormalized, currentYNormalized);

        // 지우개 모드는 handleMouseDown에서 처리되므로 여기서는 펜만 처리
        // source-over 블렌드 모드와 불투명도 사용
        ctx.globalCompositeOperation = 'source-over';
        ctx.globalAlpha = QuizState.penAlpha;
        ctx.lineWidth = QuizState.penWidth;
        ctx.strokeStyle = QuizState.penColor;

        ctx.stroke();

        QuizState.lastX = x;
        QuizState.lastY = y;
    }

    /**
     * 마우스 업 이벤트
     */
    function handleMouseUp(e) {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.isDrawing) return;

        QuizState.isDrawing = false;

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas || !QuizState.canvasContext) return;

        // 검은색 펜인지 확인
        const isBlackPen = QuizState.penColor === 'rgb(0, 0, 0)';
        
        // 자동 정렬 타이머 설정 (검은색 펜이 아닐 때만)
        if (!isBlackPen && QuizState.autoAlignEnabled && QuizState.drawingPoints.length >= 3 && QuizState.drawingTool === 'pen') {
            // 기존 타이머 취소
            if (QuizState.autoAlignTimeout) {
                clearTimeout(QuizState.autoAlignTimeout);
            }

            // 500ms 후 자동 정렬 시도
            QuizState.autoAlignTimeout = setTimeout(() => {
                autoAlignDrawing();
            }, 500);
        } else {
            // 검은색 펜이거나 자동 정렬이 비활성화되었거나 점이 부족하면 일반 처리
            finishStroke();
        }
    }

    /**
     * 터치 시작 이벤트
     */
    function handleTouchStart(e) {
        e.preventDefault();
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.drawingEnabled || !QuizState.canvasContext) return;

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;

        // 지우개 모드일 때는 스마트 지우개로 처리
        if (QuizState.drawingTool === 'eraser') {
            eraseStrokeAtPoint(e);
            return;
        }

        const touch = e.touches[0];
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;

        QuizState.isDrawing = true;
        QuizState.lastX = (touch.clientX - rect.left) * dpr;
        QuizState.lastY = (touch.clientY - rect.top) * dpr;

        // 점 배열 초기화 및 첫 점 추가
        QuizState.drawingPoints = [];
        const point = getCanvasPoint(e);
        if (point) {
            QuizState.drawingPoints.push(point);
        }

        saveHistoryState();
        QuizState.currentStrokeStartIndex = QuizState.drawingHistoryIndex;

        // 새 선 시작: strokes 배열에 추가
        QuizState.currentStrokeId = QuizState.nextStrokeId++;
        QuizState.strokes.push({
            id: QuizState.currentStrokeId,
            points: point ? [{...point}] : [],
            startHistoryIndex: QuizState.drawingHistoryIndex,
            endHistoryIndex: -1, // 아직 완료되지 않음
            color: QuizState.penColor,
            width: QuizState.penWidth,
            alpha: QuizState.penAlpha,
            timestamp: Date.now()
        });
    }

    /**
     * 터치 이동 이벤트
     */
    function handleTouchMove(e) {
        e.preventDefault();
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.isDrawing || !QuizState.canvasContext) return;

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;

        const touch = e.touches[0];
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const x = (touch.clientX - rect.left) * dpr;
        const y = (touch.clientY - rect.top) * dpr;

        const ctx = QuizState.canvasContext;

        // 이전 위치와 현재 위치 사이의 거리 계산
        const lastXNormalized = QuizState.lastX / dpr;
        const lastYNormalized = QuizState.lastY / dpr;
        const currentXNormalized = x / dpr;
        const currentYNormalized = y / dpr;
        const distance = Math.sqrt(
            Math.pow(currentXNormalized - lastXNormalized, 2) +
            Math.pow(currentYNormalized - lastYNormalized, 2)
        );

        // 최소 거리 체크
        const minDistance = Math.max(2.0, QuizState.penWidth * 0.3);
        if (distance < minDistance) {
            return;
        }

        // 점 추가
        QuizState.drawingPoints.push({
            x: currentXNormalized,
            y: currentYNormalized
        });

        // 현재 선의 points 배열에도 추가
        if (QuizState.currentStrokeId !== null) {
            const currentStroke = QuizState.strokes.find(s => s.id === QuizState.currentStrokeId);
            if (currentStroke) {
                currentStroke.points.push({
                    x: currentXNormalized,
                    y: currentYNormalized
                });
            }
        }

        ctx.beginPath();
        ctx.moveTo(lastXNormalized, lastYNormalized);
        ctx.lineTo(currentXNormalized, currentYNormalized);

        // 지우개 모드는 handleTouchStart에서 처리되므로 여기서는 펜만 처리
        // source-over 블렌드 모드와 불투명도 사용
        ctx.globalCompositeOperation = 'source-over';
        ctx.globalAlpha = QuizState.penAlpha;
        ctx.lineWidth = QuizState.penWidth;
        ctx.strokeStyle = QuizState.penColor;

        ctx.stroke();

        QuizState.lastX = x;
        QuizState.lastY = y;
    }

    /**
     * 터치 종료 이벤트
     */
    function handleTouchEnd(e) {
        e.preventDefault();
        handleMouseUp(e);
    }

    /**
     * 히스토리 상태 저장
     */
    function saveHistoryState() {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.canvasContext) return;

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;

        const imageData = QuizState.canvasContext.getImageData(0, 0, canvas.width, canvas.height);
        QuizState.drawingHistory.push(imageData);

        // 히스토리 최대 50개로 제한
        if (QuizState.drawingHistory.length > 50) {
            QuizState.drawingHistory.shift();
        }

        QuizState.drawingHistoryIndex = QuizState.drawingHistory.length - 1;
    }

    /**
     * 드로잉 히스토리 로드
     */
    function loadDrawingHistory() {
        const QuizState = getQuizState();
        if (!QuizState) return;

        QuizState.drawingHistory = [];
        QuizState.drawingHistoryIndex = -1;

        // 서버에서 저장된 드로잉 로드
        loadDrawingFromServer();
    }

    /**
     * 서버에서 저장된 드로잉 로드
     */
    async function loadDrawingFromServer() {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.questionId) {
            return;
        }

        // 캔버스 컨텍스트가 준비될 때까지 대기
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas || !QuizState.canvasContext) {
            setTimeout(loadDrawingFromServer, 200);
            return;
        }

        try {
            const isAnswered = QuizState.isAnswered ? 1 : 0;
            const response = await window.PTGPlatform.get(`ptg-quiz/v1/questions/${QuizState.questionId}/drawings`, {
                is_answered: isAnswered,
                device_type: QuizState.deviceType
            });

            if (response && response.success && response.data && response.data.length > 0) {
                const drawing = response.data[0];

                if (drawing.format === 'json' && drawing.data) {
                    try {
                        const imageDataObj = JSON.parse(drawing.data);

                        if (imageDataObj.empty || !imageDataObj.data || imageDataObj.data === '') {
                            return;
                        }

                        let uint8Array;
                        if (imageDataObj.encoded && typeof imageDataObj.data === 'string') {
                            const binaryString = atob(imageDataObj.data);
                            uint8Array = new Uint8Array(binaryString.length);
                            for (let i = 0; i < binaryString.length; i++) {
                                uint8Array[i] = binaryString.charCodeAt(i);
                            }
                        } else if (Array.isArray(imageDataObj.data)) {
                            uint8Array = new Uint8Array(imageDataObj.data);
                        } else {
                            throw new Error('지원하지 않는 데이터 형식');
                        }

                        const imageData = new ImageData(
                            new Uint8ClampedArray(uint8Array),
                            imageDataObj.width,
                            imageDataObj.height
                        );

                        const canvasWidth = canvas.width;
                        const canvasHeight = canvas.height;

                        if (imageDataObj.width === canvasWidth && imageDataObj.height === canvasHeight) {
                            QuizState.canvasContext.putImageData(imageData, 0, 0);
                            QuizState.drawingHistory = [imageData];
                            QuizState.drawingHistoryIndex = 0;
                        } else {
                            const tempCanvas = document.createElement('canvas');
                            tempCanvas.width = imageDataObj.width;
                            tempCanvas.height = imageDataObj.height;
                            const tempCtx = tempCanvas.getContext('2d');
                            tempCtx.putImageData(imageData, 0, 0);
                            QuizState.canvasContext.clearRect(0, 0, canvasWidth, canvasHeight);
                            QuizState.canvasContext.drawImage(tempCanvas, 0, 0, canvasWidth, canvasHeight);
                            const currentImageData = QuizState.canvasContext.getImageData(0, 0, canvasWidth, canvasHeight);
                            QuizState.drawingHistory = [currentImageData];
                            QuizState.drawingHistoryIndex = 0;
                        }
                    } catch (e) {
                        // JSON 파싱 실패는 조용히 무시
                    }
                }
            }
        } catch (e) {
            // 드로잉이 없거나 로드 실패 시 조용히 무시
        }
    }

    /**
     * 빈 드로잉을 서버에 저장 (드로잉 삭제)
     */
    async function saveEmptyDrawingToServer() {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.questionId) return;

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;

        try {
            const emptyDrawingData = {
                data: '',
                width: canvas.width,
                height: canvas.height,
                encoded: true,
                empty: true
            };

            const device = {
                ua: navigator.userAgent ? navigator.userAgent.substring(0, 50) : '',
                plt: navigator.platform || '',
                w: window.screen.width || 0,
                h: window.screen.height || 0
            };

            let deviceStr = JSON.stringify(device);
            if (deviceStr.length > 100) {
                deviceStr = JSON.stringify({
                    plt: navigator.platform || '',
                    w: window.screen.width || 0,
                    h: window.screen.height || 0
                });
            }

            await window.PTGPlatform.post(`ptg-quiz/v1/questions/${QuizState.questionId}/drawings`, {
                format: 'json',
                data: JSON.stringify(emptyDrawingData),
                width: canvas.width,
                height: canvas.height,
                device: deviceStr,
                is_answered: QuizState.isAnswered ? 1 : 0,
                device_type: QuizState.deviceType
            });
        } catch (e) {
            // 빈 드로잉 저장 실패 시 조용히 무시
        }
    }

    /**
     * 드로잉을 서버에 저장
     */
    async function saveDrawingToServer() {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.questionId || !QuizState.canvasContext) return;

        if (QuizState.savingDrawing) {
            return;
        }

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;

        QuizState.savingDrawing = true;

        try {
            const imageData = QuizState.canvasContext.getImageData(0, 0, canvas.width, canvas.height);

            const isEmpty = imageData.data.every((value, index) => {
                return index % 4 !== 3 || value === 0;
            });

            if (isEmpty) {
                await saveEmptyDrawingToServer();
                return;
            }

            const uint8Array = new Uint8Array(imageData.data);
            let binaryString = '';
            const chunkSize = 8192;
            for (let i = 0; i < uint8Array.length; i += chunkSize) {
                const chunk = uint8Array.subarray(i, i + chunkSize);
                binaryString += String.fromCharCode.apply(null, chunk);
            }
            const base64Data = btoa(binaryString);

            const drawingData = {
                data: base64Data,
                width: imageData.width,
                height: imageData.height,
                encoded: true
            };

            const device = {
                ua: navigator.userAgent ? navigator.userAgent.substring(0, 50) : '',
                plt: navigator.platform || '',
                w: window.screen.width || 0,
                h: window.screen.height || 0
            };

            let deviceStr = JSON.stringify(device);
            if (deviceStr.length > 100) {
                deviceStr = JSON.stringify({
                    plt: navigator.platform || '',
                    w: window.screen.width || 0,
                    h: window.screen.height || 0
                });
            }

            await window.PTGPlatform.post(`ptg-quiz/v1/questions/${QuizState.questionId}/drawings`, {
                format: 'json',
                data: JSON.stringify(drawingData),
                width: canvas.width,
                height: canvas.height,
                device: deviceStr,
                is_answered: QuizState.isAnswered ? 1 : 0,
                device_type: QuizState.deviceType
            });
        } catch (e) {
            // 저장 실패 시 조용히 무시
        } finally {
            QuizState.savingDrawing = false;
        }
    }

    /**
     * 드로잉 자동 저장 (디바운스 처리)
     */
    function debouncedSaveDrawing() {
        const QuizState = getQuizState();
        if (!QuizState) return;

        if (QuizState.drawingSaveTimeout) {
            clearTimeout(QuizState.drawingSaveTimeout);
        }
        QuizState.drawingSaveTimeout = setTimeout(saveDrawingToServer, 800);
    }

    /**
     * 드로잉 도구 설정
     */
    function setDrawingTool(tool) {
        const QuizState = getQuizState();
        if (!QuizState) return;

        QuizState.drawingTool = tool;

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;

        if (tool === 'eraser') {
            canvas.style.cursor = 'pointer'; // 스마트 지우개는 클릭 방식이므로 pointer
        } else {
            const penCursor = 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'24\' height=\'24\' viewBox=\'0 0 24 24\'%3E%3Cpath fill=\'%23333\' d=\'M20.71 4.63l-1.34-1.34c-.39-.39-1.02-.39-1.41 0L9 12.25 11.75 15l8.96-8.96c.39-.39.39-1.02 0-1.41zM7 14a3 3 0 0 0-3 3c0 1.31-1.16 2-2 2 .92 1.22 2.49 2 4 2a4 4 0 0 0 4-4c0-1.31-.84-2.42-2-2.83z\'/%3E%3C/svg%3E") 0 24, crosshair';
            canvas.style.cursor = penCursor;
        }

        if (QuizState.canvasContext) {
            if (tool === 'pen') {
                QuizState.canvasContext.lineWidth = QuizState.penWidth;
                QuizState.canvasContext.globalAlpha = QuizState.penAlpha;
                QuizState.canvasContext.globalCompositeOperation = 'source-over';
                QuizState.canvasContext.strokeStyle = QuizState.penColor;
            }
        }
    }

    /**
     * 펜 색상 설정
     */
    function setPenColor(color) {
        const QuizState = getQuizState();
        if (!QuizState) return;

        const previousColor = QuizState.penColor;
        QuizState.penColor = color;

        const highlightColors = [
            'rgb(255, 0, 0)',
            'rgb(255, 255, 0)',
            'rgb(0, 0, 255)',
            'rgb(0, 255, 0)'
        ];

        const isHighlightColor = highlightColors.includes(color);
        const isBlack = color === 'rgb(0, 0, 0)';
        const wasBlack = previousColor === 'rgb(0, 0, 0)';
        const wasHighlightColor = highlightColors.includes(previousColor);

        if (isBlack) {
            // 검은색 펜 선택 시: 자동 정렬 비활성화, 불투명도 70%로 설정
            QuizState.autoAlignEnabled = false;
            
            // 검은색 선택 전의 설정을 저장 (복원용)
            if (!wasBlack) {
                try {
                    localStorage.setItem('ptg-quiz-pen-width-backup', String(QuizState.penWidth));
                    localStorage.setItem('ptg-quiz-pen-alpha-backup', String(QuizState.penAlpha));
                } catch (e) {
                    // localStorage 사용 불가 시 무시
                }
            }
            
            // 불투명도 70% (0.7)로 설정
            setPenAlpha(0.7);

            const alphaSlider = document.getElementById('ptg-pen-alpha-slider');
            const alphaValue = document.getElementById('ptg-pen-alpha-value');

            if (alphaSlider && alphaValue) {
                alphaSlider.value = 70;
                alphaValue.textContent = '70';
            }

            // 검은색 선택 시에는 색상만 저장 (불투명도는 저장하지 않음)
            try {
                localStorage.setItem('ptg-quiz-pen-color', color);
                localStorage.setItem('ptg-quiz-pen-width', String(QuizState.penWidth));
                // penAlpha는 저장하지 않음 (검은색 전용 설정이므로)
            } catch (e) {
                // localStorage 사용 불가 시 무시
            }
        } else {
            // 검은색이 아닌 색상 선택 시: 자동 정렬 활성화, 저장된 설정으로 복원
            QuizState.autoAlignEnabled = true;
            
            // localStorage에서 저장된 설정 불러오기 (백업 값 우선)
            let savedWidth = 10; // 기본값
            let savedAlpha = 0.2; // 기본값
            
            try {
                // 검은색 선택 전의 백업 값이 있으면 우선 사용
                const backupWidth = localStorage.getItem('ptg-quiz-pen-width-backup');
                const backupAlpha = localStorage.getItem('ptg-quiz-pen-alpha-backup');
                
                if (backupWidth && !isNaN(parseInt(backupWidth))) {
                    savedWidth = parseInt(backupWidth);
                } else {
                    const storedWidth = localStorage.getItem('ptg-quiz-pen-width');
                    if (storedWidth && !isNaN(parseInt(storedWidth))) {
                        savedWidth = parseInt(storedWidth);
                    }
                }
                
                if (backupAlpha && !isNaN(parseFloat(backupAlpha))) {
                    savedAlpha = parseFloat(backupAlpha);
                } else {
                    const storedAlpha = localStorage.getItem('ptg-quiz-pen-alpha');
                    if (storedAlpha && !isNaN(parseFloat(storedAlpha))) {
                        savedAlpha = parseFloat(storedAlpha);
                    }
                }
            } catch (e) {
                // localStorage 사용 불가 시 기본값 유지
            }
            
            // 저장된 설정으로 복원
            setPenWidth(savedWidth);
            setPenAlpha(savedAlpha);

            const widthSlider = document.getElementById('ptg-pen-width-slider');
            const widthValue = document.getElementById('ptg-pen-width-value');
            const alphaSlider = document.getElementById('ptg-pen-alpha-slider');
            const alphaValue = document.getElementById('ptg-pen-alpha-value');

            if (widthSlider && widthValue) {
                widthSlider.value = savedWidth;
                widthValue.textContent = String(savedWidth);
            }
            if (alphaSlider && alphaValue) {
                const alphaPercent = Math.round(savedAlpha * 100);
                alphaSlider.value = alphaPercent;
                alphaValue.textContent = String(alphaPercent);
            }

            savePenSettings();
        }

        if (QuizState.canvasContext && QuizState.drawingTool === 'pen') {
            QuizState.canvasContext.globalCompositeOperation = 'source-over';
            QuizState.canvasContext.globalAlpha = QuizState.penAlpha;
            QuizState.canvasContext.strokeStyle = color;
        }
    }

    /**
     * 펜 불투명도 설정
     */
    function setPenAlpha(alpha) {
        const QuizState = getQuizState();
        if (!QuizState) return;

        QuizState.penAlpha = alpha;

        if (QuizState.canvasContext && QuizState.drawingTool === 'pen') {
            QuizState.canvasContext.globalCompositeOperation = 'source-over';
            QuizState.canvasContext.globalAlpha = alpha;
            QuizState.canvasContext.strokeStyle = QuizState.penColor;
        }
    }

    /**
     * 펜 두께 설정
     */
    function setPenWidth(width) {
        const QuizState = getQuizState();
        if (!QuizState) return;

        QuizState.penWidth = width;

        if (QuizState.canvasContext && QuizState.drawingTool === 'pen') {
            QuizState.canvasContext.lineWidth = width;
        }
    }

    /**
     * 실행 취소 (Undo)
     */
    function undoDrawing() {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.canvasContext || QuizState.drawingHistoryIndex < 0) return;

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;

        QuizState.drawingHistoryIndex--;

        if (QuizState.drawingHistoryIndex >= 0) {
            const imageData = QuizState.drawingHistory[QuizState.drawingHistoryIndex];
            QuizState.canvasContext.putImageData(imageData, 0, 0);
        } else {
            QuizState.canvasContext.clearRect(0, 0, canvas.width, canvas.height);
            QuizState.drawingHistoryIndex = -1;
        }
    }

    /**
     * 다시 실행 (Redo)
     */
    function redoDrawing() {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.canvasContext) return;

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;

        if (QuizState.drawingHistoryIndex < QuizState.drawingHistory.length - 1) {
            QuizState.drawingHistoryIndex++;
            const imageData = QuizState.drawingHistory[QuizState.drawingHistoryIndex];
            QuizState.canvasContext.putImageData(imageData, 0, 0);
        }
    }

    /**
     * 전체 지우기
     */
    async function clearDrawing() {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.canvasContext) return;

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;

        // 캔버스 지우기
        QuizState.canvasContext.clearRect(0, 0, canvas.width, canvas.height);

        // 히스토리 완전히 초기화 (지운 내용이 복원되지 않도록)
        QuizState.drawingHistory = [];
        QuizState.drawingHistoryIndex = -1;

        // strokes 배열 완전히 초기화
        QuizState.strokes = [];
        QuizState.nextStrokeId = 1;
        QuizState.currentStrokeId = null;
        QuizState.currentStrokeStartIndex = -1;

        // 빈 캔버스 상태만 히스토리에 저장
        const emptyImageData = QuizState.canvasContext.getImageData(0, 0, canvas.width, canvas.height);
        QuizState.drawingHistory.push(emptyImageData);
        QuizState.drawingHistoryIndex = 0;

        // 서버에 빈 드로잉 저장
        await saveEmptyDrawingToServer();
    }

    /**
     * 자동 정렬 함수
     */
    function autoAlignDrawing() {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.canvasContext || !QuizState.drawingPoints || QuizState.drawingPoints.length < 3) {
            finishStroke();
            return;
        }

        // 검은색 펜일 때는 자동 정렬 실행하지 않음
        const isBlackPen = QuizState.penColor === 'rgb(0, 0, 0)';
        if (isBlackPen) {
            finishStroke();
            return;
        }

        const points = QuizState.drawingPoints;
        const canvas = document.getElementById('ptg-drawing-canvas');
        const ctx = QuizState.canvasContext;

        // 1. 직선 판단
        const lineFit = fitLine(points);
        if (lineFit.isLine && lineFit.error < 5) {
            drawAlignedLine(lineFit);
            return;
        }

        // 2. 원 판단
        const circleFit = fitCircle(points);
        if (circleFit.isCircle && circleFit.error < 10) {
            drawAlignedCircle(circleFit);
            return;
        }

        // 3. 둘 다 아니면 원본 유지
        finishStroke();
    }

    /**
     * 직선 피팅 함수
     */
    function fitLine(points) {
        const n = points.length;
        if (n < 2) {
            return { isLine: false, error: Infinity };
        }

        let sumX = 0, sumY = 0, sumXY = 0, sumXX = 0;

        points.forEach(p => {
            sumX += p.x;
            sumY += p.y;
            sumXY += p.x * p.y;
            sumXX += p.x * p.x;
        });

        const slope = (n * sumXY - sumX * sumY) / (n * sumXX - sumX * sumX);
        const intercept = (sumY - slope * sumX) / n;

        let totalError = 0;
        points.forEach(p => {
            const expectedY = slope * p.x + intercept;
            totalError += Math.abs(p.y - expectedY);
        });
        const avgError = totalError / n;

        const startX = Math.min(...points.map(p => p.x));
        const endX = Math.max(...points.map(p => p.x));
        const startY = slope * startX + intercept;
        const endY = slope * endX + intercept;

        return {
            isLine: avgError < 5,
            error: avgError,
            start: { x: startX, y: startY },
            end: { x: endX, y: endY }
        };
    }

    /**
     * 원 피팅 함수
     */
    function fitCircle(points) {
        const n = points.length;
        if (n < 5) {
            return { isCircle: false, error: Infinity };
        }

        let sumX = 0, sumY = 0;
        points.forEach(p => {
            sumX += p.x;
            sumY += p.y;
        });
        const centerX = sumX / n;
        const centerY = sumY / n;

        let sumRadius = 0;
        points.forEach(p => {
            const dx = p.x - centerX;
            const dy = p.y - centerY;
            sumRadius += Math.sqrt(dx * dx + dy * dy);
        });
        const avgRadius = sumRadius / n;

        let totalError = 0;
        points.forEach(p => {
            const dx = p.x - centerX;
            const dy = p.y - centerY;
            const dist = Math.sqrt(dx * dx + dy * dy);
            totalError += Math.abs(dist - avgRadius);
        });
        const avgError = totalError / n;

        return {
            isCircle: avgError < 10 && n > 5,
            error: avgError,
            center: { x: centerX, y: centerY },
            radius: avgRadius
        };
    }

    /**
     * 정렬된 직선 그리기
     */
    function drawAlignedLine(lineFit) {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.canvasContext) return;

        const ctx = QuizState.canvasContext;
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;

        // 현재 그린 선 지우기 (히스토리에서 복원)
        if (QuizState.currentStrokeStartIndex >= 0 && 
            QuizState.drawingHistory[QuizState.currentStrokeStartIndex]) {
            const lastState = QuizState.drawingHistory[QuizState.currentStrokeStartIndex];
            ctx.putImageData(lastState, 0, 0);
        }

        // 정렬된 직선 그리기 (현재 설정과 동일하게)
        ctx.beginPath();
        ctx.moveTo(lineFit.start.x, lineFit.start.y);
        ctx.lineTo(lineFit.end.x, lineFit.end.y);
        ctx.globalCompositeOperation = 'source-over';
        ctx.globalAlpha = QuizState.penAlpha; // 동일한 투명도
        ctx.lineWidth = QuizState.penWidth; // 동일한 두께
        ctx.strokeStyle = QuizState.penColor; // 동일한 색상
        ctx.stroke();

        // 자동 정렬된 직선의 메타데이터 저장
        if (QuizState.currentStrokeId !== null) {
            const currentStroke = QuizState.strokes.find(s => s.id === QuizState.currentStrokeId);
            if (currentStroke) {
                // 정렬된 직선의 시작점과 끝점만 저장
                currentStroke.points = [
                    { x: lineFit.start.x, y: lineFit.start.y },
                    { x: lineFit.end.x, y: lineFit.end.y }
                ];
                // 정렬된 형태 정보 저장
                currentStroke.alignedType = 'line';
                currentStroke.alignedData = {
                    start: { x: lineFit.start.x, y: lineFit.start.y },
                    end: { x: lineFit.end.x, y: lineFit.end.y }
                };
            }
        }

        // 히스토리 업데이트
        finishStroke();
    }

    /**
     * 정렬된 원 그리기
     */
    function drawAlignedCircle(circleFit) {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.canvasContext) return;

        const ctx = QuizState.canvasContext;
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;

        // 현재 그린 선 지우기 (히스토리에서 복원)
        if (QuizState.currentStrokeStartIndex >= 0 && 
            QuizState.drawingHistory[QuizState.currentStrokeStartIndex]) {
            const lastState = QuizState.drawingHistory[QuizState.currentStrokeStartIndex];
            ctx.putImageData(lastState, 0, 0);
        }

        // 정렬된 원 그리기 (현재 설정과 동일하게)
        ctx.beginPath();
        ctx.arc(circleFit.center.x, circleFit.center.y, circleFit.radius, 0, 2 * Math.PI);
        ctx.globalCompositeOperation = 'source-over';
        ctx.globalAlpha = QuizState.penAlpha; // 동일한 투명도
        ctx.lineWidth = QuizState.penWidth; // 동일한 두께
        ctx.strokeStyle = QuizState.penColor; // 동일한 색상
        ctx.stroke();

        // 자동 정렬된 원의 메타데이터 저장
        if (QuizState.currentStrokeId !== null) {
            const currentStroke = QuizState.strokes.find(s => s.id === QuizState.currentStrokeId);
            if (currentStroke) {
                // 원의 둘레를 따라 점들을 생성 (약 32개 점으로 원 근사)
                const circlePoints = [];
                const numPoints = 32;
                for (let i = 0; i <= numPoints; i++) {
                    const angle = (i / numPoints) * 2 * Math.PI;
                    circlePoints.push({
                        x: circleFit.center.x + circleFit.radius * Math.cos(angle),
                        y: circleFit.center.y + circleFit.radius * Math.sin(angle)
                    });
                }
                currentStroke.points = circlePoints;
                // 정렬된 형태 정보 저장
                currentStroke.alignedType = 'circle';
                currentStroke.alignedData = {
                    center: { x: circleFit.center.x, y: circleFit.center.y },
                    radius: circleFit.radius
                };
            }
        }

        // 히스토리 업데이트
        finishStroke();
    }

    /**
     * 선 완료 처리 함수
     */
    function finishStroke() {
        const QuizState = getQuizState();
        if (!QuizState) return;

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas || !QuizState.canvasContext) return;

        // Redo 가능한 히스토리 제거
        if (QuizState.drawingHistory.length > QuizState.drawingHistoryIndex + 1) {
            QuizState.drawingHistory = QuizState.drawingHistory.slice(0, QuizState.drawingHistoryIndex + 1);
        }

        // 현재 상태를 히스토리에 추가
        const imageData = QuizState.canvasContext.getImageData(0, 0, canvas.width, canvas.height);
        QuizState.drawingHistory.push(imageData);

        // 히스토리 최대 50개로 제한
        if (QuizState.drawingHistory.length > 50) {
            QuizState.drawingHistory.shift();
            QuizState.drawingHistoryIndex--;
        }

        QuizState.drawingHistoryIndex = QuizState.drawingHistory.length - 1;

        // 선 완료 시 strokes 배열 업데이트
        if (QuizState.currentStrokeId !== null) {
            const currentStroke = QuizState.strokes.find(s => s.id === QuizState.currentStrokeId);
            if (currentStroke) {
                currentStroke.points = [...QuizState.drawingPoints]; // 최종 점 배열 복사
                currentStroke.endHistoryIndex = QuizState.drawingHistoryIndex;
            }
            QuizState.currentStrokeId = null;
        }

        // 드로잉 자동 저장 (디바운스)
        debouncedSaveDrawing();

        // 점 배열 초기화
        QuizState.drawingPoints = [];
        QuizState.currentStrokeStartIndex = -1;
    }

    /**
     * 점에서 선까지의 최소 거리 계산
     * 선의 두께를 정확히 고려하여 실제로 선 위에 있는지 확인
     */
    function distanceToStroke(point, stroke) {
        if (!stroke.points || stroke.points.length === 0) {
            return Infinity;
        }

        let minDistance = Infinity;
        const strokeWidth = stroke.width || 10;
        const tolerance = strokeWidth / 2; // 선의 반지름만큼만 허용

        // 각 선분에 대해 점까지의 거리 계산
        for (let i = 0; i < stroke.points.length - 1; i++) {
            const p1 = stroke.points[i];
            const p2 = stroke.points[i + 1];

            // 선분의 길이
            const dx = p2.x - p1.x;
            const dy = p2.y - p1.y;
            const lengthSq = dx * dx + dy * dy;

            if (lengthSq === 0) {
                // 점과 점 사이의 거리
                const dist = Math.sqrt(
                    Math.pow(point.x - p1.x, 2) + Math.pow(point.y - p1.y, 2)
                );
                minDistance = Math.min(minDistance, dist);
            } else {
                // 선분 위의 가장 가까운 점 찾기
                const t = Math.max(0, Math.min(1, ((point.x - p1.x) * dx + (point.y - p1.y) * dy) / lengthSq));
                const closestX = p1.x + t * dx;
                const closestY = p1.y + t * dy;
                const dist = Math.sqrt(
                    Math.pow(point.x - closestX, 2) + Math.pow(point.y - closestY, 2)
                );
                minDistance = Math.min(minDistance, dist);
            }
        }

        // 선의 두께를 고려한 거리 반환 (음수면 선 위에 있음)
        return minDistance - tolerance;
    }

    /**
     * 클릭/탭한 위치에서 가장 가까운 선 찾기
     * 선의 두께 범위 내에 정확히 있는 선만 선택
     */
    function findNearestStroke(point, maxDistance = null) {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.strokes || QuizState.strokes.length === 0) {
            return null;
        }

        let nearestStroke = null;
        let minDistance = Infinity;

        // 완료된 선만 검사 (endHistoryIndex가 있는 선)
        for (const stroke of QuizState.strokes) {
            if (stroke.endHistoryIndex < 0) {
                continue; // 아직 완료되지 않은 선은 제외
            }

            const distance = distanceToStroke(point, stroke);
            // distance가 0 이하이면 선 위에 있음 (선의 두께 범위 내)
            // 가장 가까운 선 중에서 선 위에 있는 것만 선택
            if (distance <= 0 && distance < minDistance) {
                minDistance = distance;
                nearestStroke = stroke;
            }
        }

        // 선 위에 있는 선이 없으면 null 반환 (근처 선은 지우지 않음)
        return nearestStroke;
    }

    /**
     * stroke의 points 배열을 사용하여 선 다시 그리기
     * 정렬된 선(직선, 원)은 정렬된 형태로 다시 그림
     */
    function redrawStroke(stroke) {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.canvasContext) {
            return;
        }

        const ctx = QuizState.canvasContext;

        // 선 그리기 설정
        ctx.globalCompositeOperation = 'source-over';
        ctx.globalAlpha = stroke.alpha || 0.5;
        ctx.lineWidth = stroke.width || 10;
        ctx.strokeStyle = stroke.color || 'rgb(255, 0, 0)';
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        // 정렬된 선인지 확인하고 정렬된 형태로 다시 그림
        if (stroke.alignedType === 'line' && stroke.alignedData) {
            // 정렬된 직선 그리기
            ctx.beginPath();
            ctx.moveTo(stroke.alignedData.start.x, stroke.alignedData.start.y);
            ctx.lineTo(stroke.alignedData.end.x, stroke.alignedData.end.y);
            ctx.stroke();
        } else if (stroke.alignedType === 'circle' && stroke.alignedData) {
            // 정렬된 원 그리기
            ctx.beginPath();
            ctx.arc(
                stroke.alignedData.center.x,
                stroke.alignedData.center.y,
                stroke.alignedData.radius,
                0,
                2 * Math.PI
            );
            ctx.stroke();
        } else if (stroke.points && stroke.points.length >= 2) {
            // 일반 선 그리기 (points 배열 사용)
            ctx.beginPath();
            ctx.moveTo(stroke.points[0].x, stroke.points[0].y);
            for (let i = 1; i < stroke.points.length; i++) {
                ctx.lineTo(stroke.points[i].x, stroke.points[i].y);
            }
            ctx.stroke();
        }
    }

    /**
     * 특정 선 삭제 (독립적으로 삭제, 다른 선은 유지)
     */
    function deleteStroke(strokeId) {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.canvasContext) {
            return false;
        }

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) {
            return false;
        }

        // strokes 배열에서 찾기
        const strokeIndex = QuizState.strokes.findIndex(s => s.id === strokeId);
        if (strokeIndex === -1) {
            return false;
        }

        const strokeToDelete = QuizState.strokes[strokeIndex];

        // 완료되지 않은 선은 삭제 불가
        if (strokeToDelete.endHistoryIndex < 0) {
            return false;
        }

        // 현재 그리는 선이 삭제된 경우 초기화
        if (QuizState.currentStrokeId === strokeId) {
            QuizState.currentStrokeId = null;
        }

        // 삭제할 선을 strokes 배열에서 제거
        QuizState.strokes = QuizState.strokes.filter(s => s.id !== strokeId);

        // 캔버스 초기화
        QuizState.canvasContext.clearRect(0, 0, canvas.width, canvas.height);

        // 히스토리 완전히 초기화 (삭제된 선의 히스토리가 복원되지 않도록)
        QuizState.drawingHistory = [];
        QuizState.drawingHistoryIndex = -1;
        QuizState.currentStrokeStartIndex = -1;

        // 초기 상태 저장 (빈 캔버스)
        const emptyImageData = QuizState.canvasContext.getImageData(0, 0, canvas.width, canvas.height);
        QuizState.drawingHistory.push(emptyImageData);
        QuizState.drawingHistoryIndex = 0;

        // 나머지 strokes를 순서대로 다시 그리기 (히스토리 재구성)
        for (let i = 0; i < QuizState.strokes.length; i++) {
            const stroke = QuizState.strokes[i];
            if (stroke.points && stroke.points.length >= 2) {
                // 선 시작 전 상태 저장
                const beforeImageData = QuizState.canvasContext.getImageData(0, 0, canvas.width, canvas.height);
                QuizState.drawingHistory.push(beforeImageData);
                QuizState.drawingHistoryIndex = QuizState.drawingHistory.length - 1;
                stroke.startHistoryIndex = QuizState.drawingHistoryIndex;

                // 선 그리기
                redrawStroke(stroke);

                // 선 완료 후 상태 저장
                const afterImageData = QuizState.canvasContext.getImageData(0, 0, canvas.width, canvas.height);
                QuizState.drawingHistory.push(afterImageData);
                QuizState.drawingHistoryIndex = QuizState.drawingHistory.length - 1;
                stroke.endHistoryIndex = QuizState.drawingHistoryIndex;
            }
        }

        // 히스토리 최대 50개로 제한
        if (QuizState.drawingHistory.length > 50) {
            const removeCount = QuizState.drawingHistory.length - 50;
            QuizState.drawingHistory = QuizState.drawingHistory.slice(removeCount);
            QuizState.drawingHistoryIndex = QuizState.drawingHistory.length - 1;
            
            // strokes의 히스토리 인덱스 조정
            QuizState.strokes.forEach(stroke => {
                if (stroke.startHistoryIndex >= 0) {
                    stroke.startHistoryIndex = Math.max(0, stroke.startHistoryIndex - removeCount);
                }
                if (stroke.endHistoryIndex >= 0) {
                    stroke.endHistoryIndex = Math.max(0, stroke.endHistoryIndex - removeCount);
                }
            });
        }

        // 드로잉 자동 저장
        debouncedSaveDrawing();

        return true;
    }

    /**
     * 클릭/탭한 위치에서 가장 가까운 선 지우기 (스마트 지우개)
     */
    function eraseStrokeAtPoint(e) {
        const QuizState = getQuizState();
        if (!QuizState || !QuizState.drawingEnabled || !QuizState.canvasContext) {
            return;
        }

        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) {
            return;
        }

        // 클릭/탭 위치 가져오기
        const point = getCanvasPoint(e);
        if (!point) {
            return;
        }

        // 가장 가까운 선 찾기 (선의 두께 범위 내에 정확히 있는 선만)
        const nearestStroke = findNearestStroke(point);
        if (!nearestStroke) {
            return; // 선 위에 정확히 클릭하지 않았으면 아무것도 하지 않음
        }

        // 선 삭제
        deleteStroke(nearestStroke.id);
    }

    // 전역으로 함수 노출 (quiz.js에서 사용)
    if (typeof window !== 'undefined') {
        window.PTGQuizDrawing = {
            loadPenSettings: loadPenSettings,
            savePenSettings: savePenSettings,
            initializePenMenu: initializePenMenu,
            setupDrawingToolbarEvents: setupDrawingToolbarEvents,
            toggleDrawing: toggleDrawing,
            initDrawingCanvas: initDrawingCanvas,
            setupDrawingEvents: setupDrawingEvents,
            loadDrawingHistory: loadDrawingHistory,
            loadDrawingFromServer: loadDrawingFromServer,
            saveEmptyDrawingToServer: saveEmptyDrawingToServer,
            saveDrawingToServer: saveDrawingToServer,
            debouncedSaveDrawing: debouncedSaveDrawing,
            setDrawingTool: setDrawingTool,
            setPenColor: setPenColor,
            setPenAlpha: setPenAlpha,
            setPenWidth: setPenWidth,
            undoDrawing: undoDrawing,
            redoDrawing: redoDrawing,
            clearDrawing: clearDrawing
        };
    }

})();

