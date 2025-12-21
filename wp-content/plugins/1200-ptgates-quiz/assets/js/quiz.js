/**
 * PTGates Quiz - 메인 JavaScript
 *
 * 문제 풀이 UI, 타이머, 드로잉 기능
 */

// 서버 REST(subjects/sessions/subsubjects)가 준비될 때까지
// 클라이언트 매핑만으로 즉시 채우기 위한 토글 -> 이제 서버 데이터(subjectMap)를 우선 사용합니다.
var USE_SERVER_SUBJECTS = true;

let SESSION_STRUCTURE = {};

// 서버에서 주입된 subjectMap이 있으면 사용
if (
  window.ptgQuiz &&
  window.ptgQuiz.subjectMap &&
  Object.keys(window.ptgQuiz.subjectMap).length > 0
) {
  SESSION_STRUCTURE = window.ptgQuiz.subjectMap;
} else {
  // Fallback (기본값) - DB 로드 실패 시 사용
  SESSION_STRUCTURE = {
    1: {
      total: 105,
      subjects: {
        "물리치료 기초": {
          total: 60,
          subs: {
            해부생리학: 22,
            운동학: 12,
            "물리적 인자치료": 16,
            공중보건학: 10,
          },
        },
        "물리치료 진단평가": {
          total: 45,
          subs: {
            "근골격계 물리치료 진단평가": 10,
            "신경계 물리치료 진단평가": 16,
            "진단평가 원리": 6,
            "심폐혈관계 검사 및 평가": 4,
            "기타 계통 검사": 2,
            임상의사결정: 7,
          },
        },
      },
    },
    2: {
      total: 85,
      subjects: {
        "물리치료 중재": {
          total: 65,
          subs: {
            "근골격계 중재": 28,
            "신경계 중재": 25,
            "심폐혈관계 중재": 5,
            "림프, 피부계 중재": 2,
            "물리치료 문제해결": 5,
          },
        },
        의료관계법규: {
          total: 20,
          subs: {
            의료법: 5,
            의료기사법: 5,
            노인복지법: 4,
            장애인복지법: 3,
            국민건강보험법: 3,
          },
        },
      },
    },
  };
}

const LimitSelectionState = {
  lastKey: "",
  userOverride: false,
};

// 원래 alert 함수 보존(필요 시 원래 alert으로 복구 가능)
if (
  typeof window !== "undefined" &&
  typeof window.alert === "function" &&
  typeof window.__PTG_ORIG_ALERT === "undefined"
) {
  window.__PTG_ORIG_ALERT = window.alert;
}

(function () {
  "use strict";
  // 테스트 모드 감지 (URL 쿼리로 활성화)
  if (
    typeof window !== "undefined" &&
    window.location &&
    window.location.href
  ) {
    try {
      const urlParams = new URL(window.location.href).searchParams;
      if (urlParams.get("ptg_test_mode") === "404") {
        window.PTGQuizTestMode404 = true;
      }
      if (urlParams.get("ptg_test_mode") === "401") {
        window.PTGQuizTestMode401 = true;
      }
    } catch (e) {
      // ignore parsing errors
    }
  }
})();

// 퀴즈용 alert 헬퍼 - 브라우저 alert 대신 커스텀 모달 사용
function PTG_quiz_alert(message) {
  if (typeof document === "undefined") {
    return;
  }

  let modal = document.getElementById("ptg-quiz-alert-modal");
  let overlay, box, textEl, btn;

  if (!modal) {
    modal = document.createElement("div");
    modal.id = "ptg-quiz-alert-modal";
    modal.style.position = "fixed";
    modal.style.top = "0";
    modal.style.left = "0";
    modal.style.right = "0";
    modal.style.bottom = "0";
    modal.style.display = "none";
    modal.style.alignItems = "center";
    modal.style.justifyContent = "center";
    modal.style.zIndex = "99999";

    overlay = document.createElement("div");
    overlay.style.position = "absolute";
    overlay.style.top = "0";
    overlay.style.left = "0";
    overlay.style.right = "0";
    overlay.style.bottom = "0";
    overlay.style.background = "rgba(0,0,0,0.4)";

    box = document.createElement("div");
    box.style.position = "relative";
    box.style.background = "#ffffff";
    box.style.borderRadius = "8px";
    box.style.padding = "20px 24px";
    box.style.maxWidth = "360px";
    box.style.boxShadow = "0 4px 12px rgba(0,0,0,0.25)";
    box.style.fontSize = "14px";
    box.style.lineHeight = "1.5";
    box.style.textAlign = "center";

    textEl = document.createElement("div");
    textEl.id = "ptg-quiz-alert-text";
    textEl.style.marginBottom = "16px";

    btn = document.createElement("button");
    btn.type = "button";
    btn.textContent = "확인";
    btn.style.minWidth = "80px";
    btn.style.padding = "8px 16px";
    btn.style.borderRadius = "4px";
    btn.style.border = "none";
    btn.style.background = "#4a90e2";
    btn.style.color = "#ffffff";
    btn.style.fontWeight = "600";
    btn.style.cursor = "pointer";

    box.appendChild(textEl);
    box.appendChild(btn);
    modal.appendChild(overlay);
    modal.appendChild(box);
    document.body.appendChild(modal);

    const close = () => {
      modal.style.display = "none";
    };
    overlay.addEventListener("click", close);
    btn.addEventListener("click", close);
    document.addEventListener("keydown", function (e) {
      if (modal.style.display === "flex" && e.key === "Escape") {
        close();
      }
    });
  } else {
    textEl = document.getElementById("ptg-quiz-alert-text");
  }

  if (textEl) {
    textEl.textContent = String(message || "");
  }
  modal.style.display = "flex";
}

(function () {
  "use strict";

  // 전역 네임스페이스
  window.PTGQuiz = window.PTGQuiz || {};

  // 설정
  const config =
    typeof ptgQuiz !== "undefined"
      ? ptgQuiz
      : {
          restUrl: "/wp-json/ptg-quiz/v1/",
          nonce: "",
          userId: 0,
        };

  // PTGPlatform Polyfill: 플랫폼 전역이 없더라도 독립 동작 보장
  if (typeof window.PTGPlatform === "undefined") {
    (function () {
      const buildUrl = (endpoint) => {
        // endpoint 예: 'ptg-quiz/v1/questions/123'
        if (/^https?:\/\//i.test(endpoint)) return endpoint;
        const origin =
          typeof window !== "undefined" &&
          window.location &&
          window.location.origin
            ? window.location.origin
            : "";
        return origin + "/wp-json/" + String(endpoint).replace(/^\/+/, "");
      };
      async function api(method, endpoint, data) {
        const url = buildUrl(endpoint);
        const headers = {
          Accept: "application/json",
          "X-WP-Nonce": config.nonce || "",
        };
        const init = { method, headers, credentials: "same-origin" };
        if (data !== undefined) {
          headers["Content-Type"] = "application/json";
          init.body = JSON.stringify(data);
        }
        const res = await fetch(url, init);
        const ct = res.headers.get("content-type") || "";
        const text = await res.text();
        if (!ct.includes("application/json")) {
          throw new Error(
            `[REST Non-JSON ${res.status}] ${text.slice(0, 200)}`
          );
        }
        const json = JSON.parse(text);
        if (!res.ok) {
          const msg =
            (json && (json.message || json.code)) || `HTTP ${res.status}`;
          const err = new Error(msg);
          err.status = res.status;
          err.data = json;
          throw err;
        }
        return json;
      }
      window.PTGPlatform = {
        get: (e, q = {}) => {
          const qp = new URLSearchParams(q).toString();
          const ep = qp ? `${e}?${qp}` : e;
          return api("GET", ep);
        },
        post: (e, b = {}) => api("POST", e, b),
        patch: (e, b = {}) => api("PATCH", e, b),
        showError: (m) => console.error("[PTG Platform Polyfill] 오류:", m),
        debounce: function (fn, wait) {
          let t = null;
          return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
          };
        },
      };
    })();
  }
  // 항상 안전한 래퍼로 교체(플랫폼 스크립트가 있어도 JSON만 보장하도록)
  (function () {
    const buildUrl = (endpoint) => {
      if (/^https?:\/\//i.test(endpoint)) return endpoint;
      const origin =
        typeof window !== "undefined" &&
        window.location &&
        window.location.origin
          ? window.location.origin
          : "";
      // ptg-quiz/v1/... 같은 엔드포인트 문자열을 받도록 고정
      return origin + "/wp-json/" + String(endpoint).replace(/^\/+/, "");
    };
    async function safeApi(method, endpoint, data) {
      const url = buildUrl(endpoint);
      const headers = {
        Accept: "application/json",
        "X-WP-Nonce": config.nonce || "",
      };
      const init = { method, headers, credentials: "same-origin" };
      if (data !== undefined) {
        headers["Content-Type"] = "application/json";
        init.body = JSON.stringify(data);
      }
      const res = await fetch(url, init);
      const ct = res.headers.get("content-type") || "";
      const text = await res.text();

      // 응답이 JSON이 아닌 경우 에러 처리
      if (!ct.includes("application/json")) {
        // 401이나 403인 경우 권한 문제일 수 있음
        if (res.status === 401 || res.status === 403) {
          throw new Error(
            `권한이 없습니다. 로그인이 필요합니다. (HTTP ${res.status})`
          );
        }
        // 404인 경우 엔드포인트가 존재하지 않음
        if (res.status === 404) {
          throw new Error(
            `API 엔드포인트를 찾을 수 없습니다: ${endpoint} (HTTP 404)`
          );
        }
        // 기타 HTML 응답
        console.error("[PTG Quiz] JSON이 아닌 응답:", {
          status: res.status,
          contentType: ct,
          url: url,
          preview: text.slice(0, 200),
        });
        throw new Error(
          `서버 오류: JSON 응답을 받지 못했습니다. (HTTP ${res.status})`
        );
      }

      let json;
      try {
        json = JSON.parse(text);
      } catch (e) {
        console.error("[PTG Quiz] JSON 파싱 실패:", {
          status: res.status,
          contentType: ct,
          url: url,
          text: text.slice(0, 200),
        });
        throw new Error(`JSON 파싱 실패: ${e.message}`);
      }

      if (!res.ok) {
        const errorMsg =
          (json &&
            (json.message || json.code || (json.data && json.data.message))) ||
          `HTTP ${res.status}`;
        throw new Error(errorMsg);
      }
      return json;
    }
    // 기존 객체 유지하면서 메서드만 래핑
    try {
      window.PTGPlatform = Object.assign({}, window.PTGPlatform || {}, {
        get: (e, q = {}) => {
          const qp = new URLSearchParams(q).toString();
          const ep = qp ? `${e}?${qp}` : e;
          return safeApi("GET", ep);
        },
        post: (e, b = {}) => safeApi("POST", e, b),
        patch: (e, b = {}) => safeApi("PATCH", e, b),
      });
    } catch (_) {}
  })();

  /**
   * 기기 타입 감지 (PC, Tablet, Mobile)
   * @returns {string} 'pc', 'tablet', 또는 'mobile'
   */
  function detectDeviceType() {
    const userAgent = navigator.userAgent.toLowerCase();
    const platform = navigator.platform ? navigator.platform.toLowerCase() : "";

    // iPad 감지 (iOS + iPad user agent)
    const isIPad =
      /ipad/i.test(userAgent) ||
      (platform === "macintel" && navigator.maxTouchPoints > 1);

    // iPhone 감지
    const isIPhone = /iphone/i.test(userAgent) && !isIPad;

    // Android 태블릿 감지
    const isAndroid = /android/i.test(userAgent);
    const isAndroidTablet = isAndroid && !/mobile/i.test(userAgent);

    // 태블릿 판단
    if (isIPad || isAndroidTablet) {
      return "tablet";
    }

    // 모바일 판단 (iPhone, Android 모바일 등)
    if (isIPhone || (isAndroid && /mobile/i.test(userAgent))) {
      return "mobile";
    }

    // 그 외 모든 기기 (PC)
    return "pc";
  }

  // 상태 관리
  const QuizState = {
    questions: [], // 문제 ID 목록 배열 (연속 퀴즈용)
    currentIndex: 0, // 현재 문제 인덱스
    questionId: 0, // 현재 문제 ID (호환성 유지)
    questionData: null,
    userState: null,
    userAnswer: "",
    isAnswered: false,
    tempAnswer: null, // 문제 풀이 시 임시 저장 (다음 문제 클릭 시 DB 저장용)
    timer: null,
    timerSeconds: 0,
    timerInterval: null,
    drawingEnabled: false,
    isInitialized: false, // 중복 초기화 방지 플래그
    initializing: false, // 초기화 진행 중 재진입 방지
    deviceType: detectDeviceType(), // 기기 타입 (pc, tablet, mobile)
    eventsBound: false, // 이벤트 중복 바인딩 방지
    // 앱 상태머신
    appState: "idle", // 'idle' | 'running' | 'finished' | 'terminated'
    requestSeq: 0, // 요청 시퀀스 증가값
    lastAppliedSeq: 0, // 마지막으로 적용된 시퀀스
    // 드로잉 상태
    drawingTool: "pen", // 'pen' 또는 'eraser'
    drawingHistory: [], // Undo/Redo를 위한 히스토리
    drawingHistoryIndex: -1, // 현재 히스토리 인덱스
    strokes: [], // 선 추적 배열 (스마트 지우개용) - 각 선의 메타데이터
    nextStrokeId: 1, // 다음 선 ID (고유 ID 생성용)
    isDrawing: false,
    lastX: 0,
    lastY: 0,
    canvasContext: null,
    penColor: "rgb(255, 0, 0)", // 펜 색상 (기본값: 빨강)
    penWidth: 10, // 펜 두께 (기본값: 10px)
    penAlpha: 0.5, // 펜 불투명도 (기본값: 0.5 = 50%, 0~1 범위, 높을수록 진함)
    drawingSaveTimeout: null, // 드로잉 자동 저장 디바운스 타이머
    drawingPoints: [], // 현재 그리는 선의 점들 (자동 정렬용)
    autoAlignTimeout: null, // 자동 정렬 타이머
    autoAlignEnabled: true, // 자동 정렬 활성화 여부
    currentStrokeStartIndex: -1, // 현재 선의 시작 히스토리 인덱스
    currentStrokeId: null, // 현재 그리는 선의 ID
    giveUpInProgress: false, // 포기하기 중복 실행 방지
    eventsBound: false, // 이벤트 중복 바인딩 방지
    terminated: false, // 포기/종료 이후 추가 동작 차단 (호환용)
    savingDrawing: false, // 드로잉 저장 중 플래그
    // 퀴즈 결과 추적
    answers: [], // 답안 제출 결과 배열 { questionId, isCorrect, userAnswer, correctAnswer }
    startTime: null, // 퀴즈 시작 시간 (타임스탬프)
    lastBlockingMessage: "",
    // 영구 필터 (북마크, 복습 등 - URL 파라미터에서 온 필터)
    persistentFilters: {
      bookmarked: false,
      needsReview: false,
    },
    persistentFilters: {
      bookmarked: false,
      needsReview: false,
    },
    // 관리자 편집 모드
    isEditing: false,
    // 퀴즈 모드 (learning | exam | mock_exam)
    mode: "learning",
    // 모의고사 정보
    session: null, // Round (1001)
    exam_course: null, // Period (1)
  };

  /**
   * 사용량 제한 확인 (Usage Limit Check)
   * @param {string} type 'mock' (모의고사) or 'general' (일반 퀴즈)
   * @param {number} amount 추가할 문제 수 (일반 퀴즈용)
   * @returns {boolean} true if allowed, false if blocked
   */
  function checkUsageLimit(type, amount = 0) {
    // 설정이 없으면 통과 (안전장치)
    if (typeof window.ptgQuiz === "undefined") return true;

    const config = window.ptgQuiz;
    const memberGrade = config.memberGrade || "guest"; // guest, basic, trial, premium
    const limits = config.limits || { mock: 1, quiz: 20, trial: 30 };
    const membershipUrl = config.membershipUrl || "/membership";

    // 프리미엄 회원은 무제한
    if (memberGrade === "premium" || memberGrade === "pt_admin") {
      return true;
    }

    let storageKey = "";
    let limit = 0;
    let currentCount = 0;
    let message = "";

    // 날짜 기반 키 생성 (Asia/Seoul 기준 자정 리셋을 위해 날짜 포함)
    // 클라이언트 시간 기준이지만, 대부분의 사용자가 한국에 있다고 가정
    // 더 정확한 제어를 위해 서버 시간을 쓰거나, 날짜 문자열을 키에 포함
    const today = new Date()
      .toLocaleDateString("ko-KR", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
      })
      .replace(/\. /g, "-")
      .replace(".", "");

    if (type === "mock") {
      storageKey = `ptg_mock_exam_count_${today}`;
      limit = limits.mock; // 1회
      currentCount = parseInt(localStorage.getItem(storageKey) || "0", 10);

      if (currentCount >= limit) {
        if (memberGrade === "trial") {
          message = `Trial 체험 회원님은 오늘의 모의고사 무료 이용 ${limit}회를 모두 사용하셨습니다.\n프리미엄으로 업그레이드하면 무제한으로 이용할 수 있습니다.`;
        } else {
          message = `오늘의 모의고사 무료 이용 ${limit}회를 모두 사용하셨습니다.\n프리미엄 회원은 무제한으로 전체 모의고사를 이용할 수 있습니다.`;
        }
      }
    } else {
      // General Quiz
      storageKey = `ptg_quiz_question_count_${today}`;
      limit = memberGrade === "trial" ? limits.trial : limits.quiz;
      currentCount = parseInt(localStorage.getItem(storageKey) || "0", 10);

      if (currentCount + amount > limit) {
        const remaining = Math.max(0, limit - currentCount);
        if (memberGrade === "guest") {
          message = `하루 무료 체험 ${limit}문제를 모두 사용하셨습니다.\n로그인하면 하루 ${limits.quiz}문제를 계속 학습하실 수 있습니다.`;
          // 비로그인인데 로그인하면 더 준다는 멘트가 맞는지 확인 필요.
          // 기획상 비로그인도 20, Basic도 20이면 "로그인하면..." 멘트는 좀 애매할 수 있음.
          // 하지만 기획서 멘트: "로그인하면 하루 20문제를 계속 학습하실 수 있습니다." (비로그인 20 -> 로그인 20 리셋? 아니면 공유? 일단 별도 키 사용하므로 리셋 효과 있음)
          if (currentCount >= limit) {
            // 이미 다 씀
          } else {
            // 이번에 초과
            message = `이번 퀴즈(${amount}문제)를 시작하면 하루 제한(${limit}문제)을 초과합니다.\n남은 횟수: ${remaining}문제`;
            // return false; // 제거: 아래 공통 로직에서 alert 후 redirect 처리
          }
        } else if (memberGrade === "trial") {
          message = `Trial 체험 회원님은 오늘의 체험 학습(${limit}문제)을 모두 사용하셨습니다.\n프리미엄으로 업그레이드하면 전 과목·전 문항을 무제한으로 학습할 수 있습니다.`;
        } else {
          // Basic
          message = `무료 회원은 하루 ${limit}문제까지만 정답을 확인할 수 있습니다.\n프리미엄 멤버십으로 업그레이드하고 무제한으로 이용하세요!`;
        }
      }
    }

    if (message) {
      if (confirm(message)) {
        if (memberGrade === "guest" && type !== "mock") {
          // 비로그인 일반 퀴즈 초과 시 로그인 페이지로? 기획서엔 [로그인] 버튼 언급.
          // config.loginUrl이 없으므로 membershipUrl 대신 로그인 URL이 필요할 수도 있음.
          // 하지만 ptgates-quiz.php에는 loginUrl을 안 넘겨줬음.
          // 일단 membershipUrl로 통일하거나, 필요시 수정.
          // 기획서: "모두 확인 시 /membership 페이지로 이동." (공통)
          window.location.href = membershipUrl;
        } else {
          window.location.href = membershipUrl;
        }
      }
      return false;
    }

    return true;
  }

  /**
   * 사용량 증가 (퀴즈 시작 시 호출)
   */
  function incrementUsage(type, amount = 1) {
    if (typeof window.ptgQuiz === "undefined") return;
    const config = window.ptgQuiz;
    const memberGrade = config.memberGrade || "guest";

    if (memberGrade === "premium" || memberGrade === "pt_admin") return;

    const today = new Date()
      .toLocaleDateString("ko-KR", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
      })
      .replace(/\. /g, "-")
      .replace(".", "");
    let storageKey = "";

    if (type === "mock") {
      storageKey = `ptg_mock_exam_count_${today}`;
    } else {
      storageKey = `ptg_quiz_question_count_${today}`;
    }

    let currentCount = parseInt(localStorage.getItem(storageKey) || "0", 10);
    localStorage.setItem(storageKey, (currentCount + amount).toString());
  }

  /**
   * 상태 전환 및 UI 반영
   */
  function setState(nextState) {
    const prev = QuizState.appState;
    QuizState.appState = nextState;
    // 호환 플래그 동기화
    QuizState.terminated =
      nextState === "terminated" || nextState === "finished";
    applyUIForState();
    // 타이머 제어
    if (nextState === "running") {
      startTimer();
    } else {
      if (QuizState.timerInterval) {
        clearInterval(QuizState.timerInterval);
        QuizState.timerInterval = null;
      }
    }
  }

  /**
   * 현재 상태에 맞게 UI 토글
   */
  function applyUIForState() {
    const filterSection = document.getElementById("ptg-quiz-filter-section");
    const gridSection = document.getElementById("ptg-quiz-grid-section");
    const progress = document.getElementById("ptgates-progress-section");
    const toolbar = document.querySelector(".ptg-quiz-toolbar");
    const cardWrapper = document.querySelector(".ptg-quiz-card-wrapper");
    const actions = document.querySelector(".ptg-quiz-actions");
    const resultSection = document.getElementById("ptg-quiz-result-section");

    const show = (el, display = "block") => {
      if (el) el.style.display = display;
    };
    const hide = (el) => {
      if (el) el.style.display = "none";
    };

    switch (QuizState.appState) {
      case "idle":
        show(filterSection, "flex");
        show(gridSection, "grid");
        hide(progress);
        hide(toolbar);
        hide(cardWrapper);
        hide(actions);
        hide(resultSection);
        // idle 상태로 돌아왔을 때 활성 필터 표시 업데이트
        if (QuizState.persistentFilters) {
          updateActiveFilters(
            QuizState.persistentFilters.bookmarked,
            QuizState.persistentFilters.needsReview,
            QuizState.persistentFilters.wrongOnly
          );
        }
        break;
      case "running":
        hide(filterSection);
        hide(gridSection);
        show(progress, "block");
        show(toolbar, "flex");
        show(cardWrapper, "block");
        show(actions, "flex");
        hide(resultSection);
        // 암기카드 버튼 강제 표시 및 순서 보장 (상태 변경 시 재확인)
        setTimeout(function () {
          if (
            window.PTGQuizToolbar &&
            window.PTGQuizToolbar.ensureFlashcardButton
          ) {
            window.PTGQuizToolbar.ensureFlashcardButton();
          }
        }, 100);
        break;
      case "finished":
      case "terminated":
        hide(filterSection);
        hide(gridSection);
        hide(progress);
        hide(toolbar);
        hide(cardWrapper);
        hide(actions);
        // 안전: 카드 내용 제거 및 버튼 비활성화
        try {
          const card = document.getElementById("ptg-quiz-card");
          if (card) {
            card.innerHTML = "";
          }
          const btnCheck = document.getElementById("ptg-btn-check-answer");
          const btnNext = document.getElementById("ptg-btn-next-question");
          const btnGiveup = document.getElementById("ptgates-giveup-btn");
          if (btnCheck) {
            btnCheck.disabled = true;
            btnCheck.style.display = "none";
          }
          if (btnNext) {
            btnNext.disabled = true;
            btnNext.style.display = "none";
          }
          if (btnGiveup) {
            btnGiveup.disabled = true;
            btnGiveup.style.pointerEvents = "none";
          }
        } catch (e) {}
        show(resultSection, "block");
        // 결과로 스크롤
        if (
          resultSection &&
          typeof resultSection.scrollIntoView === "function"
        ) {
          resultSection.scrollIntoView({ behavior: "smooth", block: "start" });
        }
        break;
    }
  }

  function showBlockingAlert(message) {
    const text =
      typeof message === "string" && message.trim().length > 0
        ? message.trim()
        : "문제를 불러오는 중 오류가 발생했습니다.";

    QuizState.lastBlockingMessage = text;

    const runNativeAlert = () => {
      try {
        if (
          typeof window !== "undefined" &&
          typeof window.alert === "function"
        ) {
          window.alert(text);
        } else if (typeof PTG_quiz_alert === "function") {
          PTG_quiz_alert(text);
        } else {
          console.warn("[PTG Quiz] ALERT:", text);
        }
      } catch (alertError) {
        console.warn(
          "[PTG Quiz] window.alert 실패, 커스텀 알림 사용:",
          alertError
        );
        if (typeof PTG_quiz_alert === "function") {
          PTG_quiz_alert(text);
        }
      } finally {
        if (typeof PTG_quiz_alert === "function") {
          PTG_quiz_alert(text);
        }
      }
    };

    if (
      typeof window !== "undefined" &&
      typeof window.setTimeout === "function"
    ) {
      window.setTimeout(runNativeAlert, 0);
    } else {
      runNativeAlert();
    }
  }

  // loadPenSettings, savePenSettings 함수는 quiz-drawing.js로 이동됨

  // 툴바 관련 함수는 quiz-toolbar.js로 이동됨

  /**
   * 초기화
   */
  function init() {
    // 중복 초기화/동시 초기화 방지
    if (QuizState.isInitialized || QuizState.initializing) {
      return;
    }
    QuizState.initializing = true;

    // 기기 타입 감지 및 저장 (필터링에 사용)
    QuizState.deviceType = detectDeviceType();
    console.log("[PTG Quiz] Device Type:", QuizState.deviceType);

    // 저장된 펜 설정 불러오기
    if (window.PTGQuizDrawing && window.PTGQuizDrawing.loadPenSettings) {
      window.PTGQuizDrawing.loadPenSettings();
    }

    const container = document.getElementById("ptg-quiz-container");
    if (!container) {
      console.error("[PTG Quiz] 컨테이너를 찾을 수 없음: ptg-quiz-container");
      return;
    }

    // URL 파라미터에서 필터 읽기 (우선순위: URL 파라미터 > data 속성)
    const urlParams = new URLSearchParams(window.location.search);

    // URL 파라미터에서 필터 읽기
    const yearFromUrl = urlParams.get("year")
      ? parseInt(urlParams.get("year"))
      : null;
    const subjectFromUrl = urlParams.get("subject") || "";
    const limitFromUrlRaw = urlParams.get("limit");
    const limitFromUrl =
      limitFromUrlRaw !== null && limitFromUrlRaw !== ""
        ? parseInt(limitFromUrlRaw)
        : null;
    const sessionFromUrl = urlParams.get("session")
      ? parseInt(urlParams.get("session"))
      : null;
    const fullSessionFromUrl =
      urlParams.get("full_session") === "1" ||
      urlParams.get("full_session") === "true";
    const bookmarkedFromUrl =
      urlParams.get("bookmarked") === "1" ||
      urlParams.get("bookmarked") === "true";
    const needsReviewFromUrl =
      urlParams.get("needs_review") === "1" ||
      urlParams.get("needs_review") === "true";
    const wrongOnlyFromUrl =
      urlParams.get("wrong_only") === "1" ||
      urlParams.get("wrong_only") === "true";
    const reviewOnlyFromUrl =
      urlParams.get("review_only") === "1" ||
      urlParams.get("review_only") === "true";
    const autoStart =
      urlParams.get("auto_start") === "1" ||
      urlParams.get("auto_start") === "true";

    // data 속성에서 필터 읽기
    const yearFromData = container.dataset.year
      ? parseInt(container.dataset.year)
      : null;
    const subjectFromData = container.dataset.subject || "";
    const limitFromDataRaw = container.dataset.limit;
    const limitFromData =
      typeof limitFromDataRaw !== "undefined" &&
      limitFromDataRaw !== null &&
      limitFromDataRaw !== ""
        ? parseInt(limitFromDataRaw)
        : null;
    const sessionFromData = container.dataset.session
      ? parseInt(container.dataset.session)
      : null;
    const fullSessionFromData = container.dataset.fullSession === "1";
    const bookmarkedFromData = container.dataset.bookmarked === "1";
    const needsReviewFromData = container.dataset.needsReview === "1";
    const reviewOnlyFromData = container.dataset.reviewOnly === "1";

    // 최종 필터 값 (URL 파라미터 우선)
    const year = yearFromUrl || yearFromData;
    const subject = subjectFromUrl || subjectFromData;
    const limit =
      limitFromUrl !== null && !Number.isNaN(limitFromUrl)
        ? limitFromUrl
        : limitFromData !== null && !Number.isNaN(limitFromData)
        ? limitFromData
        : null;
    const session = sessionFromUrl || sessionFromData;
    const fullSession = fullSessionFromUrl || fullSessionFromData;
    const bookmarked = bookmarkedFromUrl || bookmarkedFromData;
    const needsReview = needsReviewFromUrl || needsReviewFromData;
    const wrongOnly = wrongOnlyFromUrl; // data 속성은 없음
    const reviewOnly = reviewOnlyFromUrl || reviewOnlyFromData;

    const questionId = parseInt(container.dataset.questionId) || 0;

    // 1200-ptgates-quiz는 기본적으로 기출문제 제외하고 5문제 연속 퀴즈
    // question_id가 없고 필터도 없으면 기본값으로 5문제 시작
    // 1200-ptgates-quiz는 기본적으로 기출문제 제외하고 5문제 연속 퀴즈
    // question_id가 없고 필터도 없으면 기본값으로 5문제 시작
    const hasFilters =
      year ||
      subject ||
      limit ||
      session ||
      bookmarked ||
      needsReview ||
      wrongOnly ||
      reviewOnly;
    const useDefaultFilters = !questionId && !hasFilters;

    // 타이머 설정: 1교시(90분) 또는 2교시(75분)가 아니면 문제당 50초로 계산
    const timerMinutes = parseInt(container.dataset.timer) || 0;
    const isSession1 = timerMinutes === 90;
    const isSession2 = timerMinutes === 75;

    // 암기카드와 노트 버튼 강제 제거 (캐시된 버전 대응) - 제거됨
    // const flashcardBtn = document.querySelector('.ptg-btn-flashcard');
    // const notebookBtn = document.querySelector('.ptg-btn-notebook');
    // if (flashcardBtn) {
    //     flashcardBtn.remove();
    // }
    // if (notebookBtn) {
    //     notebookBtn.remove();
    // }

    // 메모 패널 초기 상태: 숨김
    const notesPanel = document.getElementById("ptg-notes-panel");
    if (notesPanel) {
      notesPanel.style.display = "none";
    }

    // 모바일에서 드로잉 기능 활성화 (사용자 요청)
    /*
    if (QuizState.deviceType === "mobile") {
      const btnDrawing = document.querySelector(".ptg-btn-drawing");
      const drawingToolbar = document.getElementById("ptg-drawing-toolbar");
      if (btnDrawing) {
        btnDrawing.style.display = "none";
      }
      if (drawingToolbar) {
        drawingToolbar.style.display = "none";
      }
    }
    */

    // 이벤트 리스너 등록
    setupEventListeners();

    // 실전 모의 학습Tip 버튼 이벤트
    setupTipModal();

    // 필터 UI 설정
    setupFilterUI();

    // 활성 필터 표시 업데이트
    updateActiveFilters(bookmarked, needsReview, wrongOnly);

    // 영구 필터 상태 저장 (조회 버튼에서 사용)
    QuizState.persistentFilters = {
      bookmarked: bookmarked || false,
      needsReview: needsReview || false,
      wrongOnly: wrongOnly || false,
      reviewOnly: reviewOnly || false,
    };

    // 초기 상태 적용
    setState("idle");

    // 북마크/복습만 있고 다른 필터가 없으면 필터 섹션 표시 (단, 자동 시작이면 제외)
    const hasOnlyPersistentFilter =
      !autoStart &&
      (bookmarked || needsReview || wrongOnly || reviewOnly) &&
      !year &&
      !subject &&
      !limit &&
      !session;

    // 필터 조건이 있으면 필터 UI 숨기고 바로 시작 (단, 영구 필터만 있는 경우는 제외)
    if ((hasFilters || questionId) && !hasOnlyPersistentFilter) {
      setState("running");
    } else if (hasOnlyPersistentFilter) {
      // 영구 필터만 있으면 필터 섹션 표시하고 대기
      showFilterSection();
      return; // 필터 섹션이 표시되면 여기서 종료
    } else {
      // 필터 섹션이 표시되면 퀴즈는 시작하지 않음 (조회 클릭 시 시작)
      return; // 필터 섹션이 표시되면 여기서 종료
    }

    // 1200-ptgates-quiz는 기본적으로 기출문제 제외하고 5문제 연속 퀴즈
    // questionId가 없으면 항상 기본값으로 처리
    if (hasFilters || useDefaultFilters || questionId === 0) {
      (async () => {
        try {
          const filters = {};
          if (year) filters.year = year;
          if (subject) filters.subject = subject;

          // [수정] 복습 퀴즈나 오답 퀴즈는 무조건 무제한(limit=0)이어야 함
          if (reviewOnly || wrongOnly) {
            filters.limit = 0;
          } else if (limit !== null && !Number.isNaN(limit)) {
            // limit=0 은 무제한(북마크 전체 등)을 의미
            filters.limit = limit;
          } else if (useDefaultFilters) {
            filters.limit = 5;
          }
          if (session) {
            filters.session = session;
            filters.full_session = fullSession;
          }
          if (bookmarked) {
            filters.bookmarked = true;
          }
          if (needsReview) {
            filters.needs_review = true;
          }
          if (wrongOnly) {
            filters.wrong_only = true;
          }
          if (reviewOnly) {
            filters.review_only = true;
          }

          // --- Usage Limit Check ---
          // 모의고사 (session 필터 존재)
          if (session) {
            if (!checkUsageLimit("mock")) {
              return; // 차단
            }
          }
          // 일반 퀴즈 (session 없음)
          // 문제 수를 미리 알 수 없으므로, limit 파라미터로 체크
          else {
            // limit이 없으면 기본값 5
            // [수정] 복습 퀴즈는 무제한이므로 예상 카운트를 0으로 처리하여 한도 체크 건너뜀
            const isUnlimitedReview = reviewOnly || wrongOnly;
            const estimatedCount = isUnlimitedReview ? 0 : limit || 5;

            if (
              !isUnlimitedReview &&
              !checkUsageLimit("general", estimatedCount)
            ) {
              return; // 차단
            }
          }
          // -------------------------

          const questionIds = await loadQuestionsList(filters);

          if (questionIds === null) {
            setState("idle");
            return;
          }

          if (!questionIds || questionIds.length === 0) {
            showError("선택한 조건에 맞는 문제를 찾을 수 없습니다.");
            return;
          }

          // --- Increment Usage ---
          // 실제 로드된 문제 수로 증가 (일반 퀴즈의 경우)
          if (session) {
            incrementUsage("mock");
          } else {
            incrementUsage("general", questionIds.length);
          }
          // -----------------------

          // 문제 목록 저장
          QuizState.questions = questionIds;
          QuizState.currentIndex = 0;
          QuizState.questionId = questionIds[0];
          // 퀴즈 결과 초기화
          QuizState.answers = [];
          QuizState.startTime = null;

          // 타이머 설정: 문제 수 × 50초 (1교시/2교시가 아닌 경우)
          if (isSession1 || isSession2) {
            QuizState.timerSeconds = timerMinutes * 60;
          } else {
            QuizState.timerSeconds = questionIds.length * 50;
          }

          // 타이머 표시 즉시 업데이트 (문제 수 × 50초)
          updateTimerDisplay();

          // 진행 상태 섹션 표시
          showProgressSection();
          // 퀴즈 UI 표시
          showQuizUI();

          // 첫 번째 문제 로드
          await loadQuestion();

          // 타이머 시작
          if (container.dataset.unlimited !== "1") {
            startTimer();
          }
        } catch (error) {
          console.error("[PTG Quiz] 문제 목록 로드 오류:", error);
          showError("문제 목록을 불러오는 중 오류가 발생했습니다.");
        }
      })();
    } else if (questionId) {
      // 기존 방식: 단일 문제
      QuizState.questionId = questionId;
      // 퀴즈 결과 초기화
      QuizState.answers = [];
      QuizState.startTime = null;

      if (isSession1 || isSession2) {
        // 1교시 또는 2교시 전체 문제 풀이: 설정된 시간 그대로 사용
        QuizState.timerSeconds = timerMinutes * 60;
      } else {
        // 그 외의 경우: 문제당 50초로 계산 (단일 문제이므로 50초)
        QuizState.timerSeconds = 50;
      }

      // 타이머 표시 즉시 업데이트
      updateTimerDisplay();

      // 진행 상태 섹션 표시
      showProgressSection();
      // 퀴즈 UI 표시
      showQuizUI();

      // 문제 로드
      loadQuestion();

      // 타이머 시작
      if (container.dataset.unlimited !== "1") {
        startTimer();
      }
    } else {
      // 문제 ID도 필터도 없으면 기본값으로 처리
      // useDefaultFilters가 false인 경우에도 기본값으로 처리
      (async () => {
        try {
          // --- Usage Limit Check (Default) ---
          // 기본 5문제
          if (!checkUsageLimit("general", 5)) {
            return;
          }
          // -----------------------------------

          const questionIds = await loadQuestionsList({ limit: 5 });

          if (questionIds === null) {
            setState("idle");
            return;
          }

          if (!questionIds || questionIds.length === 0) {
            showError("문제를 찾을 수 없습니다.");
            return;
          }

          // --- Increment Usage ---
          incrementUsage("general", questionIds.length);
          // -----------------------

          QuizState.questions = questionIds;
          QuizState.currentIndex = 0;
          QuizState.questionId = questionIds[0];
          QuizState.timerSeconds = questionIds.length * 50;
          // 퀴즈 결과 초기화
          QuizState.answers = [];
          QuizState.startTime = null;
          // 진행 상태 섹션 표시
          showProgressSection();
          // 퀴즈 UI 표시
          // 퀴즈 UI 표시 (loadQuestion 완료 후 표시됨)
          // showQuizUI();

          // 타이머 표시 즉시 업데이트 (문제 수 × 50초)
          updateTimerDisplay();

          await loadQuestion();

          if (container.dataset.unlimited !== "1") {
            startTimer();
          }
        } catch (error) {
          console.error("[PTG Quiz] 기본 문제 목록 로드 오류:", error);
          showError("문제 목록을 불러오는 중 오류가 발생했습니다.");
        }
      })();
    }

    // 키보드 단축키
    setupKeyboardShortcuts();

    // [FIX] 포기하기 버튼 이벤트 연결 (Ghost Button Fix)
    const btnGiveup = document.getElementById("ptgates-giveup-btn");
    if (btnGiveup) {
      btnGiveup.removeEventListener("click", giveUpQuiz);
      btnGiveup.addEventListener("click", giveUpQuiz);
    }

    // 초기화 완료 플래그 설정
    QuizState.isInitialized = true;
    QuizState.initializing = false;

    // 페이지 로드 시 헤더로 스크롤 (약간의 지연을 두어 DOM이 완전히 렌더링된 후 실행)
    setTimeout(() => {
      if (window.PTGQuizToolbar && window.PTGQuizToolbar.scrollToHeader) {
        window.PTGQuizToolbar.scrollToHeader();
      }
    }, 100);
  }

  /**
   * 필터 UI 설정
   */
  function setupFilterUI() {
    // 교시 목록 로드
    const sessionSelect = document.getElementById("ptg-quiz-filter-session");
    const limitSelect = document.getElementById("ptg-quiz-filter-limit");
    if (sessionSelect) {
      loadSessions();
    }
    if (limitSelect) {
      limitSelect.addEventListener("change", () => {
        LimitSelectionState.userOverride = true;
      });
    }
    // 교시 선택 시 과목 목록 로드
    const subjectSelect = document.getElementById("ptg-quiz-filter-subject");
    const subSubjectSelect = document.getElementById(
      "ptg-quiz-filter-subsubject"
    );
    if (sessionSelect) {
      sessionSelect.addEventListener("change", async function () {
        const session = this.value || "";
        await loadSubjectsForSession(session);
        const subjectValue = (subjectSelect && subjectSelect.value) || "";
        const subValue = (subSubjectSelect && subSubjectSelect.value) || "";
        // applyRecommendedLimit(session || null, subjectValue || null, subValue || null);
      });
    }

    // 과목 선택 시 세부과목 목록 채우기
    if (subjectSelect) {
      subjectSelect.addEventListener("change", async function () {
        const session =
          (document.getElementById("ptg-quiz-filter-session") || {}).value ||
          "";
        const subject = this.value || "";
        await populateSubSubjects(session, subject);
        const subValue = (subSubjectSelect && subSubjectSelect.value) || "";
        // applyRecommendedLimit(session || null, subject || null, subValue || null);
      });
    }

    if (subSubjectSelect) {
      subSubjectSelect.addEventListener("change", function () {
        const session =
          (document.getElementById("ptg-quiz-filter-session") || {}).value ||
          "";
        const subject = (subjectSelect && subjectSelect.value) || "";
        const subValue = this.value || "";
        // applyRecommendedLimit(session || null, subject || null, subValue || null);
      });
    }

    // 조회 버튼 클릭 시 퀴즈 시작
    const startBtn = document.getElementById("ptg-quiz-start-btn");
    if (startBtn) {
      startBtn.addEventListener("click", startQuizFromFilter);
    }
  }

  /**
   * 교시 목록 로드 (ptg-quiz/v1/sessions)
   */
  async function loadSessions() {
    const sessionSelect = document.getElementById("ptg-quiz-filter-session");
    if (!sessionSelect) return;
    try {
      let sessions = [];
      if (USE_SERVER_SUBJECTS) {
        const response = await PTGPlatform.get("ptg-quiz/v1/sessions");
        sessions =
          response && response.success && Array.isArray(response.data)
            ? response.data
            : [];
      }
      // 기본 옵션
      sessionSelect.innerHTML = '<option value="">교시</option>';
      // Fallback: 로그인 실패/호출 실패 시 1,2 제공
      if (!sessions || sessions.length === 0) {
        sessions = [1, 2];
      }
      sessions.forEach((no) => {
        const opt = document.createElement("option");
        opt.value = String(no);
        opt.textContent = `${no}교시`;
        sessionSelect.appendChild(opt);
      });
      // 디폴트(전체) 상태에서 과목/세부과목은 "전체" 기준으로 채움
      await loadSubjectsForSession("");
    } catch (e) {
      console.error("[PTG Quiz] 교시 목록 로드 오류:", e);
      // 치명적 오류 시에도 기본 옵션 제공
      try {
        sessionSelect.innerHTML =
          '<option value="">교시</option><option value="1">1교시</option><option value="2">2교시</option>';
        // 오류 시에도 디폴트(전체) 기준 과목/세부과목 채우기
        await loadSubjectsForSession("");
      } catch (_) {}
    }
  }

  /**
   * 교시별 과목 목록 로드
   */
  async function loadSubjectsForSession(session) {
    const subjectSelect = document.getElementById("ptg-quiz-filter-subject");
    const subSubjectSelect = document.getElementById(
      "ptg-quiz-filter-subsubject"
    );
    if (!subjectSelect) return;

    // 기본 초기화
    subjectSelect.innerHTML = '<option value="">과목</option>';
    if (subSubjectSelect) {
      subSubjectSelect.innerHTML = '<option value="">세부과목</option>';
    }

    try {
      let subjects = [];
      if (USE_SERVER_SUBJECTS) {
        // 교시가 없어도(전체) API 호출
        const endpoint = session
          ? `ptg-quiz/v1/subjects?session=${session}`
          : "ptg-quiz/v1/subjects";
        const response = await PTGPlatform.get(endpoint);
        subjects =
          response && response.success && Array.isArray(response.data)
            ? response.data
            : [];
      }

      // Fallback (클라이언트 매핑 - 공통 MAP 주입)
      if (!subjects || subjects.length === 0) {
        const mapping =
          typeof ptgQuiz !== "undefined" && ptgQuiz.subjectMap
            ? ptgQuiz.subjectMap
            : {};

        if (session) {
          // Specific Session
          if (mapping[session] && mapping[session].subjects) {
            subjects = Object.keys(mapping[session].subjects);
          }
        } else {
          // All Sessions
          // 교시 미선택 시 모든 과목 합치기
          Object.keys(mapping).forEach((sKey) => {
            if (mapping[sKey] && mapping[sKey].subjects) {
              subjects = subjects.concat(Object.keys(mapping[sKey].subjects));
            }
          });
        }
      }

      // 과목 목록 추가 (있을 때만)
      if (subjects && subjects.length) {
        // 중복 제거
        const uniqueSubjects = Array.from(new Set(subjects));
        uniqueSubjects.forEach((subject) => {
          const option = document.createElement("option");
          option.value = subject;
          option.textContent = subject;
          subjectSelect.appendChild(option);
        });
        // 교시 선택만으로는 세부과목은 "해당 교시 전체" 기준으로 채움
        const sess = session || "";
        await populateSubSubjects(sess, "");
      }
    } catch (error) {
      console.error("[PTG Quiz] 과목 목록 로드 오류:", error);
      // Fallback (오류 시에도 기본 과목 채우기)
      // Fallback (오류 시에도 기본 과목 채우기)
      try {
        const mapping =
          typeof ptgQuiz !== "undefined" && ptgQuiz.subjectMap
            ? ptgQuiz.subjectMap
            : {};
        let subjects = [];

        if (session) {
          if (mapping[session] && mapping[session].subjects) {
            subjects = Object.keys(mapping[session].subjects);
          }
        } else {
          Object.keys(mapping).forEach((sKey) => {
            if (mapping[sKey] && mapping[sKey].subjects) {
              subjects = subjects.concat(Object.keys(mapping[sKey].subjects));
            }
          });
        }

        if (subjects.length) {
          subjects = [...new Set(subjects)];
          subjects.forEach((subject) => {
            const option = document.createElement("option");
            option.value = subject;
            option.textContent = subject;
            subjectSelect.appendChild(option);
          });
          // 자동 선택 및 세부과목 로드 트리거
          if (!subjectSelect.value && session) {
            subjectSelect.value = String(subjects[0]);
            try {
              subjectSelect.dispatchEvent(
                new Event("change", { bubbles: true })
              );
            } catch (_) {}
          }
        }
      } catch (_) {}
    }

    const sessionValue =
      (document.getElementById("ptg-quiz-filter-session") || {}).value ||
      session ||
      "";
    const subjectValue = (subjectSelect && subjectSelect.value) || "";
    const subValue = (subSubjectSelect && subSubjectSelect.value) || "";
    // applyRecommendedLimit(sessionValue || null, subjectValue || null, subValue || null);
  }

  /**
   * 세부과목 목록 채우기 (DB 기반 REST 호출)
   */
  async function populateSubSubjects(session, subject) {
    const select = document.getElementById("ptg-quiz-filter-subsubject");
    if (!select) return;

    select.innerHTML = '<option value="">세부과목</option>';
    try {
      let list = [];

      // 1) 특정 과목이 선택된 경우 OR 교시만 선택된 경우 OR 전체인 경우 -> 모두 API 호출 시도
      // 백엔드에서 endpoint가 이미 전체 조회를 지원하도록 수정됨.
      if (USE_SERVER_SUBJECTS) {
        // Query param construction
        const params = new URLSearchParams();
        if (session) params.append("session", session);
        if (subject) params.append("subject", subject);

        const endpoint = `ptg-quiz/v1/subsubjects?${params.toString()}`;
        const response = await PTGPlatform.get(endpoint);
        list =
          response && response.success && Array.isArray(response.data)
            ? response.data
            : [];
      }

      // Fallback (클라이언트 매핑 - 공통 MAP 주입)
      const mapping =
        typeof ptgQuiz !== "undefined" && ptgQuiz.subjectMap
          ? ptgQuiz.subjectMap
          : {};

      // 서버에서 못 받았거나, 집계 모드일 때는 정적 매핑으로 계산
      if (!list || list.length === 0) {
        const sessKey = session ? String(session) : null;
        let all = [];

        // Helper to extract sub-subjects from the new map structure
        const getSubs = (sKey, subjKey) => {
          if (
            mapping[sKey] &&
            mapping[sKey].subjects &&
            mapping[sKey].subjects[subjKey] &&
            mapping[sKey].subjects[subjKey].subs
          ) {
            return Object.keys(mapping[sKey].subjects[subjKey].subs);
          }
          return [];
        };

        if (subject) {
          // 특정 과목의 세부과목
          if (sessKey) {
            all = getSubs(sessKey, subject);
          } else {
            // 교시가 전체인 경우 → 모든 교시에서 해당 과목의 세부과목을 합쳐서 표시
            Object.keys(mapping).forEach((sk) => {
              all = all.concat(getSubs(sk, subject));
            });
          }
        } else if (sessKey) {
          // 특정 교시 전체 세부과목
          if (mapping[sessKey] && mapping[sessKey].subjects) {
            Object.keys(mapping[sessKey].subjects).forEach((subj) => {
              all = all.concat(getSubs(sessKey, subj));
            });
          }
        } else {
          // 교시도 선택 안 한 경우: 전체 교시의 전체 세부과목
          Object.keys(mapping).forEach((sk) => {
            if (mapping[sk] && mapping[sk].subjects) {
              Object.keys(mapping[sk].subjects).forEach((subj) => {
                all = all.concat(getSubs(sk, subj));
              });
            }
          });
        }
        list = Array.from(new Set(all));
      }

      if (list && list.length) {
        // 최종 중복 제거
        const uniqueList = Array.from(new Set(list));
        uniqueList.forEach((name) => {
          const opt = document.createElement("option");
          opt.value = name;
          opt.textContent = name;
          select.appendChild(opt);
        });
      }
    } catch (e) {
      console.error("[PTG Quiz] 세부과목 목록 로드 오류:", e);
    }
  }

  // 검색 토글 버튼 이벤트
  const searchToggle = document.getElementById("ptg-quiz-search-toggle");
  const searchContainer = document.getElementById("ptg-quiz-search-container");
  if (searchToggle && searchContainer) {
    searchToggle.addEventListener("click", function () {
      const isVisible = searchContainer.style.display !== "none";
      searchContainer.style.display = isVisible ? "none" : "flex";
      searchToggle.classList.toggle("active", !isVisible);

      // 포커스 이동 및 초기화
      if (!isVisible) {
        const idInput = document.getElementById("ptg-quiz-search-id");
        if (idInput) setTimeout(() => idInput.focus(), 100);
      } else {
        // 닫힐 때 입력값 초기화 (사용자 요청)
        const idInput = document.getElementById("ptg-quiz-search-id");
        const keywordInput = document.getElementById("ptg-quiz-search-keyword");
        if (idInput) idInput.value = "";
        if (keywordInput) keywordInput.value = "";
      }
    });
  }

  // 검색 입력창 엔터 키 이벤트
  const searchIdInput = document.getElementById("ptg-quiz-search-id");
  const searchKeywordInput = document.getElementById("ptg-quiz-search-keyword");
  const handleSearchEnter = function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      const startBtn = document.getElementById("ptg-quiz-start-btn");
      if (startBtn) startBtn.click();
    }
  };

  if (searchIdInput) {
    searchIdInput.addEventListener("keydown", handleSearchEnter);
  }
  if (searchKeywordInput) {
    searchKeywordInput.addEventListener("keydown", handleSearchEnter);
  }

  function getRecommendedLimitValue(session, subject, subsubject) {
    const sessKey = session ? String(session) : null;
    const normalizedSubject = subject || "";
    const normalizedSub = subsubject || "";

    if (sessKey && SESSION_STRUCTURE[sessKey]) {
      const sessionData = SESSION_STRUCTURE[sessKey];
      if (
        normalizedSub &&
        normalizedSubject &&
        sessionData.subjects[normalizedSubject] &&
        sessionData.subjects[normalizedSubject].subs[normalizedSub]
      ) {
        return sessionData.subjects[normalizedSubject].subs[normalizedSub];
      }
      if (normalizedSubject && sessionData.subjects[normalizedSubject]) {
        return sessionData.subjects[normalizedSubject].total;
      }
      if (!normalizedSubject && !normalizedSub) {
        return sessionData.total;
      }
    }

    if (!sessKey && normalizedSubject) {
      let total = null;
      Object.keys(SESSION_STRUCTURE).some((key) => {
        const sessionData = SESSION_STRUCTURE[key];
        if (sessionData.subjects[normalizedSubject]) {
          total = normalizedSub
            ? sessionData.subjects[normalizedSubject].subs[normalizedSub] ||
              null
            : sessionData.subjects[normalizedSubject].total;
          return total !== null;
        }
        return false;
      });
      if (total !== null) {
        return total;
      }
    }

    if (!sessKey && !normalizedSubject && normalizedSub) {
      let subTotal = null;
      Object.keys(SESSION_STRUCTURE).some((key) => {
        const sessionData = SESSION_STRUCTURE[key];
        return (
          Object.keys(sessionData.subjects).some((subjName) => {
            const subjectData = sessionData.subjects[subjName];
            if (subjectData.subs[normalizedSub]) {
              subTotal = subjectData.subs[normalizedSub];
              return true;
            }
            return false;
          }) && subTotal !== null
        );
      });
      if (subTotal !== null) {
        return subTotal;
      }
    }

    return null;
  }

  function applyRecommendedLimit(session, subject, subsubject) {
    const limitSelect = document.getElementById("ptg-quiz-filter-limit");
    if (!limitSelect) {
      return;
    }

    const key = `${session || "all"}|${subject || "all"}|${
      subsubject || "all"
    }`;
    if (LimitSelectionState.lastKey !== key) {
      LimitSelectionState.lastKey = key;
      LimitSelectionState.userOverride = false;
    }

    if (LimitSelectionState.userOverride) {
      return;
    }

    const recommended = getRecommendedLimitValue(session, subject, subsubject);
    if (!recommended) {
      return;
    }

    let option = Array.from(limitSelect.options).find(
      (opt) => parseInt(opt.value, 10) === recommended
    );
    if (!option) {
      option = document.createElement("option");
      option.value = String(recommended);
      option.textContent = `${recommended}문제`;
      option.dataset.autoAdded = "1";
      limitSelect.appendChild(option);
    }

    const sortedOptions = Array.from(limitSelect.options).sort(
      (a, b) => parseInt(a.value, 10) - parseInt(b.value, 10)
    );

    const fragment = document.createDocumentFragment();
    sortedOptions.forEach((opt) => fragment.appendChild(opt));
    limitSelect.appendChild(fragment);

    limitSelect.value = String(recommended);
  }

  /**
   * 필터에서 조회 버튼 클릭 시 퀴즈 시작
   */
  async function startQuizFromFilter() {
    const sessionSelect = document.getElementById("ptg-quiz-filter-session");
    const subjectSelect = document.getElementById("ptg-quiz-filter-subject");
    const subSubjectSelect = document.getElementById(
      "ptg-quiz-filter-subsubject"
    );
    const limitSelect = document.getElementById("ptg-quiz-filter-limit");

    if (!sessionSelect || !subjectSelect || !limitSelect) {
      PTG_quiz_alert("필터 요소를 찾을 수 없습니다.");
      return;
    }

    // 영구 필터 읽기 (QuizState에 저장된 값 우선, 없으면 URL/컨테이너에서 읽기)
    let bookmarked = false;
    let needsReview = false;
    let wrongOnly = false;
    let reviewOnly = false;

    if (QuizState.persistentFilters) {
      // QuizState에 저장된 값 사용 (초기화 시 설정됨)
      bookmarked = QuizState.persistentFilters.bookmarked || false;
      needsReview = QuizState.persistentFilters.needsReview || false;
      if (QuizState.persistentFilters.wrongOnly) wrongOnly = true;
      if (QuizState.persistentFilters.reviewOnly) reviewOnly = true;
    } else {
      // QuizState에 없으면 URL 파라미터나 컨테이너에서 읽기
      const urlParams = new URLSearchParams(window.location.search);
      const bookmarkedFromUrl =
        urlParams.get("bookmarked") === "1" ||
        urlParams.get("bookmarked") === "true";
      const needsReviewFromUrl =
        urlParams.get("needs_review") === "1" ||
        urlParams.get("needs_review") === "true";

      const container = document.getElementById("ptg-quiz-container");
      const bookmarkedFromData =
        container && container.dataset.bookmarked === "1";
      const needsReviewFromData =
        container && container.dataset.needsReview === "1";

      bookmarked = bookmarkedFromUrl || bookmarkedFromData;
      needsReview = needsReviewFromUrl || needsReviewFromData;
    }

    // 틀린 문제만 필터 (URL 파라미터 체크)
    // 틀린 문제만 필터 (URL 파라미터 체크)
    const urlParams = new URLSearchParams(window.location.search);
    if (
      urlParams.get("wrong_only") === "1" ||
      urlParams.get("wrong_only") === "true"
    ) {
      wrongOnly = true;
    }

    // 복습문제만 Checkbox 값 읽기
    const reviewCheckbox = document.getElementById("ptg-quiz-filter-review");
    const wrongCheckbox = document.getElementById("ptg-quiz-filter-wrong");
    const drawingCheckbox = document.getElementById("ptg-quiz-filter-drawing");

    if (reviewCheckbox && reviewCheckbox.checked) {
      reviewOnly = true;
    }

    let hasDrawing = false;
    if (drawingCheckbox && drawingCheckbox.checked) {
      hasDrawing = true;
    }

    // URL 파라미터 체크 (Dashboard 연동)
    if (
      urlParams.get("review_only") === "1" ||
      urlParams.get("review_only") === "true"
    ) {
      reviewOnly = true;
    }

    // 체크박스가 있으면 URL 파라미터보다 우선 (혹은 OR 조건? 기획상 체크박스 있으면 그거 씀)
    if (wrongCheckbox && wrongCheckbox.checked) {
      wrongOnly = true;
    }

    // 교시 미선택 시 null로 설정하여 조회 조건에서 제외 (교시 전체 조회)
    const session =
      sessionSelect.value && sessionSelect.value !== ""
        ? parseInt(sessionSelect.value)
        : null;
    const subject = subjectSelect.value || null;
    const subsubject = subSubjectSelect ? subSubjectSelect.value || null : null;
    const limitVal = limitSelect.value;

    let limit = 5;
    let fullSession = false;
    let unsolvedOnly = false;

    // [수정] 복습 퀴즈나 오답 퀴즈는 무조건 무제한(limit=0)이어야 함
    // (문제수 드롭다운 조건보다 우선)
    if (reviewOnly || wrongOnly || hasDrawing) {
      limit = 0;
    } else if (limitVal === "full") {
      if (!session) {
        PTG_quiz_alert("실전 모의고사는 교시를 선택해야 합니다.");
        return;
      }
      fullSession = true;
      limit = 0; // API에서 full_session=true일 때 limit 무시됨
    } else if (limitVal === "unsolved") {
      unsolvedOnly = true;
      limit = 10; // 안푼 문제 10개 (사용자 요청)
    } else {
      limit = parseInt(limitVal) || 5;
    }

    const startBtn = document.getElementById("ptg-quiz-start-btn");

    try {
      const filters = {};
      if (session) filters.session = session;
      if (subject) filters.subject = subject;
      // 세부과목이 선택된 경우에만 전달 (빈 값은 전체와 동일)
      if (subsubject) filters.subsubject = subsubject;
      // [수정] 복습 퀴즈나 오답 퀴즈는 무조건 무제한(limit=0)이어야 함
      if (reviewOnly || wrongOnly || hasDrawing) {
        filters.limit = 0;
      } else {
        filters.limit = limit;
      }
      if (fullSession) filters.full_session = true;
      if (unsolvedOnly) filters.unsolved_only = true;

      // 영구 필터 유지 (URL 파라미터에서 온 경우 계속 유지)
      if (bookmarked) {
        filters.bookmarked = true;
      }
      if (needsReview) {
        filters.needs_review = true;
      }
      if (wrongOnly) {
        filters.wrong_only = true;
      }
      if (reviewOnly) {
        filters.review_only = true;
      }
      if (hasDrawing) {
        filters.has_drawing = true;
      }

      // 검색 필터 추가
      const searchIdInput = document.getElementById("ptg-quiz-search-id");
      const searchKeywordInput = document.getElementById(
        "ptg-quiz-search-keyword"
      );

      if (searchIdInput && searchIdInput.value.trim()) {
        filters.id = searchIdInput.value.trim();
      }
      if (searchKeywordInput && searchKeywordInput.value.trim()) {
        filters.keyword = searchKeywordInput.value.trim();
      }

      // 검색 실행 시 토글창 닫기
      const searchContainer = document.getElementById(
        "ptg-quiz-search-container"
      );
      const searchToggle = document.getElementById("ptg-quiz-search-toggle");
      if (searchContainer && searchContainer.style.display !== "none") {
        searchContainer.style.display = "none";
        if (searchToggle) searchToggle.classList.remove("active");
      }

      await startQuizWithParams(filters);
    } catch (error) {
      console.error("[PTG Quiz] 퀴즈 시작 오류:", error);
      if (!error || !error.alertShown) {
        const fallback =
          error &&
          typeof error.message === "string" &&
          error.message.trim().length > 0
            ? error.message
            : "문제를 불러오는 중 오류가 발생했습니다.";
        PTG_quiz_alert(fallback);
      }
      setState("idle");
    } finally {
      // startQuizWithParams 내부에서 처리됨 (또는 여기서 중복 처리해도 무방)
    }
  }

  /**
   * 파라미터로 퀴즈 시작 (Mock Exam 등 외부 호출용)
   */
  async function startQuizWithParams(filters) {
    const startBtn = document.getElementById("ptg-quiz-start-btn");

    if (startBtn) {
      startBtn.disabled = true;
      startBtn.classList.add("ptg-btn-loading");
    }

    try {
      // 모드 설정 (mock_exam 등)
      if (filters.mode) {
        QuizState.mode = filters.mode;
      } else {
        QuizState.mode = "learning";
      }
      // 세션/교시 정보 저장 (제출용)
      if (filters.session) QuizState.session = filters.session;
      if (filters.exam_course) QuizState.exam_course = filters.exam_course;

      const questionIds = await loadQuestionsList(filters);

      if (questionIds === null) {
        setState("idle");
        return;
      }

      if (!questionIds || questionIds.length === 0) {
        PTG_quiz_alert("선택한 조건에 맞는 문제를 찾을 수 없습니다.");
        setState("idle");
        return;
      }

      // 실행 상태로 전환
      setState("running");

      // 문제 목록 저장
      QuizState.questions = questionIds;
      QuizState.currentIndex = 0;
      QuizState.questionId = questionIds[0];
      QuizState.answers = [];
      QuizState.startTime = null;
      QuizState.giveUpInProgress = false; // 포기하기 플래그 초기화

      // 타이머 설정:
      // 1. 모의고사 모드인 경우: 설정된 시간(filters.timer_minutes) 사용
      // 2. 그 외: 문제 수 × 50초
      if (filters.timer_minutes) {
        QuizState.timerSeconds = parseInt(filters.timer_minutes) * 60;
      } else {
        QuizState.timerSeconds = questionIds.length * 50;
      }

      // 필터 섹션 숨기기
      hideFilterSection();

      // 활성 필터 표시 업데이트 (조회 후에도 필터 표시 유지)
      // 일반 조회 시 필터값은 filters 객체에 있음.
      if (QuizState.persistentFilters) {
        updateActiveFilters(
          QuizState.persistentFilters.bookmarked,
          QuizState.persistentFilters.needsReview,
          filters.wrong_only // 틀린 문제만 필터 추가
        );
      }

      // 타이머 표시 업데이트
      updateTimerDisplay();

      // 로딩 중에는 진행 상태 섹션 숨김 (로딩 화면만 표시)
      hideProgressSection();

      // 성능 개선: 문제 목록 로드 후 즉시 첫 문제 로드 시작
      // 로딩 상태를 명확하게 표시 (템플릿의 로딩 페이지 사용)
      const loadingEl = document.querySelector(".ptg-quiz-loading");
      const cardEl = document.getElementById("ptg-quiz-card");
      if (loadingEl) {
        loadingEl.style.display = "flex"; // 로딩 페이지 표시
        // 카드에 로딩 클래스 추가 (하단 border 제거용)
        if (cardEl) {
          cardEl.classList.add("loading-active");
        }
        const loadingText = loadingEl.querySelector("p");
        if (loadingText) {
          loadingText.textContent = "문제를 불러오는 중...";
        }
      }

      // 첫 번째 문제 로드 (즉시 시작하여 사용자 대기 시간 최소화)
      // 문제 목록은 이미 로드되었으므로 첫 문제를 바로 로드
      await loadQuestion().catch((error) => {
        console.error("[PTG Quiz] 첫 문제 로드 오류:", error);
        showError("문제를 불러오는 중 오류가 발생했습니다.");
      });

      // 타이머 시작 (무제한 아닐 때)
      const container = document.getElementById("ptg-quiz-container");
      if (container && container.dataset.unlimited !== "1") {
        startTimer();
      }

      // 포기하기 버튼 활성화 (상세 검색 후에도 클릭 가능하도록)
      const btnGiveup = document.getElementById("ptgates-giveup-btn");
      if (btnGiveup) {
        btnGiveup.disabled = false;
        btnGiveup.style.pointerEvents = "auto";
        btnGiveup.removeAttribute("disabled");
      }

      // 헤더로 스크롤
      if (window.PTGQuizToolbar && window.PTGQuizToolbar.scrollToHeader) {
        window.PTGQuizToolbar.scrollToHeader();
      }
    } finally {
      if (startBtn) {
        startBtn.disabled = false;
        startBtn.classList.remove("ptg-btn-loading");
      }
    }
  }

  /**
   * 활성 필터 표시 업데이트
   */
  function updateActiveFilters(bookmarked, needsReview, wrongOnly) {
    // [수정] 사용자 요청으로 활성 필터 배너(태그) 기능 제거
    // 전체 프로젝트에서 더 이상 필요하지 않음
    const filtersContainer = document.getElementById("ptg-quiz-active-filters");
    if (filtersContainer) {
      filtersContainer.style.display = "none";
      filtersContainer.innerHTML = "";
    }
  }

  /**
   * 필터 제거 (URL에서 파라미터 제거하고 페이지 새로고침)
   */
  function removeFilter(filterType) {
    const urlParams = new URLSearchParams(window.location.search);

    if (filterType === "bookmarked") {
      urlParams.delete("bookmarked");
    } else if (filterType === "needs_review") {
      urlParams.delete("needs_review");
    } else if (filterType === "wrong_only") {
      urlParams.delete("wrong_only");
    }

    // 새로운 URL 생성
    const newUrl =
      window.location.pathname +
      (urlParams.toString() ? "?" + urlParams.toString() : "");

    // 페이지 이동 (replace로 히스토리 교체)
    window.location.replace(newUrl);
  }

  /**
   * 필터 섹션 표시/숨김
   */
  function showFilterSection() {
    const filterSection = document.getElementById("ptg-quiz-filter-section");
    if (filterSection) {
      filterSection.style.display = "flex";
    }
  }

  function hideFilterSection() {
    const filterSection = document.getElementById("ptg-quiz-filter-section");
    if (filterSection) {
      filterSection.style.display = "none";
    }
  }

  /**
   * 이벤트 리스너 설정
   */
  function setupEventListeners() {
    const container = document.getElementById("ptg-quiz-container");
    if (!container) return;

    // 이벤트 중복 바인딩 방지
    if (QuizState.eventsBound) {
      return;
    }
    QuizState.eventsBound = true;

    // 툴바 이벤트 설정 (quiz-toolbar.js에서 처리)
    if (window.PTGQuizToolbar && window.PTGQuizToolbar.setupToolbarEvents) {
      window.PTGQuizToolbar.setupToolbarEvents();
    }

    // 정답 확인 버튼
    const btnCheckAnswer = document.getElementById("ptg-btn-check-answer");
    if (
      btnCheckAnswer &&
      !btnCheckAnswer.hasAttribute("data-listener-attached")
    ) {
      btnCheckAnswer.addEventListener("click", checkAnswer);
      btnCheckAnswer.setAttribute("data-listener-attached", "true");
    }

    // 이전 문제 버튼
    const btnPrev = document.getElementById("ptg-btn-prev-question");
    if (btnPrev && !btnPrev.hasAttribute("data-listener-attached")) {
      btnPrev.addEventListener("click", loadPrevQuestion);
      btnPrev.setAttribute("data-listener-attached", "true");
    }

    // 다음 문제 버튼
    const btnNext = document.getElementById("ptg-btn-next-question");
    if (btnNext && !btnNext.hasAttribute("data-listener-attached")) {
      btnNext.addEventListener("click", loadNextQuestion);
      btnNext.setAttribute("data-listener-attached", "true");
    }

    // 관리자 편집 버튼
    setupEditModeEvents();

    // 포기하기 버튼 (progress-section)
    // 포기하기 버튼 (progress-section)
    const oldBtnGiveup = document.getElementById("ptgates-giveup-btn");
    if (oldBtnGiveup) {
      const btnGiveup = oldBtnGiveup.cloneNode(true);
      oldBtnGiveup.parentNode.replaceChild(btnGiveup, oldBtnGiveup);

      btnGiveup.addEventListener("click", function (e) {
        if (e && typeof e.preventDefault === "function") e.preventDefault();
        if (e && typeof e.stopPropagation === "function") e.stopPropagation();

        // 중복 실행 방지
        if (QuizState.giveUpInProgress) return;

        let proceed = true;
        if (typeof window.confirm === "function") {
          proceed = window.confirm(
            "퀴즈를 포기하시겠습니까? 현재까지의 결과가 저장됩니다."
          );
        }
        if (!proceed) {
          return;
        }

        // 확인 후에만 버튼 비활성화
        btnGiveup.disabled = true;
        btnGiveup.style.pointerEvents = "none";

        giveUpQuiz();
      });
      // btnGiveup.setAttribute("data-listener-attached", "true"); // cloneNode로 초기화되므로 속성도 새로 설정 (필요시)
    }

    // 시간관리 tip 버튼 (이벤트 위임 사용 - progress-section이 나중에 표시될 수 있음)
    // 컨테이너에서 이벤트 위임으로 처리
    container.addEventListener("click", function (e) {
      const timeTipBtn = e.target.closest("#ptgates-time-tip-btn");
      if (!timeTipBtn) return;

      e.preventDefault();
      e.stopPropagation();

      // 공통 팝업 유틸리티가 로드되었는지 확인
      if (
        typeof window.PTGTips === "undefined" ||
        typeof window.PTGTips.show !== "function"
      ) {
        console.warn(
          "[PTG Quiz] 공통 팝업 유틸리티가 아직 로드되지 않았습니다."
        );
        // 잠시 후 다시 시도 (최대 3초)
        let retryCount = 0;
        const maxRetries = 30;
        const checkInterval = setInterval(function () {
          retryCount++;
          if (
            typeof window.PTGTips !== "undefined" &&
            typeof window.PTGTips.show === "function"
          ) {
            clearInterval(checkInterval);
            window.PTGTips.show("timer-tip");
          } else if (retryCount >= maxRetries) {
            clearInterval(checkInterval);
            console.error(
              "[PTG Quiz] 공통 팝업 유틸리티를 로드할 수 없습니다."
            );
          }
        }, 100);
        return;
      }

      // 공통 팝업 표시 (내용은 중앙 저장소에서 자동 가져옴)
      window.PTGTips.show("timer-tip");
    });

    // 드로잉 툴바 버튼 이벤트 (닫기 버튼 포함)
    if (
      window.PTGQuizDrawing &&
      window.PTGQuizDrawing.setupDrawingToolbarEvents
    ) {
      window.PTGQuizDrawing.setupDrawingToolbarEvents();
    }

    // 페이지 이탈 시 드로잉 저장 보장
    window.addEventListener("beforeunload", function (e) {
      if (QuizState.canvasContext && QuizState.savingDrawing) {
        // 저장 중이면 페이지 이탈 방지 (동기적으로 저장 불가)
        // 단, 브라우저가 저장 완료를 기다려주지 않으므로
        // 최소한 사용자에게 경고만 표시
        e.preventDefault();
        e.returnValue = "드로잉이 저장 중입니다. 페이지를 떠나시겠습니까?";
        return e.returnValue;
      }
    });

    // 페이지 숨김 시 (탭 전환 등) 드로잉 저장
    document.addEventListener("visibilitychange", function () {
      if (
        document.hidden &&
        QuizState.canvasContext &&
        QuizState.drawingEnabled
      ) {
        // 디바운스 타이머가 있으면 취소하고 즉시 저장
        if (QuizState.drawingSaveTimeout) {
          clearTimeout(QuizState.drawingSaveTimeout);
          QuizState.drawingSaveTimeout = null;
        }
        // 비동기 저장 (완료 보장은 어려움)
        if (
          window.PTGQuizDrawing &&
          window.PTGQuizDrawing.saveDrawingToServer
        ) {
          window.PTGQuizDrawing.saveDrawingToServer();
        }
      }
    });
  }

  // initializePenMenu 함수는 quiz-drawing.js로 이동됨

  // setupDrawingToolbarEvents 함수는 quiz-drawing.js로 이동됨

  /**
   * 키보드 단축키 설정
   */
  function setupKeyboardShortcuts() {
    document.addEventListener("keydown", (e) => {
      // Esc: 패널 닫기
      if (e.key === "Escape") {
        if (QuizState.drawingEnabled) {
          if (window.PTGQuizDrawing && window.PTGQuizDrawing.toggleDrawing) {
            window.PTGQuizDrawing.toggleDrawing(false);
          }
        }
      }
    });
  }

  /**
   * 문제 로드
   */
  async function loadQuestion() {
    // 종료되었거나 실행 상태가 아니면 로드하지 않음
    if (QuizState.terminated || QuizState.appState !== "running") {
      return;
    }
    const seq = ++QuizState.requestSeq;
    // questionId가 0이면 기본값으로 문제 목록 로드 시도
    if (QuizState.questionId === 0) {
      // 기본값으로 5문제 로드
      try {
        const questionIds = await loadQuestionsList({ limit: 5 });

        if (questionIds === null) {
          setState("idle");
          showFilterSection();
          return;
        }

        if (questionIds && questionIds.length > 0) {
          QuizState.questions = questionIds;
          QuizState.currentIndex = 0;
          QuizState.questionId = questionIds[0];
          // 재귀 호출로 첫 번째 문제 로드
          return await loadQuestion();
        } else {
          showError("문제를 찾을 수 없습니다.");
          return;
        }
      } catch (error) {
        console.error("[PTG Quiz] 기본 문제 목록 로드 오류:", error);
        showError("문제 목록을 불러오는 중 오류가 발생했습니다.");
        return;
      }
    }

    // questionId가 여전히 0이면 에러 (안전장치)
    if (QuizState.questionId === 0) {
      console.error(
        "[PTG Quiz] questionId가 0입니다. 기본값 처리에 실패했습니다."
      );
      showError("문제를 불러올 수 없습니다. 페이지를 새로고침해 주세요.");
      return;
    }

    // 테스트 모드: 404/401 시나리오를 시뮬레이션하기 위한 모드
    if (typeof window !== "undefined") {
      if (window.PTGQuizTestMode404) {
        const err = new Error("Not Found");
        err.status = 404;
        throw err;
      }
      if (window.PTGQuizTestMode401) {
        const err = new Error("Unauthorized");
        err.status = 401;
        throw err;
      }
    }
    try {
      // PTGPlatform 확인
      if (typeof PTGPlatform === "undefined") {
        // PTGPlatform이 없으면 직접 fetch 사용
        const restBase = config.restUrl || "/wp-json/ptg-quiz/v1/";
        const fullUrl =
          (restBase.startsWith("http")
            ? restBase
            : window.location.origin + restBase) +
          `questions/${QuizState.questionId}`;

        const fetchResponse = await fetch(fullUrl, {
          method: "GET",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": config.nonce || "",
          },
        });

        if (!fetchResponse.ok) {
          throw new Error(
            `HTTP ${fetchResponse.status}: ${fetchResponse.statusText}`
          );
        }

        const data = await fetchResponse.json();

        if (!data.success || !data.data) {
          const msg = data.message || "문제를 불러올 수 없습니다.";
          const isNotFound =
            /Not Found|404|라우트를 찾을 수 없습니다|URL과 요청한/.test(msg) ||
            /404/.test(msg);
          if (isNotFound) {
            showError(
              "해당 문제가 존재하지 않거나 비활성화되어 있을 수 있습니다. 다른 문제를 시도해 주세요."
            );
            return;
          }
          throw new Error(msg);
        }

        if (
          QuizState.terminated ||
          QuizState.appState !== "running" ||
          seq < QuizState.requestSeq
        )
          return;
        QuizState.questionData = data.data;
        renderQuestion(data.data);

        // progress-section 표시 (단일 문제인 경우)
        if (QuizState.questions.length === 0) {
          showProgressSection();
          updateProgress(1, 1);
        }

        if (
          QuizState.terminated ||
          QuizState.appState !== "running" ||
          seq < QuizState.requestSeq
        )
          return;
        await loadUserState();

        // 문제 로드 완료 후 헤더로 스크롤
        setTimeout(() => {
          if (window.PTGQuizToolbar && window.PTGQuizToolbar.scrollToHeader) {
            window.PTGQuizToolbar.scrollToHeader();
          }
        }, 100);

        return;
      }

      // 플랫폼 헬퍼 사용 - ptg-quiz/v1 엔드포인트 사용
      // 성능 개선: 캐시 활용 및 빠른 응답을 위한 최적화
      const endpoint = `ptg-quiz/v1/questions/${QuizState.questionId}`;

      // 로딩 상태 업데이트 (템플릿의 로딩 페이지 사용)
      const loadingEl = document.querySelector(".ptg-quiz-loading");
      const cardEl = document.getElementById("ptg-quiz-card");
      if (loadingEl) {
        loadingEl.style.display = "flex"; // 로딩 페이지 표시
        // 카드에 로딩 클래스 추가 (하단 border 제거용)
        if (cardEl) {
          cardEl.classList.add("loading-active");
        }
        const loadingText = loadingEl.querySelector("p");
        if (loadingText) {
          loadingText.textContent = "문제 내용을 불러오는 중...";
        }
      }

      const response = await PTGPlatform.get(endpoint);

      if (!response || !response.success || !response.data) {
        const errorMsg = response?.message || "문제를 불러올 수 없습니다.";
        const isNotFound =
          /Not Found|404|라우트를 찾을 수 없습니다|URL과 요청한/.test(
            errorMsg
          ) || /404/.test(errorMsg);
        if (isNotFound) {
          showError(
            "해당 문제가 존재하지 않거나 비활성화되어 있을 수 있습니다. 다른 문제를 시도해 주세요."
          );
          return;
        }
        throw new Error(errorMsg);
      }

      if (
        QuizState.terminated ||
        QuizState.appState !== "running" ||
        seq < QuizState.requestSeq
      )
        return;

      // 로딩 페이지 숨김 (위에서 선언한 loadingEl, cardEl 재사용)
      if (loadingEl) {
        loadingEl.style.display = "none";
        // 카드에서 로딩 클래스 제거
        if (cardEl) {
          cardEl.classList.remove("loading-active");
        }
      }

      QuizState.questionData = response.data;
      renderQuestion(response.data);

      // 문제 로드 완료 후 진행 상태 섹션 표시
      showProgressSection();

      // 사용자 상태 로드
      if (
        QuizState.terminated ||
        QuizState.appState !== "running" ||
        seq < QuizState.requestSeq
      )
        return;
      await loadUserState();

      // 문제 로드 완료 후 헤더로 스크롤
      setTimeout(() => {
        if (window.PTGQuizToolbar && window.PTGQuizToolbar.scrollToHeader) {
          window.PTGQuizToolbar.scrollToHeader();
        }
      }, 100);
    } catch (error) {
      console.error("[PTG Quiz] 문제 로드 오류:", error);
      console.error("[PTG Quiz] 에러 스택:", error.stack);
      const errorMessage =
        (error && error.message) || "알 수 없는 오류가 발생했습니다.";
      // 404/라우트 매핑 문제에 대한 우아한 처리
      if (typeof errorMessage === "string") {
        const isNotFound =
          /404|Not Found|라우트를 찾을 수 없습니다|URL과 요청한/.test(
            errorMessage
          );
        if (isNotFound) {
          if (typeof window.__PTG_ORIG_ALERT === "function") {
            window.__PTG_ORIG_ALERT("다음 문제 기능은 추후 구현됩니다.");
          }
          showError(
            "문제를 불러오는 중 오류가 발생했습니다: 해당 문제가 존재하지 않거나 비활성화되어 있을 수 있습니다. 다른 문제를 시도해 주세요."
          );
          return;
        }
        if (
          errorMessage.includes("404") ||
          errorMessage.includes("라우트를 찾을 수 없습니다")
        ) {
          if (typeof window.__PTG_ORIG_ALERT === "function") {
            window.__PTG_ORIG_ALERT("다음 문제 기능은 추후 구현됩니다.");
          }
          showError(
            "문제를 불러오는 중 오류가 발생했습니다: 해당 문제가 존재하지 않거나 비활성화되어 있을 수 있습니다. 다른 문제를 시도해 주세요."
          );
          return;
        }
      }
      showError("문제를 불러오는 중 오류가 발생했습니다: " + errorMessage);

      // 로딩 표시 제거
      const card = document.getElementById("ptg-quiz-card");
      if (card) {
        card.innerHTML = `
                <div class="ptg-question-content">
                    <p style="color: red;">문제를 불러올 수 없습니다: ${errorMessage}</p>
                    <p style="color: gray; font-size: 12px;">문제 ID: ${QuizState.questionId}</p>
                </div>
            `;
      }
      // 로딩 타임아웃 정리
      if (QuizState.loadingTimeout) {
        clearTimeout(QuizState.loadingTimeout);
        QuizState.loadingTimeout = null;
      }
    }
  }

  /**
   * 사용자 상태 로드
   */
  async function loadUserState() {
    try {
      const response = await PTGPlatform.get(
        `ptg-quiz/v1/questions/${QuizState.questionId}/state`
      );

      if (response && response.success && response.data) {
        QuizState.userState = response.data;

        // 북마크 상태 업데이트
        const btnBookmark = document.querySelector(".ptg-btn-bookmark");
        if (btnBookmark) {
          if (response.data.bookmarked) {
            btnBookmark.classList.add("active");
            const icon = btnBookmark.querySelector(".ptg-icon");
            if (icon) icon.textContent = "★";
          } else {
            btnBookmark.classList.remove("active");
            const icon = btnBookmark.querySelector(".ptg-icon");
            if (icon) icon.textContent = "☆";
          }
        }

        // 복습 상태 업데이트
        const btnReview = document.querySelector(".ptg-btn-review");
        if (btnReview) {
          if (response.data.needs_review) {
            btnReview.classList.add("active");
          } else {
            btnReview.classList.remove("active");
          }
        }

        // 암기카드 상태 업데이트
        const btnFlashcard = document.querySelector(".ptg-btn-flashcard");
        if (btnFlashcard) {
          // 플래시카드 상태도 response.data에서 확인 (또는 QuizState.userState 사용)
          // API가 flashcard 필드를 주는지 확인 필요. 기존 코드에서는 QuizState.userState.flashcard 사용.
          if (response.data.flashcard || QuizState.userState.flashcard) {
            btnFlashcard.classList.add("active");
          } else {
            btnFlashcard.classList.remove("active");
          }
        }

        // 메모 상태 업데이트 (메모 내용이 있으면 활성화)
        const btnNotes = document.querySelector(".ptg-btn-notes");
        if (btnNotes) {
          const hasNote =
            (response.data.note && response.data.note.trim().length > 0) ||
            (QuizState.userState.note &&
              QuizState.userState.note.trim().length > 0);

          if (hasNote) {
            btnNotes.classList.add("active");
            // 메모 텍스트 영역에도 내용 설정
            const notesTextarea = document.getElementById("ptg-notes-textarea");
            if (notesTextarea && !notesTextarea.value.trim()) {
              notesTextarea.value =
                response.data.note || QuizState.userState.note;
            }
          } else {
            btnNotes.classList.remove("active");
          }
        }
      }
    } catch (error) {
      console.error("사용자 상태 로드 오류:", error);
    }
  }

  /**
   * 관리자 편집 모드 이벤트 설정
   */
  function setupEditModeEvents() {
    const editBtn = document.getElementById("ptg-btn-edit-question");
    if (!editBtn) return;

    // 편집 버튼 이벤트 (교체 방식)
    const newBtn = editBtn.cloneNode(true);
    editBtn.parentNode.replaceChild(newBtn, editBtn);

    newBtn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      if (QuizState.isEditing) {
        // 저장 모드
        saveQuestionContent();
      } else {
        // 편집 모드 진입
        toggleEditMode(true);
      }
    });

    // 취소 버튼 이벤트
    const cancelBtn = document.getElementById("ptg-btn-cancel-edit");
    if (cancelBtn) {
      const newCancelBtn = cancelBtn.cloneNode(true);
      cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

      newCancelBtn.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        toggleEditMode(false); // 취소 (저장 없이 종료)
      });
    }
  }

  /**
   * 편집 모드 토글
   */
  function toggleEditMode(enable) {
    const editBtn = document.getElementById("ptg-btn-edit-question");
    const cancelBtn = document.getElementById("ptg-btn-cancel-edit");
    const card = document.getElementById("ptg-quiz-card");

    if (!enable) {
      // 편집 모드 종료 (뷰 모드)
      QuizState.isEditing = false;

      if (card) {
        card.classList.remove("ptg-quiz-editing");
      }

      // 버튼 텍스트/스타일 복구
      if (editBtn) {
        editBtn.textContent = "[편집]";
        editBtn.classList.remove("ptg-btn-primary");
        editBtn.classList.add("ptg-btn-secondary");
        editBtn.disabled = false;
      }

      // 취소 버튼 숨기기
      if (cancelBtn) {
        cancelBtn.style.display = "none";
      }

      // 선택지 영역 다시 표시
      const choices = document.getElementById("ptg-quiz-choices");
      if (choices) {
        choices.style.display = ""; // 원래 스타일로 복구 (보통 block)
      }

      // 뷰 모드로 복원 (저장된 데이터로 다시 렌더링)
      // 이미 저장된 데이터가 QuizState.questionData에 있으므로 그것을 사용
      if (QuizState.questionData) {
        renderQuestion(QuizState.questionData);
      }

      return;
    }

    // 편집 모드 시작
    if (!card) return;

    // 문제 내용 요소
    const contentEl = card.querySelector(".ptg-question-content");
    // 해설 요소
    const explanationEl = document.getElementById("ptg-quiz-explanation");

    // 취소 버튼 표시
    if (cancelBtn) {
      cancelBtn.style.display = "inline-block";
    }

    // 기능: 높이 자동 조절
    const autoResize = (el) => {
      el.style.height = "auto";
      el.style.height = el.scrollHeight + 2 + "px"; // border 고려
    };

    if (!contentEl) {
      PTG_quiz_alert("편집할 문제 내용을 찾을 수 없습니다.");
      return;
    }

    // 선택지 영역 숨기기 (원본 내용에 포함되어 있으므로)
    const choices = document.getElementById("ptg-quiz-choices");
    if (choices) {
      choices.style.display = "none";
    }

    QuizState.isEditing = true;
    card.classList.add("ptg-quiz-editing");

    if (editBtn) {
      editBtn.textContent = "[저장]";
      editBtn.classList.remove("ptg-btn-secondary");
      editBtn.classList.add("ptg-btn-primary");
    }

    // 문제 내용 -> Textarea
    let contentHtml = "";
    // 원본 데이터 사용 (가장 안전) - raw_content가 있으면 우선 사용 (선택지 포함된 원본)
    if (QuizState.questionData && QuizState.questionData.raw_content) {
      contentHtml = QuizState.questionData.raw_content;
    } else if (QuizState.questionData && QuizState.questionData.content) {
      contentHtml = QuizState.questionData.content;
    } else {
      // DOM에서 가져오기 (ID prefix 등 제거 주의)
      contentHtml = contentEl.innerHTML;
      // ID tag removal heuristic could be added here if needed
    }

    const contentTextarea = document.createElement("textarea");
    contentTextarea.className = "ptg-edit-textarea ptg-edit-content";
    contentTextarea.value = contentHtml;
    contentTextarea.placeholder = "문제 내용을 입력하세요 (HTML 지원)";
    contentTextarea.addEventListener("input", function () {
      autoResize(this);
    });

    contentEl.innerHTML = "";
    contentEl.appendChild(contentTextarea);

    // 초기 높이 설정 (DOM에 추가된 후 실행해야 scrollHeight가 정확함)
    setTimeout(() => autoResize(contentTextarea), 0);

    // 해설 -> Textarea
    // 편집 모드에서는 원본 해설(raw_explanation)을 사용하거나, 없으면 explanation 사용
    // DB 내용 그대로 표시하기 위해 원본 데이터 우선 사용
    let explainHtml = "";
    if (QuizState.questionData && QuizState.questionData.raw_explanation) {
      // 원본 해설이 있으면 사용 (변형 없음, DB 내용 그대로)
      explainHtml = QuizState.questionData.raw_explanation;
    } else if (QuizState.questionData && QuizState.questionData.explanation) {
      // 원본이 없으면 explanation 사용 (이미 변형되었을 수 있음)
      explainHtml = QuizState.questionData.explanation;
    } else {
      // 해설이 아직 로드되지 않은 경우 빈 문자열
      explainHtml = "";
    }

    // 해설 영역이 없으면 생성
    let explainContainer = explanationEl;
    if (!explainContainer) {
      explainContainer = document.createElement("div");
      explainContainer.id = "ptg-quiz-explanation";
      explainContainer.className = "ptg-quiz-explanation";
      // 위치: choices 뒤
      const choices = document.getElementById("ptg-quiz-choices");
      if (choices) {
        choices.parentNode.insertBefore(explainContainer, choices.nextSibling);
      } else {
        card.appendChild(explainContainer);
      }
    }
    explainContainer.style.display = "block";

    const explainTextarea = document.createElement("textarea");
    explainTextarea.className = "ptg-edit-textarea ptg-edit-explanation";
    explainTextarea.value = explainHtml;
    explainTextarea.placeholder = "해설을 입력하세요 (HTML 지원)";
    explainTextarea.addEventListener("input", function () {
      autoResize(this);
    });

    explainContainer.innerHTML = "";
    const label = document.createElement("div");
    label.style.fontWeight = "bold";
    label.style.marginBottom = "5px";
    label.textContent = "해설 편집:";
    explainContainer.appendChild(label);
    explainContainer.appendChild(explainTextarea);

    // 초기 높이 설정
    setTimeout(() => autoResize(explainTextarea), 0);
  }

  /**
   * 문제 내용 저장
   */
  async function saveQuestionContent() {
    const card = document.getElementById("ptg-quiz-card");
    if (!card) return;

    const contentTextarea = card.querySelector(".ptg-edit-content");
    const explainTextarea = card.querySelector(".ptg-edit-explanation");

    if (!contentTextarea) return;

    const newContent = contentTextarea.value;
    const newExplanation = explainTextarea ? explainTextarea.value : "";

    const editBtn = document.getElementById("ptg-btn-edit-question");
    if (editBtn) {
      editBtn.disabled = true;
      editBtn.textContent = "저장 중...";
    }

    try {
      const response = await PTGPlatform.post(
        `ptg-quiz/v1/questions/${QuizState.questionId}/content`,
        {
          content: newContent,
          explanation: newExplanation,
        }
      );

      if (response && response.success) {
        // 편집한 내용에서 선택지 제거하여 question_text 생성
        // PHP의 get_question 로직과 동일하게 구현
        let questionText = newContent;
        const optionPattern = /([①-⑳]|\([0-9]+\))\s*/u;
        const matches = [];
        let match;
        const regex = new RegExp(/([①-⑳]|\([0-9]+\))\s*/u, "g");

        // 모든 선택지 시작 위치 찾기
        while ((match = regex.exec(newContent)) !== null) {
          matches.push({
            start: match.index,
            end: match.index + match[0].length,
          });
        }

        // 선택지가 있으면 제거
        if (matches.length > 0) {
          const questionParts = [];
          let lastPos = 0;

          // 각 선택지 범위를 제외한 부분만 추출
          for (let i = 0; i < matches.length; i++) {
            const optionStart = matches[i].start;
            const optionEnd =
              i < matches.length - 1 ? matches[i + 1].start : newContent.length;

            // 선택지 시작 전까지의 텍스트 추가
            if (optionStart > lastPos) {
              questionParts.push(newContent.substring(lastPos, optionStart));
            }

            lastPos = optionEnd;
          }

          // 마지막 선택지 이후 텍스트 추가
          if (lastPos < newContent.length) {
            questionParts.push(newContent.substring(lastPos));
          }

          questionText = questionParts.join("");
        }

        // 데이터 업데이트 (편집한 내용이 새로운 원본이 됨)
        if (QuizState.questionData) {
          QuizState.questionData.content = newContent;
          QuizState.questionData.question_text = questionText; // 선택지 제거된 지문
          QuizState.questionData.explanation = newExplanation;
          QuizState.questionData.raw_content = newContent; // 원본도 업데이트
          QuizState.questionData.raw_explanation = newExplanation; // 원본 해설도 업데이트
        }
        PTG_quiz_alert("수정되었습니다.");

        // 뷰 모드로 복귀 및 재렌더링 (DB 조회 없이 저장한 내용 그대로 표시)
        toggleEditMode(false);
        renderQuestion(QuizState.questionData);
      } else {
        throw new Error(response.message || "저장 실패");
      }
    } catch (e) {
      console.error(e);
      PTG_quiz_alert("저장 중 오류가 발생했습니다: " + (e.message || e));
    } finally {
      if (editBtn) {
        editBtn.disabled = false;
        if (QuizState.isEditing) {
          editBtn.textContent = "[저장]";
        }
      }
    }
  }

  /**
   * 텍스트 정리 함수 (_x000D_ 제거 및 줄바꿈 처리)
   * @param {string} text 원본 텍스트
   * @returns {string} 정리된 텍스트
   */
  function cleanText(text) {
    if (!text) return "";
    // _x000D_ 제거 (Windows 줄바꿈 문자 \r\n의 유니코드 표현)
    let cleaned = String(text)
      .replace(/_x000D_/g, "")
      .replace(/\r\n/g, "\n")
      .replace(/\r/g, "\n");
    // 연속된 줄바꿈 보존 (표시할 때 nl2br 처리)
    // cleaned = cleaned.replace(/\n{2,}/g, "\n");
    return cleaned.trim();
  }

  /**
   * 문제 렌더링 (공통 퀴즈 UI 컴포넌트 사용)
   */
  function renderQuestion(question) {
    // 종료 상태에서 렌더 차단
    if (QuizState.terminated) {
      return;
    }
    // API에서 이미 파싱된 데이터 사용 (question_text, options)
    // 편집 모드가 아닐 때: question_text 사용 (선택지가 제거된 지문)
    // 편집 모드일 때: raw_content 사용 (원본, 선택지 포함)
    // CSS의 white-space: pre-wrap으로 줄바꿈이 자동 표시됨
    let questionText = QuizState.isEditing
      ? question.raw_content || question.content || ""
      : question.question_text || question.content || "";

    // 문제 번호 앞의 빈 스페이스 제거 (앞쪽 공백만 제거, 뒤쪽은 유지)
    // 문제 번호 앞의 빈 스페이스 제거 (앞쪽 공백만 제거, 뒤쪽은 유지)
    questionText = questionText.replace(/^\s+/, "");

    // choicesContainer 변수 선언 (Assignment to constant variable 오류 방지)
    let choicesContainer = null;

    const options = question.options || [];

    // 문제 번호 계산 (연속 퀴즈인 경우) - 먼저 계산해야 함
    const questionNumber =
      QuizState.questions.length > 0 ? QuizState.currentIndex + 1 : null;
    const totalQuestions =
      QuizState.questions.length > 0 ? QuizState.questions.length : null;

    // 문제 텍스트에 이미 문제 번호가 포함되어 있으면 제거 (예: "1. 문제 내용" -> "문제 내용")
    // 연속 퀴즈에서는 우리가 번호를 추가하므로 중복 방지
    if (questionNumber) {
      questionText = questionText.replace(/^\d+\.\s*/, "");
    }

    // 문제 번호 앞 공백 제거를 위해 공백 없이 직접 연결
    // 문제 번호 뒤에는 공백 하나만 추가 (DOM 정리에서 첫 번째 텍스트 노드 공백은 제거됨)
    const questionNumberPrefix = questionNumber
      ? `<strong class="ptg-question-number">${questionNumber}.</strong> `
      : "";
    const questionNumberSuffix = "";

    // 옵션이 없으면 경고
    if (!options || options.length === 0) {
      console.error("[PTG Quiz] 선택지가 없음");
    }

    // 문제 텍스트와 선택지를 하나의 카드 안에 표시 (기출 문제 학습 형식)
    const questionCardEl = document.getElementById("ptg-quiz-card");
    if (questionCardEl) {
      // 로딩 표시 제거 (처음 한 번) - 템플릿의 로딩 페이지만 사용
      const loadingEl = questionCardEl.querySelector(".ptg-quiz-loading");
      if (loadingEl) {
        loadingEl.style.display = "none";
        // 카드에서 로딩 클래스 제거 (하단 border 복원)
        questionCardEl.classList.remove("loading-active");
      }

      // 해설 영역을 보존하기 위해 먼저 확인
      let explanationEl = document.getElementById("ptg-quiz-explanation");
      const explanationExists = explanationEl !== null;

      // 질문 텍스트와 선택지 컨테이너를 함께 구성
      // 해설 영역이 없으면 생성, 있으면 기존 것 유지
      // 로딩 페이지는 유지하고 숨김 처리
      if (!explanationExists) {
        // 로딩 페이지 숨김
        if (loadingEl) {
          loadingEl.style.display = "none";
        }

        // 문제 번호 앞 공백 완전 제거를 위해 텍스트 정리
        const cleanQuestionText = questionText.trim();

        questionCardEl.innerHTML = `<div class="ptg-question-content">${questionNumberPrefix}${cleanQuestionText}${questionNumberSuffix}</div><div class="ptg-quiz-choices" id="ptg-quiz-choices"><!-- 선택지가 동적으로 로드됨 --></div><div class="ptg-quiz-explanation" id="ptg-quiz-explanation" style="display: none;"><!-- 해설이 동적으로 로드됨 --></div>`;

        // 문제 번호 앞 공백 제거 (DOM 정리 - 첫 번째 문제에서 특히 중요)
        const questionContentEl = questionCardEl.querySelector(
          ".ptg-question-content"
        );
        if (questionContentEl) {
          // 첫 번째 자식 노드 확인 및 공백 제거
          let firstChild = questionContentEl.firstChild;
          // <strong> 태그 앞의 텍스트 노드(공백) 제거
          if (firstChild && firstChild.nodeType === Node.TEXT_NODE) {
            const trimmedValue = firstChild.nodeValue.trim();
            if (trimmedValue === "") {
              // 공백만 있으면 제거
              firstChild.remove();
            } else {
              // 앞 공백만 제거
              firstChild.nodeValue = trimmedValue;
            }
          }

          // 모든 텍스트 노드의 앞 공백 제거
          const walker = document.createTreeWalker(
            questionContentEl,
            NodeFilter.SHOW_TEXT,
            null,
            false
          );
          let node;
          while ((node = walker.nextNode())) {
            if (node.nodeValue) {
              // 앞 공백 제거
              node.nodeValue = node.nodeValue.replace(/^\s+/, "");
            }
          }
          // 정규화하여 인접한 텍스트 노드 병합
          questionContentEl.normalize();

          // 최종 확인: 첫 번째 노드가 공백만 있는 텍스트 노드면 제거
          firstChild = questionContentEl.firstChild;
          if (firstChild && firstChild.nodeType === Node.TEXT_NODE) {
            if (/^\s*$/.test(firstChild.nodeValue)) {
              firstChild.remove();
            }
          }
        }
      } else {
        // 해설이 이미 있으면 선택지와 문제 텍스트만 업데이트
        const questionContent = questionCardEl.querySelector(
          ".ptg-question-content"
        );
        choicesContainer = document.getElementById("ptg-quiz-choices");

        // 문제 텍스트 업데이트
        // 문제 번호 앞 공백 완전 제거를 위해 텍스트 정리
        const cleanQuestionText = questionText.trim();

        if (questionContent) {
          questionContent.innerHTML = `${questionNumberPrefix}${cleanQuestionText}${questionNumberSuffix}`;
          // 문제 번호 앞 공백 제거 (DOM 정리)
          let firstChild = questionContent.firstChild;
          // <strong> 태그 앞의 텍스트 노드(공백) 제거
          if (firstChild && firstChild.nodeType === Node.TEXT_NODE) {
            const trimmedValue = firstChild.nodeValue.trim();
            if (trimmedValue === "") {
              // 공백만 있으면 제거
              firstChild.remove();
            } else {
              // 앞 공백만 제거
              firstChild.nodeValue = trimmedValue;
            }
          }

          const walker = document.createTreeWalker(
            questionContent,
            NodeFilter.SHOW_TEXT,
            null,
            false
          );
          let node;
          while ((node = walker.nextNode())) {
            if (node.nodeValue) {
              // 앞 공백 제거
              node.nodeValue = node.nodeValue.replace(/^\s+/, "");
            }
          }
          // 정규화하여 인접한 텍스트 노드 병합
          questionContent.normalize();

          // 최종 확인: 첫 번째 노드가 공백만 있는 텍스트 노드면 제거
          firstChild = questionContent.firstChild;
          if (firstChild && firstChild.nodeType === Node.TEXT_NODE) {
            if (/^\s*$/.test(firstChild.nodeValue)) {
              firstChild.remove();
            }
          }
        } else {
          const newQuestionContent = document.createElement("div");
          newQuestionContent.className = "ptg-question-content";
          newQuestionContent.innerHTML = `${questionNumberPrefix}${cleanQuestionText}${questionNumberSuffix}`;
          // 문제 번호 앞 공백 제거 (DOM 정리)
          let firstChild = newQuestionContent.firstChild;
          // <strong> 태그 앞의 텍스트 노드(공백) 제거
          if (firstChild && firstChild.nodeType === Node.TEXT_NODE) {
            const trimmedValue = firstChild.nodeValue.trim();
            if (trimmedValue === "") {
              // 공백만 있으면 제거
              firstChild.remove();
            } else {
              // 앞 공백만 제거
              firstChild.nodeValue = trimmedValue;
            }
          }

          const walker = document.createTreeWalker(
            newQuestionContent,
            NodeFilter.SHOW_TEXT,
            null,
            false
          );
          let node;
          while ((node = walker.nextNode())) {
            if (node.nodeValue) {
              // 앞 공백 제거
              node.nodeValue = node.nodeValue.replace(/^\s+/, "");
            }
          }
          // 정규화하여 인접한 텍스트 노드 병합
          newQuestionContent.normalize();

          // 최종 확인: 첫 번째 노드가 공백만 있는 텍스트 노드면 제거
          firstChild = newQuestionContent.firstChild;
          if (firstChild && firstChild.nodeType === Node.TEXT_NODE) {
            if (/^\s*$/.test(firstChild.nodeValue)) {
              firstChild.remove();
            }
          }
          if (choicesContainer) {
            choicesContainer.parentNode.insertBefore(
              newQuestionContent,
              choicesContainer
            );
          } else {
            questionCardEl.insertBefore(newQuestionContent, explanationEl);
          }
        }
        // 선택지 컨테이너 업데이트
        if (choicesContainer) {
          choicesContainer.innerHTML = "<!-- 선택지가 동적으로 로드됨 -->";
        } else {
          const newChoicesContainer = document.createElement("div");
          newChoicesContainer.className = "ptg-quiz-choices";
          newChoicesContainer.id = "ptg-quiz-choices";
          newChoicesContainer.innerHTML = "<!-- 선택지가 동적으로 로드됨 -->";
          questionCardEl.insertBefore(newChoicesContainer, explanationEl);
        }

        // 로딩 표시 제거 (해설이 있을 때 추가 확인) - 템플릿의 로딩 페이지만 사용
        const loadingElAfter =
          questionCardEl.querySelector(".ptg-quiz-loading");
        if (loadingElAfter) {
          loadingElAfter.style.display = "none";
          // 카드에서 로딩 클래스 제거 (하단 border 복원)
          questionCardEl.classList.remove("loading-active");
        }
      }

      // 이미지 렌더링 (지문과 선택지 사이)
      const existingImage = questionCardEl.querySelector(".ptg-question-image");
      if (existingImage) {
        existingImage.remove();
      }

      if (question.question_image) {
        const imageContainer = document.createElement("div");
        imageContainer.className = "ptg-question-image";
        imageContainer.style.textAlign = "center";
        imageContainer.style.margin = "20px 0";

        const img = document.createElement("img");
        // 이미지 경로 생성: /wp-content/uploads/ptgates-questions/{year}/{session}/{filename}
        const year = question.exam_year;
        const session = question.exam_session;
        // ptgQuiz.uploadUrl이 없으면 기본 경로 사용 (대부분의 WP 설정에서 표준)
        const baseUrl =
          typeof ptgQuiz !== "undefined" && ptgQuiz.uploadUrl
            ? ptgQuiz.uploadUrl
            : "/wp-content/uploads/ptgates-questions";

        img.src = `${baseUrl}/${year}/${session}/${question.question_image}`;
        img.alt = "문제 이미지";
        img.style.maxWidth = "100%";
        img.style.height = "auto";
        img.style.border = "1px solid #ddd";
        img.style.borderRadius = "4px";

        imageContainer.appendChild(img);

        // 지문(.ptg-question-content) 바로 다음에 삽입
        const contentEl = questionCardEl.querySelector(".ptg-question-content");
        if (contentEl) {
          contentEl.parentNode.insertBefore(
            imageContainer,
            contentEl.nextSibling
          );
        }
      }

      // 공통 퀴즈 UI 컴포넌트 사용
      if (
        typeof PTGQuizUI !== "undefined" &&
        typeof PTGQuizUI.displayOptions === "function"
      ) {
        const container = document.getElementById("ptg-quiz-choices");
        if (container) {
          try {
            PTGQuizUI.displayOptions(options, {
              containerId: "ptg-quiz-choices",
              answerName: "ptg-answer",
            });
          } catch (error) {
            console.error("[PTG Quiz] displayOptions 호출 오류:", error);
            renderChoices(options);
          }
        } else {
          console.error("[PTG Quiz] 컨테이너를 찾을 수 없음: ptg-quiz-choices");
          renderChoices(options);
        }
      } else {
        // 공통 컴포넌트가 없는 경우 기존 방식 사용 (로그 제거)
        renderChoices(options);
      }

      // 최종 확인: 로딩 표시가 남아있으면 숨김 - 템플릿의 로딩 페이지만 사용
      const finalLoadingEl = questionCardEl.querySelector(".ptg-quiz-loading");
      if (finalLoadingEl) {
        finalLoadingEl.style.display = "none";
        // 카드에서 로딩 클래스 제거 (하단 border 복원)
        questionCardEl.classList.remove("loading-active");
      }

      // 문제 카드가 렌더링된 직후 헤더로 스크롤
      setTimeout(() => {
        if (window.PTGQuizToolbar && window.PTGQuizToolbar.scrollToHeader) {
          window.PTGQuizToolbar.scrollToHeader();
        }
      }, 150);
    } else {
      console.error("[PTG Quiz] 문제 카드를 찾을 수 없음: ptg-quiz-card");
    }

    // 버튼 상태 초기화: 모든 버튼 표시 (항상 활성화)
    const btnCheck = document.getElementById("ptg-btn-check-answer");
    if (btnCheck) {
      if (QuizState.terminated) {
        btnCheck.style.display = "none";
      } else {
        // [NEW] Mock Exam 모드에서는 정답 확인 버튼 숨김
        if (QuizState.mode === "mock_exam" || QuizState.mode === "exam") {
          btnCheck.style.display = "none";
        } else {
          btnCheck.style.display = "inline-block";
        }
        btnCheck.disabled = false; // 항상 활성화
        // 버튼 텍스트 설정 (다른 버튼들과 동일한 스타일)
        if (!btnCheck.textContent || btnCheck.textContent.trim() === "") {
          btnCheck.textContent = "정답 확인(해설)";
        }
      }
    }

    const btnPrev = document.getElementById("ptg-btn-prev-question");
    if (btnPrev) {
      if (QuizState.terminated) {
        btnPrev.style.display = "none";
      } else {
        btnPrev.style.display = "inline-block";
        // 첫 번째 문제인 경우 비활성화
        btnPrev.disabled = QuizState.currentIndex <= 0;
      }
    }

    const btnNext = document.getElementById("ptg-btn-next-question");
    if (btnNext) {
      if (QuizState.terminated) {
        btnNext.style.display = "none";
      } else {
        btnNext.style.display = "inline-block";
        btnNext.disabled = false; // 항상 활성화
        btnNext.textContent = "다음 문제";
        btnNext.classList.remove("ptg-btn-finish");
      }
    }

    // 임시 답안 초기화
    QuizState.tempAnswer = null;

    // 포기하기 버튼 활성화 (안전장치)
    const btnGiveup = document.getElementById("ptgates-giveup-btn");
    if (btnGiveup) {
      btnGiveup.disabled = false;
      btnGiveup.style.pointerEvents = "auto";
      btnGiveup.removeAttribute("disabled");
    }

    // 문제 상태 초기화
    QuizState.isAnswered = false;
    QuizState.userAnswer = "";

    // 해설 영역 초기화 (새 문제 로드 시 해설 숨김)
    const explanationEl = document.getElementById("ptg-quiz-explanation");
    if (explanationEl) {
      explanationEl.style.display = "none";
      explanationEl.innerHTML = "<!-- 해설이 동적으로 로드됨 -->";
    }

    // 진행률 업데이트 및 progress-section 표시
    if (QuizState.terminated) {
      hideProgressSection();
      return;
    }
    if (questionNumber && totalQuestions) {
      updateProgress(questionNumber, totalQuestions);
      // progress-section 표시 (문제가 로드된 후)
      showProgressSection();
    } else if (QuizState.questions.length > 0) {
      // 연속 퀴즈인 경우
      const current = QuizState.currentIndex + 1;
      const total = QuizState.questions.length;
      updateProgress(current, total);
      showProgressSection();
    } else {
      // 단일 문제인 경우에도 progress-section 표시
      showProgressSection();
      // 단일 문제이므로 1/1로 표시
      updateProgress(1, 1);
    }

    // 툴바 상태 업데이트 (1100 플러그인과 통합)
    if (
      typeof window.PTGStudyToolbar !== "undefined" &&
      typeof window.PTGStudyToolbar.updateToolbarStatus === "function"
    ) {
      window.PTGStudyToolbar.updateToolbarStatus(QuizState.questionId);
    }

    // 버튼에 data-question-id 속성 추가 (study-toolbar.js 호환성)
    if (QuizState.questionId) {
      const toolbarButtons = document.querySelectorAll(
        ".ptg-quiz-toolbar .ptg-btn-icon"
      );
      toolbarButtons.forEach((btn) => {
        btn.setAttribute("data-question-id", QuizState.questionId);
      });
    }

    // 암기카드 버튼 강제 표시 및 순서 보장 (문제 렌더링 시 재확인)
    setTimeout(function () {
      if (typeof ensureFlashcardButton === "function") {
        ensureFlashcardButton();
      }
    }, 200);
  }

  /**
   * 문제 본문에서 선택지 파싱 (ptgates-engine 스타일)
   *
   * @param {string} content 문제 본문 (선택지 포함)
   * @returns {object} { questionText: string, options: array }
   */
  function parseQuestionContent(content) {
    if (!content || typeof content !== "string") {
      console.error("[PTG Quiz] 파싱 오류: content가 유효하지 않음", content);
      return { questionText: content || "", options: [] };
    }

    // _x000D_ 제거 및 정규화
    const cleanedContent = cleanText(content);

    const options = [];
    let questionText = cleanedContent;

    // HTML 태그 제거 (있는 경우)
    const textContent = cleanedContent.replace(/<[^>]*>/g, "");

    // 먼저 모든 원형 숫자(①~⑳)의 위치 찾기 (괄호 숫자는 선택지 내용이므로 제외)
    // 공백 제거: 원형 숫자 뒤의 공백을 포함하지 않도록 수정
    const numberPattern = /[①-⑳]/gu;
    const numberMatches = [];
    let numMatch;

    // 정규식 리셋
    numberPattern.lastIndex = 0;

    while ((numMatch = numberPattern.exec(textContent)) !== null) {
      // 원형 숫자 뒤의 공백 제거 (빈 공간 문제 해결)
      const afterNumber = textContent.substring(numMatch.index + 1);
      const spaceMatch = afterNumber.match(/^\s+/);
      const spaceLength = spaceMatch ? spaceMatch[0].length : 0;

      numberMatches.push({
        number: numMatch[0],
        position: numMatch.index,
        spaceAfter: spaceLength, // 뒤의 공백 길이 저장
      });
    }

    if (numberMatches.length > 0) {
      const optionRanges = [];

      // 각 원형 숫자의 시작/끝 위치 저장
      numberMatches.forEach((numMatch, idx) => {
        const startPos = numMatch.position;

        // 다음 원형 숫자의 위치 찾기
        let endPos = textContent.length;
        if (numberMatches[idx + 1]) {
          endPos = numberMatches[idx + 1].position;
        }

        // 옵션 텍스트 추출 (원형 숫자 포함)
        let optionText = textContent.substring(startPos, endPos).trim();
        // 원형 숫자 뒤의 불필요한 공백 제거 (빈 공간 문제 해결)
        if (numberMatches[idx].spaceAfter > 0) {
          // 원형 숫자 뒤의 첫 공백만 유지하고 나머지 제거
          optionText = optionText.replace(/^([①-⑳])\s+/, "$1 ");
        }
        // 연속된 줄바꿈(3개 이상)만 공백으로 정리, 단일/이중 줄바꿈은 유지 (선택지 줄바꿈 표시)
        optionText = optionText.replace(/\n{3,}/g, "\n\n");

        if (optionText) {
          options.push(optionText);
          optionRanges.push({ start: startPos, end: endPos });
        }
      });

      // 문제 본문에서 옵션 부분 제거
      if (optionRanges.length > 0) {
        const questionParts = [];
        let lastPos = 0;

        // 옵션이 없는 부분들을 조합하여 문제 본문 재구성
        optionRanges.forEach((range, idx) => {
          if (range.start > lastPos) {
            const part = textContent.substring(lastPos, range.start).trim();
            if (part) {
              questionParts.push(part);
            }
          }
          lastPos = range.end;
        });

        // 마지막 옵션 이후 텍스트 추가
        if (lastPos < textContent.length) {
          const part = textContent.substring(lastPos).trim();
          if (part) {
            questionParts.push(part);
          }
        }

        questionText = questionParts.join(" ").trim();
      }
    }

    return {
      questionText: questionText || content,
      options: options,
    };
  }

  /**
   * 선택지 렌더링 (ptgates-engine 스타일)
   */
  function renderChoices(options) {
    const choicesContainer = document.getElementById("ptg-quiz-choices");
    if (!choicesContainer) {
      console.error("[PTG Quiz] 선택지 컨테이너를 찾을 수 없음");
      return;
    }

    // 컨테이너 초기화 및 스타일 설정
    choicesContainer.innerHTML = "";
    choicesContainer.style.display = "block";
    choicesContainer.style.width = "100%";
    choicesContainer.style.marginTop = "0";
    choicesContainer.style.padding = "10px 12px";

    if (!options || options.length === 0) {
      // 선택지가 없는 경우 (주관식)
      const input = document.createElement("input");
      input.type = "text";
      input.className = "ptg-text-answer";
      input.id = "ptg-user-answer";
      input.placeholder = "답을 입력하세요";
      choicesContainer.appendChild(input);
    } else {
      // 객관식 선택지 렌더링
      options.forEach((option, index) => {
        const label = document.createElement("label");
        label.className = "ptg-quiz-ui-option-label";
        const optionId = `ptg-choice-${index}`;
        label.setAttribute("for", optionId);

        // 라벨 스타일
        label.style.display = "flex";
        label.style.flexDirection = "row";
        label.style.width = "100%";
        label.style.marginBottom = "0";
        label.style.padding = "4px 8px";
        label.style.alignItems = "flex-start";
        label.style.border = "none";
        label.style.borderRadius = "0";
        label.style.cursor = "pointer";
        label.style.background = "transparent";
        label.style.boxSizing = "border-box";

        // 라디오 버튼 생성
        const radio = document.createElement("input");
        radio.type = "radio";
        radio.name = "ptg-answer";
        radio.value = option; // 전체 옵션 텍스트를 value로 사용 (원형 숫자 포함)
        radio.id = optionId;
        radio.className = "ptg-quiz-ui-radio-input";

        // 라디오 버튼 스타일
        radio.style.width = "20px";
        radio.style.height = "20px";
        radio.style.minWidth = "20px";
        radio.style.minHeight = "20px";
        radio.style.marginRight = "8px";
        radio.style.marginTop = "2px";
        radio.style.cursor = "pointer";
        radio.style.flexShrink = "0";

        // 옵션 텍스트 (원형 숫자 포함)
        const text = document.createElement("span");
        text.className = "ptg-quiz-ui-option-text";

        // 옵션 텍스트 설정 (확실하게 표시되도록)
        let optionText = String(option || "").trim();
        // 줄바꿈 유지 (표시 시 줄바꿈 그대로 표시)

        // textContent 사용 (HTML 이스케이프 자동 처리, CSS white-space: pre-line으로 줄바꿈 표시)
        text.textContent = optionText;

        // 텍스트 스타일 강제 적용
        text.style.display = "block";
        text.style.flex = "1";
        text.style.whiteSpace = "pre-line"; // 줄바꿈 유지
        text.style.wordWrap = "break-word";
        text.style.overflowWrap = "break-word";
        text.style.lineHeight = "1.3";
        text.style.color = "#333";
        text.style.width = "calc(100% - 40px)";
        text.style.visibility = "visible";
        text.style.opacity = "1";

        // 라벨 클릭 이벤트
        label.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          if (radio.disabled) {
            return;
          }
          if (e.target !== radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event("change", { bubbles: true }));
          }
        });

        // 라디오 버튼 변경 이벤트
        radio.addEventListener("change", function (e) {
          QuizState.userAnswer = e.target.value;
        });

        // 요소 추가 (순서 중요)
        label.appendChild(radio);
        label.appendChild(text);
        choicesContainer.appendChild(label);

        // 생성 후 즉시 확인 (다른 스크립트 간섭 방지)
        setTimeout(() => {
          const actualText = text.textContent || text.innerText || "";
          if (!actualText || actualText.trim() === "") {
            // 다시 설정 시도 (에러 로그 제거)
            text.textContent = optionText;
            text.innerText = optionText;
          }
        }, 100);
      });

      // 선택지 렌더링 완료 후 헤더로 스크롤
      setTimeout(() => {
        if (window.PTGQuizToolbar && window.PTGQuizToolbar.scrollToHeader) {
          window.PTGQuizToolbar.scrollToHeader();
        }
      }, 200);
    }
  }

  /**
   * 타이머 시작
   */
  function startTimer() {
    if (QuizState.timerInterval) {
      clearInterval(QuizState.timerInterval);
    }

    // 퀴즈 시작 시간 설정 (처음 시작할 때만)
    if (!QuizState.startTime) {
      QuizState.startTime = Date.now();
    }

    QuizState.timerInterval = setInterval(() => {
      QuizState.timerSeconds--;

      if (QuizState.timerSeconds <= 0) {
        clearInterval(QuizState.timerInterval);
        timerExpired();
        return;
      }

      updateTimerDisplay();
    }, 1000);

    updateTimerDisplay();
  }

  /**
   * 타이머 표시 업데이트
   */
  function updateTimerDisplay() {
    // 기존 타이머 표시 (호환성 유지)
    const display = document.getElementById("ptg-timer-display");
    if (display) {
      const minutes = Math.floor(QuizState.timerSeconds / 60);
      const seconds = QuizState.timerSeconds % 60;
      display.textContent = `${String(minutes).padStart(2, "0")}:${String(
        seconds
      ).padStart(2, "0")}`;
    }

    // progress-section의 타이머 업데이트
    const progressTimer = document.getElementById("ptgates-timer");
    if (progressTimer) {
      const minutes = Math.floor(QuizState.timerSeconds / 60);
      const seconds = QuizState.timerSeconds % 60;
      progressTimer.textContent = `${String(minutes).padStart(2, "0")}:${String(
        seconds
      ).padStart(2, "0")}`;
    }
  }

  /**
   * 타이머 만료
   */
  function timerExpired() {
    if (QuizState.terminated) return;
    showError("시간이 종료되었습니다.");
    // 자동 제출 또는 알림
  }

  // 툴바 관련 함수는 quiz-toolbar.js로 이동됨

  /**
   * 암기카드 모달 표시 (레거시 호환용 - quiz-toolbar.js로 이동됨)
   */
  async function showFlashcardModalLegacy() {
    const questionId = QuizState.questionId;
    if (!questionId) {
      return;
    }

    // Helper function to convert HTML to text while preserving line breaks
    function htmlToText($element) {
      const clone = $element.cloneNode(true);
      // Replace <br> with newline
      clone.querySelectorAll("br").forEach((br) => {
        br.replaceWith("\n");
      });
      // Get text content
      return (clone.textContent || clone.innerText || "").trim();
    }

    let frontText = "";
    let backText = "";

    // 먼저 DB에서 저장된 암기카드 데이터 조회
    let hasDbData = false;
    try {
      const params = {
        source_type: "question",
        source_id: questionId,
      };

      const cardsResponse = await PTGPlatform.get("ptg-flash/v1/cards", params);

      // WordPress REST API는 배열을 직접 반환하거나 data 속성에 포함
      const cards = Array.isArray(cardsResponse)
        ? cardsResponse
        : cardsResponse.data || [];

      // 첫 번째 카드 사용 (source_type, source_id로 필터링됨)
      const existingCard =
        Array.isArray(cards) && cards.length > 0 ? cards[0] : null;

      if (existingCard) {
        // front_custom, back_custom이 존재하고 빈 문자열이 아닌지 확인
        const frontValue = existingCard.front_custom;
        const backValue = existingCard.back_custom;

        const hasFront =
          frontValue !== null &&
          frontValue !== undefined &&
          String(frontValue).trim() !== "";
        const hasBack =
          backValue !== null &&
          backValue !== undefined &&
          String(backValue).trim() !== "";

        // 둘 중 하나라도 값이 있으면 DB 데이터 사용
        if (hasFront || hasBack) {
          frontText = frontValue ? String(frontValue) : "";
          backText = backValue ? String(backValue) : "";
          hasDbData = true;
        }
      }
    } catch (error) {
      // DB 조회 실패 시 DOM에서 추출로 진행
      console.error("[PTG Quiz] 암기카드 DB 조회 실패:", error);
    }

    // DB 데이터가 없으면 QuizState.questionData에서 추출
    if (!hasDbData) {
      // 앞면: 지문과 선택지를 QuizState.questionData에서 가져오기
      if (QuizState.questionData) {
        // 지문 추가 (질문 시작 부분에 ID 추가)
        const questionIdPrefix = "(id-" + QuizState.questionId + ") ";
        if (QuizState.questionData.question_text) {
          frontText =
            questionIdPrefix + QuizState.questionData.question_text.trim();
        } else if (QuizState.questionData.content) {
          frontText = questionIdPrefix + QuizState.questionData.content.trim();
        }

        // 선택지 추가
        if (
          QuizState.questionData.options &&
          Array.isArray(QuizState.questionData.options) &&
          QuizState.questionData.options.length > 0
        ) {
          QuizState.questionData.options.forEach((option, index) => {
            let optionText = String(option || "").trim();
            if (optionText) {
              // 이미 원형 숫자가 있으면 제거 (①~⑳ 패턴 제거)
              optionText = optionText.replace(/^[①-⑳]\s*/, "");

              // 선택지 형식: ① 선택지 내용
              const optionNumber = String.fromCharCode(0x2460 + index); // 원형 숫자 ①, ②, ③...
              frontText += "\n" + optionNumber + " " + optionText;
            }
          });
        }

        // 뒷면: 정답과 해설
        // 정답 추가
        if (QuizState.questionData.answer) {
          backText = "정답: " + QuizState.questionData.answer;
        }

        // 해설 추가
        if (QuizState.questionData.explanation) {
          if (backText) {
            backText += "\n\n";
          }
          backText += htmlToTextForFlashcard(
            QuizState.questionData.explanation
          );
        }
      } else {
        // QuizState.questionData가 없으면 DOM에서 추출 (fallback)
        const card = document.getElementById("ptg-quiz-card");

        if (card) {
          // Get question text (질문 시작 부분에 ID 추가)
          const questionEl = card.querySelector(
            ".ptg-question-text, .ptg-question-content"
          );
          if (questionEl) {
            const questionIdPrefix = "(id-" + QuizState.questionId + ") ";
            frontText = questionIdPrefix + htmlToText(questionEl);
          }

          // Get question options/choices (실제 렌더링된 클래스 사용)
          const choicesEl = card.querySelector(".ptg-quiz-choices");
          if (choicesEl) {
            const choices = choicesEl.querySelectorAll(
              ".ptg-quiz-ui-option-label, .ptg-quiz-choice, .ptg-choice-item"
            );
            choices.forEach((choice) => {
              // 선택지 텍스트 추출
              const optionText = choice.querySelector(
                ".ptg-quiz-ui-option-text"
              );
              if (optionText) {
                const choiceText = htmlToText(optionText);
                if (choiceText) {
                  frontText += "\n" + choiceText.trim();
                }
              } else {
                // fallback: 직접 텍스트 추출
                const choiceText = htmlToText(choice);
                if (choiceText) {
                  frontText += "\n" + choiceText.trim();
                }
              }
            });
          }
        }

        // 뒷면: DOM에서 추출 (fallback)
        const explanation = document.getElementById("ptg-quiz-explanation");

        if (explanation && explanation.style.display !== "none") {
          // Extract answer and explanation
          const explanationContent = explanation.querySelector(
            ".ptg-explanation-content"
          );
          let extractedText = "";
          if (explanationContent) {
            extractedText = htmlToText(explanationContent);
          } else {
            extractedText = htmlToText(explanation);
          }
          // 뒷면에서 ID 패턴 제거 (id-xxxx 형식)
          backText = extractedText.replace(/\s*\(id-\d+\)\s*/g, "").trim();
        }
      }
    }

    // Create modal if it doesn't exist
    let modal = document.getElementById("ptg-quiz-flashcard-modal");
    if (!modal) {
      const modalHtml =
        '<div id="ptg-quiz-flashcard-modal" class="ptg-modal" style="display: none;">' +
        '<div class="ptg-modal-overlay"></div>' +
        '<div class="ptg-modal-content">' +
        '<div class="ptg-modal-header">' +
        "<h3>암기카드 만들기</h3>" +
        '<button class="ptg-modal-close">&times;</button>' +
        "</div>" +
        '<div class="ptg-modal-body">' +
        '<div class="form-group">' +
        "<label>앞면 (질문)</label>" +
        '<textarea id="ptg-flashcard-front" rows="4"></textarea>' +
        "</div>" +
        '<div class="form-group">' +
        "<label>뒷면 (답변/해설)</label>" +
        '<textarea id="ptg-flashcard-back" rows="4"></textarea>' +
        "</div>" +
        "</div>" +
        '<div class="ptg-modal-footer">' +
        '<div class="ptg-flashcard-status" style="flex: 1; font-size: 14px; color: #666;"></div>' +
        '<button class="ptg-btn ptg-btn-secondary ptg-modal-cancel">취소</button>' +
        '<button class="ptg-btn ptg-btn-primary ptg-flashcard-save">저장</button>' +
        "</div>" +
        "</div>" +
        "</div>";

      const tempDiv = document.createElement("div");
      tempDiv.innerHTML = modalHtml;
      modal = tempDiv.firstElementChild;
      document.body.appendChild(modal);

      // Close handler
      modal.addEventListener("click", function (e) {
        if (
          e.target.classList.contains("ptg-modal-close") ||
          e.target.classList.contains("ptg-modal-cancel") ||
          e.target.classList.contains("ptg-modal-overlay")
        ) {
          modal.style.display = "none";
          const statusEl = modal.querySelector(".ptg-flashcard-status");
          if (statusEl) statusEl.textContent = "";
        }
      });

      // Save handler (bound once)
      modal.addEventListener("click", function (e) {
        if (e.target.classList.contains("ptg-flashcard-save")) {
          e.preventDefault();
          if (window.PTGQuizToolbar && window.PTGQuizToolbar.saveFlashcard) {
            window.PTGQuizToolbar.saveFlashcard();
          }
        }
      });
    }

    // Fill modal fields
    const frontTextarea = document.getElementById("ptg-flashcard-front");
    const backTextarea = document.getElementById("ptg-flashcard-back");
    const statusEl = modal.querySelector(".ptg-flashcard-status");

    if (frontTextarea) {
      frontTextarea.value = frontText ? frontText.trim() : "";
    }

    if (backTextarea) {
      backTextarea.value = backText ? backText.trim() : "";
    }

    if (statusEl) {
      statusEl.textContent = "";
      statusEl.style.color = "#666";
    }

    // Set question ID
    modal.setAttribute("data-question-id", questionId);

    // Show modal
    modal.style.display = "flex";
    modal.style.alignItems = "center";
    modal.style.justifyContent = "center";

    // 모달 표시 후 포커스
    setTimeout(() => {
      if (frontTextarea) frontTextarea.focus();
    }, 100);
  }

  // toggleDrawing 함수는 quiz-drawing.js로 이동됨

  // initDrawingCanvas, setupDrawingEvents, handleMouseDown, handleMouseMove, handleMouseUp, handleTouchStart, handleTouchMove, handleTouchEnd, saveHistoryState, loadDrawingHistory, loadDrawingFromServer, saveEmptyDrawingToServer, saveDrawingToServer, debouncedSaveDrawing, setDrawingTool, setPenColor, setPenAlpha, setPenWidth, undoDrawing, redoDrawing, clearDrawing 함수들은 quiz-drawing.js로 이동됨

  /**
   * 정답 확인
   */
  /**
   * 문제 풀이 버튼 클릭 시: 해설 표시 (DB 저장 없음)
   * 선택지 선택 시: 정/오답 표시 + 해설 표시
   * 선택지 미선택 시: 해설만 표시
   */
  async function checkAnswer() {
    if (QuizState.terminated) {
      return;
    }

    // 공통 컴포넌트에서 답안 가져오기
    let userAnswer = "";
    if (typeof PTGQuizUI !== "undefined") {
      userAnswer = PTGQuizUI.getSelectedAnswer({
        answerName: "ptg-answer",
        textAnswerId: "ptg-user-answer",
      });
    } else {
      userAnswer = QuizState.userAnswer;
    }
    // DOM 기반 보강 체크 (라디오 직접 확인)
    if (!userAnswer) {
      try {
        const checked = document.querySelector(
          '#ptg-quiz-choices input[type="radio"]:checked'
        );
        if (checked && checked.value) {
          userAnswer = checked.value;
        }
      } catch (e) {
        // ignore
      }
    }

    // 문자열 정리
    let normalizedUserAnswer = userAnswer;
    if (typeof normalizedUserAnswer === "string") {
      normalizedUserAnswer = normalizedUserAnswer.trim();
    }

    // 선택지 선택 여부 확인
    const hasAnswer =
      normalizedUserAnswer &&
      normalizedUserAnswer !== "null" &&
      normalizedUserAnswer !== "undefined";

    // 드로잉 저장 (문제 풀이 클릭 시)
    if (QuizState.canvasContext) {
      if (QuizState.drawingSaveTimeout) {
        clearTimeout(QuizState.drawingSaveTimeout);
        QuizState.drawingSaveTimeout = null;
      }
      if (window.PTGQuizDrawing && window.PTGQuizDrawing.saveDrawingToServer) {
        await window.PTGQuizDrawing.saveDrawingToServer();
      }
    }

    // 항상 정답 확인 및 표시 (선택 여부와 관계없이)
    try {
      // 정답 정보를 가져오기 위한 API 호출
      const response = await PTGPlatform.get(
        `ptg-quiz/v1/questions/${QuizState.questionId}`
      );

      if (response && response.data) {
        // API는 'answer' 필드로 정답을 반환함
        const correctAnswer =
          response.data.answer || response.data.correct_answer;

        if (!correctAnswer) {
          console.error("정답 정보를 가져올 수 없습니다.");
          return;
        }

        if (hasAnswer) {
          // 선택지 선택 시: 정/오답 표시
          const normalizedAnswer = circleToNumber(normalizedUserAnswer);
          const isCorrect =
            circleToNumber(String(correctAnswer || "")) === normalizedAnswer;

          // 정답/오답 표시
          if (typeof PTGQuizUI !== "undefined") {
            PTGQuizUI.showAnswerFeedback(
              "ptg-quiz-choices",
              correctAnswer,
              userAnswer
            );
          }
          // 보조 하이라이트 및 비활성화 (강제로 정답 표시)
          try {
            applyAnswerHighlight(correctAnswer, userAnswer);
          } catch (e) {
            console.error("정답 하이라이트 오류:", e);
          }

          // QuizState에 임시 저장 (다음 문제 클릭 시 사용)
          QuizState.tempAnswer = {
            userAnswer: userAnswer,
            normalizedAnswer: normalizedAnswer,
            isCorrect: isCorrect,
            correctAnswer: correctAnswer,
          };
        } else {
          // 선택지 미선택 시: 정답만 표시 (사용자 답안 없음)
          if (typeof PTGQuizUI !== "undefined") {
            PTGQuizUI.showAnswerFeedback(
              "ptg-quiz-choices",
              correctAnswer,
              "" // 사용자 답안 없음
            );
          }
          // 정답 하이라이트만 표시 (강제로 정답 표시)
          try {
            applyAnswerHighlight(correctAnswer, "");
          } catch (e) {
            console.error("정답 하이라이트 오류:", e);
          }

          // 임시 답안 초기화
          QuizState.tempAnswer = null;
        }
      }
    } catch (error) {
      console.error("정답 확인 오류:", error);
      // 오류 발생 시에도 임시 답안 초기화
      QuizState.tempAnswer = null;
    }

    // 해설 표시 (선택 여부와 관계없이)
    await showExplanation();

    // 퀴즈 카드로 스크롤 (헤더 대신)
    const card = document.getElementById("ptg-quiz-card");
    if (card) {
      setTimeout(() => {
        const cardRect = card.getBoundingClientRect();
        const scrollTop =
          window.pageYOffset || document.documentElement.scrollTop;
        const cardTop = cardRect.top + scrollTop;
        const adminBar = document.getElementById("wpadminbar");
        const adminBarHeight = adminBar ? adminBar.offsetHeight : 0;

        window.scrollTo({
          top: cardTop - adminBarHeight - 20, // 20px 여유
          behavior: "smooth",
        });
      }, 200);
    }

    // 버튼 상태 변경: "[정답 확인]"은 항상 표시, "[이전 문제]", "[다음 문제]"도 표시
    const btnCheck = document.getElementById("ptg-btn-check-answer");
    const btnPrev = document.getElementById("ptg-btn-prev-question");
    const btnNext = document.getElementById("ptg-btn-next-question");

    if (btnCheck) {
      btnCheck.style.display = "inline-block";
      btnCheck.disabled = false; // 항상 활성화
      // 버튼 텍스트를 한 줄로 설정
      if (!btnCheck.textContent || btnCheck.textContent.trim() === "") {
        btnCheck.textContent = "정답확인(풀이)";
      }
    }

    if (btnPrev) {
      btnPrev.style.display = "inline-block";
      // 첫 번째 문제인 경우 비활성화
      btnPrev.disabled = QuizState.currentIndex <= 0;
    }

    if (btnNext) {
      btnNext.style.display = "inline-block";
      btnNext.disabled = false; // 항상 활성화
      // 마지막 문제인 경우 버튼 텍스트를 '종료'로 변경
      if (QuizState.currentIndex >= QuizState.questions.length - 1) {
        btnNext.textContent = "종료";
        btnNext.classList.add("ptg-btn-finish");
      } else {
        btnNext.textContent = "다음 문제";
        btnNext.classList.remove("ptg-btn-finish");
      }
    }

    // 답안 제출 후 드로잉 로드 (해설이 표시된 후)
    if (QuizState.canvasContext) {
      if (
        QuizState.drawingEnabled &&
        window.PTGQuizDrawing &&
        window.PTGQuizDrawing.initDrawingCanvas
      ) {
        window.PTGQuizDrawing.initDrawingCanvas();
      }

      const canvas = document.getElementById("ptg-drawing-canvas");
      if (canvas) {
        QuizState.canvasContext.clearRect(0, 0, canvas.width, canvas.height);
      }
      QuizState.drawingHistory = [];
      QuizState.drawingHistoryIndex = -1;
      QuizState.strokes = [];
      QuizState.nextStrokeId = 1;
      QuizState.currentStrokeId = null;
      if (
        window.PTGQuizDrawing &&
        window.PTGQuizDrawing.loadDrawingFromServer
      ) {
        await window.PTGQuizDrawing.loadDrawingFromServer();

        if (
          QuizState.drawingEnabled &&
          window.PTGQuizDrawing &&
          window.PTGQuizDrawing.initDrawingCanvas
        ) {
          setTimeout(() => {
            window.PTGQuizDrawing.initDrawingCanvas();
          }, 100);
        }
      }
    }
  }

  // 보조 하이라이트 및 입력 비활성화 (클래스/스타일 충돌 대비)
  function applyAnswerHighlight(correctAnswer, userAnswer) {
    const container = document.getElementById("ptg-quiz-choices");
    if (!container) return;
    const correctNum = circleToNumber(String(correctAnswer || ""));
    const userNum = circleToNumber(String(userAnswer || ""));
    // .ptg-quiz-ui-option-label 클래스를 가진 요소를 찾아야 함
    const labels = container.querySelectorAll(
      ".ptg-quiz-ui-option-label, label"
    );
    labels.forEach((label) => {
      const radio = label.querySelector('input[type="radio"]');
      if (!radio) return;
      const optionNum = circleToNumber(String(radio.value || ""));
      label.classList.remove("ptg-quiz-ui-correct-answer");
      label.classList.remove("ptg-quiz-ui-incorrect-answer");
      // 정답 표시 (항상) - 정답 번호와 옵션 번호가 일치하면 표시
      if (optionNum && correctNum && optionNum === correctNum) {
        label.classList.add("ptg-quiz-ui-correct-answer");
        try {
          label.style.setProperty("background", "#d4edda", "important");
          label.style.setProperty("border", "2px solid #28a745", "important");
        } catch (e) {}
      }
      // 사용자 답안이 있고 정답이 아닌 경우 오답 표시
      if (userNum && optionNum === userNum && optionNum !== correctNum) {
        label.classList.add("ptg-quiz-ui-incorrect-answer");
        try {
          label.style.setProperty("background", "#f8d7da", "important");
        } catch (e) {}
      }
      try {
        radio.disabled = true;
      } catch (e) {}
      try {
        label.style.setProperty("pointer-events", "none", "important");
      } catch (e) {}
    });
  }

  /**
   * 원형 숫자를 일반 숫자로 변환하는 함수
   */
  function circleToNumber(str) {
    const circleMap = {
      "①": "1",
      "②": "2",
      "③": "3",
      "④": "4",
      "⑤": "5",
      "⑥": "6",
      "⑦": "7",
      "⑧": "8",
      "⑨": "9",
      "⑩": "10",
    };
    // 옵션 텍스트에서 원형 숫자 추출
    for (const [circle, num] of Object.entries(circleMap)) {
      if (str.includes(circle)) {
        return num;
      }
    }
    // 원형 숫자가 없으면 숫자만 추출
    const numMatch = str.match(/^\d+/);
    return numMatch ? numMatch[0] : str.trim();
  }

  /**
   * 결과 표시
   */
  function showResult(isCorrect, correctAnswer) {
    const choicesContainer = document.getElementById("ptg-quiz-choices");
    if (!choicesContainer) return;

    // 정답 번호 추출 (원형 숫자 또는 일반 숫자)
    const correctNum = circleToNumber(correctAnswer);

    // 선택지에 결과 표시 (공통 UI 클래스 사용)
    choicesContainer
      .querySelectorAll(".ptg-quiz-ui-option-label")
      .forEach((label) => {
        const radio = label.querySelector('input[type="radio"]');
        if (!radio) return;

        // 옵션 텍스트에서 번호 추출
        const optionText = radio.value;
        const optionNum = circleToNumber(optionText);

        // 기존 클래스 제거 후 엔진 스타일과 동일한 클래스 적용
        label.classList.remove("ptg-quiz-ui-correct-answer");
        label.classList.remove("ptg-quiz-ui-incorrect-answer");

        // 정답 하이라이트
        if (correctNum === optionNum) {
          label.classList.add("ptg-quiz-ui-correct-answer");
        }

        // 선택한 답안이 틀렸으면 표시
        if (
          radio.checked &&
          !isCorrect &&
          optionNum === circleToNumber(QuizState.userAnswer || "")
        ) {
          label.classList.add("ptg-quiz-ui-incorrect-answer");
        }

        // 확인 후 선택 비활성화
        try {
          radio.disabled = true;
        } catch (e) {}
      });

    // 정답 확인 버튼 숨기기
    const btnCheck = document.getElementById("ptg-btn-check-answer");
    if (btnCheck) {
      btnCheck.style.display = "none";
    }

    // 이전 문제 버튼 표시
    const btnPrev = document.getElementById("ptg-btn-prev-question");
    if (btnPrev) {
      btnPrev.style.display = "inline-block";
      // 첫 번째 문제인 경우 비활성화
      btnPrev.disabled = QuizState.currentIndex <= 0;
    }

    // 다음 문제 버튼 표시
    const btnNext = document.getElementById("ptg-btn-next-question");
    if (btnNext) {
      btnNext.style.display = "inline-block";

      // 마지막 문제인 경우 버튼 텍스트를 '종료'로 변경
      if (QuizState.currentIndex >= QuizState.questions.length - 1) {
        btnNext.textContent = "종료";
        btnNext.classList.add("ptg-btn-finish");
      } else {
        btnNext.textContent = "다음 문제";
        btnNext.classList.remove("ptg-btn-finish");
      }
    }

    // 정/오답 텍스트 피드백 박스는 표시하지 않음 (색상 하이라이트만 유지)
    try {
      const existingFeedback = document.getElementById("ptg-quiz-feedback");
      if (existingFeedback && existingFeedback.parentNode) {
        existingFeedback.parentNode.removeChild(existingFeedback);
      }
    } catch (e) {}
  }

  /**
   * 해설 표시
   */
  async function showExplanation() {
    try {
      const response = await PTGPlatform.get(
        `ptg-quiz/v1/explanation/${QuizState.questionId}`
      );

      if (response && response.data) {
        const explanationEl = document.getElementById("ptg-quiz-explanation");
        if (explanationEl) {
          const subject = response.data.subject || "";
          // DB 내용 그대로 표시 (변형 없음)
          // CSS의 white-space: pre-wrap으로 줄바꿈이 자동 표시됨
          let explanationHtml = response.data.explanation || "해설이 없습니다.";

          // HTML 이스케이프는 하지 않음 (DB에 저장된 HTML 그대로 표시)
          // 하지만 XSS 방지를 위해 기본적인 처리는 필요 (이미 DB에 저장된 관리자 작성 내용이므로 안전)

          explanationEl.innerHTML = `
                        <h3>해설${
                          subject ? " | (" + subject + ")" : ""
                        } &nbsp;&nbsp;(id-${QuizState.questionId})</h3>
                        <div class="ptg-explanation-content">${explanationHtml}</div>
                        
                        <!-- Review Schedule Buttons -->
                        <div class="ptg-review-schedule-container" style="margin-top:20px; border-top:1px dashed #ddd; padding-top:15px;">
                            <p style="margin-bottom:10px; font-weight:600; font-size:14px; color:#555;">📅 복습 일정 설정 (틀린 문제 다시 풀기)</p>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <button type="button" class="ptg-schedule-btn" data-days="1" style="flex:1; padding:8px; border:none; border-radius:4px; background:#ff6b6b; color:white; cursor:pointer;">내일</button>
                                <button type="button" class="ptg-schedule-btn" data-days="3" style="flex:1; padding:8px; border:none; border-radius:4px; background:#feca57; color:white; cursor:pointer;">3일 후</button>
                                <button type="button" class="ptg-schedule-btn" data-days="5" style="flex:1; padding:8px; border:none; border-radius:4px; background:#1dd1a1; color:white; cursor:pointer;">5일 후</button>
                                <button type="button" class="ptg-schedule-btn" data-days="7" style="flex:1; padding:8px; border:none; border-radius:4px; background:#54a0ff; color:white; cursor:pointer;">7일 후</button>
                            </div>
                        </div>
                    `;

          // Add Event Listeners for Schedule Buttons
          setTimeout(() => {
            const scheduleBtns =
              explanationEl.querySelectorAll(".ptg-schedule-btn");
            const labelMap = {
              1: "내일",
              3: "3일 후",
              5: "5일 후",
              7: "7일 후",
            };

            scheduleBtns.forEach((btn) => {
              btn.addEventListener("click", async function (e) {
                e.preventDefault();
                const days = parseInt(this.dataset.days);
                if (!days) return;

                // Reset all buttons first: restore text and dim opacity
                scheduleBtns.forEach((b) => {
                  const d = parseInt(b.dataset.days);
                  b.textContent = labelMap[d] || `${d}일 후`;
                  b.style.opacity = "0.5";
                  b.style.border = "none"; // Reset any potential border
                });

                // UI Feedback for Active Button
                this.style.opacity = "1";
                this.style.border = "2px solid #333"; // Optional: Add border for emphasis? User didn't ask but "Saved" implies visual cue. Image showed yellow bg.
                // Keeping original style logic mostly, but enforcing text reset.
                // Revert border addition to stick strictly to request unless necessary.
                // The image shows a black border on the yellow button. I'll add that.
                this.style.border = "2px solid #333";

                this.textContent = "저장 중...";

                try {
                  await PTGPlatform.post(
                    `ptg-quiz/v1/questions/${QuizState.questionId}/schedule`,
                    { days: days }
                  );
                  this.textContent = "설정됨";
                } catch (err) {
                  console.error("Schedule Error:", err);
                  alert(
                    "일정 저장 실패: " + (err.message || "알 수 없는 오류")
                  );
                  this.textContent = labelMap[days] || "실패"; // Reset text on failure
                }
              });
            });
          }, 0);
          explanationEl.style.display = "block";

          // 해설이 표시되면 카드 크기가 자동으로 조정되도록 강제 리플로우
          // 카드 요소에 접근하여 리플로우 트리거
          const cardEl = document.getElementById("ptg-quiz-card");
          if (cardEl) {
            // 리플로우 강제 (카드 크기 재계산)
            cardEl.offsetHeight; // 읽기 작업으로 리플로우 트리거
            // 해설이 표시된 후 추가 리플로우 보장
            void cardEl.offsetWidth;
          }

          // 드로잉 모드가 활성화되어 있으면 캔버스 재조정 (카드 크기 변경 반영)
          if (QuizState.drawingEnabled) {
            // 여러 단계로 재조정하여 확실하게 반영
            setTimeout(() => {
              if (
                window.PTGQuizDrawing &&
                window.PTGQuizDrawing.initDrawingCanvas
              ) {
                window.PTGQuizDrawing.initDrawingCanvas();
              }
            }, 100); // 첫 번째 재조정

            setTimeout(() => {
              if (
                window.PTGQuizDrawing &&
                window.PTGQuizDrawing.initDrawingCanvas
              ) {
                window.PTGQuizDrawing.initDrawingCanvas();
              }
            }, 300); // 두 번째 재조정 (더 확실하게)
          }
        }
      }
    } catch (error) {
      console.error("해설 로드 오류:", error);
      // 오류 발생 시에도 사용자에게 알림
      const explanationEl = document.getElementById("ptg-quiz-explanation");
      if (explanationEl) {
        explanationEl.innerHTML = `<div class="ptg-error-message">해설을 불러오는 중 오류가 발생했습니다.<br>${
          error.message || ""
        }</div>`;
        explanationEl.style.display = "block";
      }
    }
  }

  /**
   * 문제 목록 로드 (필터 조건으로)
   */
  async function loadQuestionsList(filters = {}) {
    try {
      // 요청 시퀀스 증가 및 캡처
      const seq = ++QuizState.requestSeq;
      const params = new URLSearchParams();
      if (filters.year) params.append("year", filters.year);
      if (filters.subject) params.append("subject", filters.subject);
      if (filters.subsubject) params.append("subsubject", filters.subsubject);
      // limit=0 도 무제한 의미이므로 반드시 전송
      if (filters.limit !== undefined && filters.limit !== null) {
        params.append("limit", filters.limit);
      }
      if (filters.session) params.append("session", filters.session);
      if (filters.full_session) params.append("full_session", "true");
      if (filters.bookmarked) params.append("bookmarked", "true");
      if (filters.needs_review) params.append("needs_review", "true");
      if (filters.wrong_only) params.append("wrong_only", "true");
      if (filters.id) params.append("id", filters.id);
      if (filters.keyword) params.append("keyword", filters.keyword);
      if (filters.review_only) params.append("review_only", "true");
      if (filters.has_drawing) {
        params.append("has_drawing", "true");
        // 드로잉 필터 시 현재 기기 타입도 함께 전송 (기기별 드로잉 구분)
        if (QuizState.deviceType) {
          params.append("device_type", QuizState.deviceType);
        }
      }
      // [NEW] Mock Exam Params
      if (filters.mode) params.append("mode", filters.mode);
      if (filters.exam_course)
        params.append("exam_course", filters.exam_course);

      const endpoint = `ptg-quiz/v1/questions?${params.toString()}`;
      const response = await PTGPlatform.get(endpoint);

      // 만약 응답이 오래되어 현재 시퀀스보다 작으면 무시
      if (seq < QuizState.requestSeq) {
        return []; // 무시
      }

      if (!response || !response.success || !Array.isArray(response.data)) {
        throw new Error(response?.message || "문제 목록을 불러올 수 없습니다.");
      }

      return response.data; // question_id 배열
    } catch (error) {
      const code =
        error?.code || error?.data?.code || error?.data?.error || null;
      const message =
        typeof error?.message === "string" && error.message.trim().length > 0
          ? error.message.trim()
          : "문제 목록을 불러오는 중 오류가 발생했습니다.";

      const gateCodes = [
        "limit_reached",
        "daily_limit",
        "guest_limit",
        "login_required",
        "forbidden",
        "unauthorized",
      ];
      const lowerMessage = message.toLowerCase();
      const isGateError =
        (code && gateCodes.includes(String(code).toLowerCase())) ||
        lowerMessage.includes("일일 무료") ||
        lowerMessage.includes("프리미엄") ||
        lowerMessage.includes("로그인") ||
        lowerMessage.includes("한도") ||
        lowerMessage.includes("멤버십");

      if (isGateError) {
        showBlockingAlert(message);
        console.warn("[PTG Quiz] 제한으로 문제 로드 차단:", { code, message });
        return null;
      }

      console.error("[PTG Quiz] 문제 목록 로딩 실패:", {
        code,
        message,
        error,
      });
      throw error;
    }
  }

  /**
   * 다음 문제 로드
   */
  /**
   * 다음 문제 버튼 클릭 시
   * - 선택지 선택 시: DB 저장 및 통계 추가
   * - 선택지 미선택 시: 통계에 오답으로 추가 (DB 저장 없음)
   */
  async function loadNextQuestion() {
    // 다음 문제로 넘어갈 때 메모창이 열려있으면 닫기 (사용자 요청)
    if (window.PTGQuizToolbar && window.PTGQuizToolbar.toggleNotesPanel) {
      window.PTGQuizToolbar.toggleNotesPanel(false);
    }

    // 종료 상태에서는 더 이상 진행하지 않음
    if (QuizState.terminated) {
      return;
    }
    // 드로잉 모드가 활성화되어 있으면 자동으로 닫기 (사용자 요청)
    const overlay = document.getElementById("ptg-drawing-overlay");
    const isOverlayVisible =
      overlay &&
      window.getComputedStyle(overlay).display !== "none" &&
      overlay.style.display !== "none";

    if (QuizState.drawingEnabled || isOverlayVisible) {
      if (window.PTGQuizDrawing && window.PTGQuizDrawing.toggleDrawing) {
        window.PTGQuizDrawing.toggleDrawing(false);
        // toggleDrawing이 실행되면 isDirty가 true일 경우 비동기로 저장이 시작됨
        // 아래의 중복 저장 로직은 savingDrawing 플래그에 의해 관리됨
      }
    }

    if (QuizState.questions.length === 0) {
      showError("문제 목록이 없습니다.");
      return;
    }

    // 드로잉 저장 (다음 문제 클릭 시) - 비동기 처리 (기다리지 않음)
    if (QuizState.canvasContext) {
      if (QuizState.drawingSaveTimeout) {
        clearTimeout(QuizState.drawingSaveTimeout);
        QuizState.drawingSaveTimeout = null;
      }
      if (window.PTGQuizDrawing && window.PTGQuizDrawing.saveDrawingToServer) {
        // 백그라운드에서 저장 실행
        window.PTGQuizDrawing.saveDrawingToServer().catch((e) => {
          console.warn("[PTG Quiz] 드로잉 백그라운드 저장 실패:", e);
        });
      }
    }

    // 선택지 선택 여부 확인
    // 1. 이미 정답확인을 한 경우 (checkAnswer를 통해 tempAnswer가 생성됨)
    let finalAnswerObj = QuizState.tempAnswer;

    // 2. 정답확인을 안 하고 바로 넘어가는 경우 -> UI에서 현재 선택된 값 확인
    if (!finalAnswerObj) {
      let currentUiAnswer = "";
      if (typeof PTGQuizUI !== "undefined") {
        currentUiAnswer = PTGQuizUI.getSelectedAnswer({
          answerName: "ptg-answer",
          textAnswerId: "ptg-user-answer",
        });
      } else {
        currentUiAnswer = QuizState.userAnswer;
      }

      // DOM fallback
      if (!currentUiAnswer) {
        try {
          const checked = document.querySelector(
            '#ptg-quiz-choices input[type="radio"]:checked'
          );
          if (checked && checked.value) {
            currentUiAnswer = checked.value;
          }
        } catch (e) {}
      }

      // 선택된 값이 있으면 전송 객체 구성
      if (currentUiAnswer) {
        finalAnswerObj = {
          userAnswer: currentUiAnswer,
          normalizedAnswer: circleToNumber(currentUiAnswer),
          // 정답 여부(isCorrect)나 정답(correctAnswer)은 아직 모름 -> API 응답으로 처리
          isCorrect: false, // 임시값 (API 응답으로 덮어씌움)
          correctAnswer: "", // 임시값
        };
      }
    }

    const hasAnswer = finalAnswerObj !== null && finalAnswerObj !== undefined;

    if (hasAnswer) {
      // 선택지 선택 시: DB 저장 및 통계 추가 - 백그라운드 처리
      // sessionStorage에서 이미 로그된 question_id 목록 가져오기
      const QUIZ_STORAGE_KEY = "ptg_quiz_logged_questions";
      let loggedQuestions = [];
      try {
        const stored = sessionStorage.getItem(QUIZ_STORAGE_KEY);
        if (stored) {
          loggedQuestions = JSON.parse(stored);
        }
      } catch (e) {
        console.warn("PTG Quiz: Failed to read sessionStorage", e);
      }

      // 이미 이 세션에서 로그된 question_id인지 확인
      const currentQId = QuizState.questionId;
      const alreadyLogged = loggedQuestions.includes(currentQId);
      const elapsed = QuizState.timerSeconds;

      // attempt API 호출 (DB 저장) - 비동기 실행 (기다리지 않음)
      PTGPlatform.post(`ptg-quiz/v1/questions/${currentQId}/attempt`, {
        answer: finalAnswerObj.normalizedAnswer,
        elapsed: elapsed,
        skip_count_update: alreadyLogged ? true : false,
      })
        .then((response) => {
          // 성공 시 sessionStorage에 추가 (아직 로그되지 않은 경우만)
          if (response && response.data && !alreadyLogged) {
            // 다시 읽어서 안전하게 업데이트
            try {
              const currentStored = sessionStorage.getItem(QUIZ_STORAGE_KEY);
              let currentLogged = currentStored
                ? JSON.parse(currentStored)
                : [];
              if (!currentLogged.includes(currentQId)) {
                currentLogged.push(currentQId);
                sessionStorage.setItem(
                  QUIZ_STORAGE_KEY,
                  JSON.stringify(currentLogged)
                );
              }
            } catch (e) {}
          }

          // 통계에 추가 (완료 화면용)
          // API 응답에서 정확한 정답 여부와 정답 내용을 가져옴
          const isCorrectApi =
            response && response.data
              ? response.data.is_correct
              : finalAnswerObj.isCorrect;
          const correctAnswerApi =
            response && response.data
              ? response.data.correct_answer
              : finalAnswerObj.correctAnswer;

          QuizState.answers.push({
            questionId: currentQId,
            isCorrect: isCorrectApi,
            userAnswer: finalAnswerObj.userAnswer,
            correctAnswer: correctAnswerApi,
          });
        })
        .catch((error) => {
          console.error("답안 저장 오류 (백그라운드):", error);
        });
    } else {
      // 선택지 미선택 시: 통계에 오답으로 추가 (DB 저장 없음)
      // 정답 정보 가져오기 (통계용) - 백그라운드 처리
      const currentQId = QuizState.questionId;
      PTGPlatform.get(`ptg-quiz/v1/questions/${currentQId}`)
        .then((response) => {
          if (response && response.data) {
            const correctAnswer =
              response.data.answer || response.data.correct_answer;
            QuizState.answers.push({
              questionId: currentQId,
              isCorrect: false,
              userAnswer: "",
              correctAnswer: correctAnswer,
            });
          }
        })
        .catch((error) => {
          console.error("정답 정보 가져오기 오류:", error);
          // 오류 발생 시에도 통계에 추가
          QuizState.answers.push({
            questionId: currentQId,
            isCorrect: false,
            userAnswer: "",
            correctAnswer: "",
          });
        });
    }

    // 임시 답안 초기화
    QuizState.tempAnswer = null;

    if (QuizState.currentIndex < QuizState.questions.length - 1) {
      // 다음 문제로 이동
      QuizState.currentIndex++;
      QuizState.questionId = QuizState.questions[QuizState.currentIndex];
      QuizState.isAnswered = false;
      QuizState.userAnswer = "";

      // 해설 영역 숨기기
      const explanationEl = document.getElementById("ptg-quiz-explanation");
      if (explanationEl) {
        explanationEl.style.display = "none";
      }

      // 피드백 박스 제거
      const feedbackBox = document.getElementById("ptg-quiz-feedback");
      if (feedbackBox) {
        feedbackBox.remove();
      }

      // 버튼 상태 초기화: 모든 버튼 표시 (항상 활성화)
      const btnCheck = document.getElementById("ptg-btn-check-answer");
      const btnPrev = document.getElementById("ptg-btn-prev-question");
      const btnNext = document.getElementById("ptg-btn-next-question");

      if (btnCheck) {
        btnCheck.style.display = "inline-block";
        btnCheck.disabled = false; // 항상 활성화
        // 버튼 텍스트를 두 줄로 설정
        if (!btnCheck.querySelector(".ptg-btn-text-main")) {
          btnCheck.innerHTML =
            '<span class="ptg-btn-text-main">정답 확인(풀이)</span>';
        }
      }

      if (btnPrev) {
        btnPrev.style.display = "inline-block";
        // 첫 번째 문제인 경우 비활성화
        btnPrev.disabled = QuizState.currentIndex <= 0;
      }

      if (btnNext) {
        btnNext.style.display = "inline-block";
        btnNext.disabled = false; // 항상 활성화
        btnNext.textContent = "다음 문제";
        btnNext.classList.remove("ptg-btn-finish");
      }

      // 문제 로드
      loadQuestion();
    } else {
      // 마지막 문제 완료 - 완료 화면 표시
      finishQuiz();
    }
  }

  /**
   * 이전 문제 로드
   */
  async function loadPrevQuestion() {
    // 종료 상태에서는 더 이상 진행하지 않음
    if (QuizState.terminated) {
      return;
    }

    // 드로잉 모드가 활성화되어 있으면 이전 문제로 넘어가지 않음
    const overlay = document.getElementById("ptg-drawing-overlay");
    const isOverlayVisible =
      overlay &&
      window.getComputedStyle(overlay).display !== "none" &&
      overlay.style.display !== "none";

    if (QuizState.drawingEnabled || isOverlayVisible) {
      // 종료 상태에서는 알림 표시하지 않음
      if (!QuizState.terminated) {
        PTG_quiz_alert("드로잉 모드를 해제하세요");
      }
      return;
    }

    if (QuizState.questions.length === 0) {
      showError("문제 목록이 없습니다.");
      return;
    }

    // 첫 번째 문제인 경우
    if (QuizState.currentIndex <= 0) {
      PTG_quiz_alert("첫 번째 문제입니다.");
      return;
    }

    // 드로잉 저장 (이전 문제 클릭 시) - 비동기 처리
    if (QuizState.canvasContext) {
      if (QuizState.drawingSaveTimeout) {
        clearTimeout(QuizState.drawingSaveTimeout);
        QuizState.drawingSaveTimeout = null;
      }
      if (window.PTGQuizDrawing && window.PTGQuizDrawing.saveDrawingToServer) {
        // 백그라운드에서 저장 실행
        window.PTGQuizDrawing.saveDrawingToServer().catch((e) => {
          console.warn("[PTG Quiz] 드로잉 백그라운드 저장 실패:", e);
        });
      }
    }

    // 이전 문제로 이동
    QuizState.currentIndex--;
    QuizState.questionId = QuizState.questions[QuizState.currentIndex];
    QuizState.isAnswered = false;
    QuizState.userAnswer = "";
    QuizState.tempAnswer = null;

    // 해설 영역 숨기기
    const explanationEl = document.getElementById("ptg-quiz-explanation");
    if (explanationEl) {
      explanationEl.style.display = "none";
    }

    // 피드백 박스 제거
    const feedbackBox = document.getElementById("ptg-quiz-feedback");
    if (feedbackBox) {
      feedbackBox.remove();
    }

    // 버튼 상태 초기화: 모든 버튼 표시 (항상 활성화)
    const btnCheck = document.getElementById("ptg-btn-check-answer");
    const btnNext = document.getElementById("ptg-btn-next-question");
    const btnPrev = document.getElementById("ptg-btn-prev-question");

    if (btnCheck) {
      // [FIX] Mock Exam 모드에서는 무조건 숨김 (Flicker 방지)
      if (QuizState.mode === "mock_exam" || QuizState.mode === "exam") {
        btnCheck.style.display = "none";
      } else {
        btnCheck.style.display = "inline-block";
        btnCheck.disabled = false;
        // 버튼 텍스트를 한 줄로 설정
        if (!btnCheck.querySelector(".ptg-btn-text-main")) {
          btnCheck.innerHTML =
            '<span class="ptg-btn-text-main">정답 확인(풀이)</span>';
        }
      }
    }
    if (btnNext) {
      btnNext.style.display = "inline-block";
      btnNext.disabled = false; // 항상 활성화
      btnNext.textContent = "다음 문제";
      btnNext.classList.remove("ptg-btn-finish");
    }
    if (btnPrev) {
      btnPrev.style.display = "inline-block";
      // 첫 번째 문제인 경우 비활성화
      btnPrev.disabled = QuizState.currentIndex <= 0;
    }

    // 문제 로드
    loadQuestion();
  }

  /**
   * 진행률 업데이트
   */
  function updateProgress(current, total) {
    const counter = document.getElementById("ptgates-question-counter");
    const fill = document.getElementById("ptgates-progress-fill");

    if (counter) {
      counter.textContent = `${current} / ${total}`;
    }

    if (fill) {
      const percentage = (current / total) * 100;
      fill.style.width = percentage + "%";
    }
  }

  /**
   * 진행 상태 섹션 표시/숨김
   */
  function showProgressSection() {
    if (QuizState.terminated) return;
    const progress = document.getElementById("ptgates-progress-section");
    if (progress) {
      progress.style.display = "block";
      // 강제로 표시 (다른 스타일이 덮어쓸 수 있음)
      progress.style.setProperty("display", "block", "important");
    }
  }

  /**
   * 퀴즈 UI 표시 (툴바/카드/버튼)
   */
  function showQuizUI() {
    const cardWrapper = document.querySelector(".ptg-quiz-card-wrapper");
    const actions = document.querySelector(".ptg-quiz-actions");
    const toolbar = document.querySelector(".ptg-quiz-toolbar");
    if (toolbar) toolbar.style.display = "flex";
    if (cardWrapper) cardWrapper.style.display = "block";
    if (actions) actions.style.display = "flex";
  }

  function hideProgressSection() {
    const progress = document.getElementById("ptgates-progress-section");
    if (progress) {
      progress.style.display = "none";
    }
  }

  /**
   * 퀴즈 포기
   */
  async function giveUpQuiz() {
    // 중복 실행 방지 플래그 세팅
    if (QuizState.giveUpInProgress) {
      return;
    }
    QuizState.giveUpInProgress = true;
    // 상태 전환: terminated
    setState("terminated");

    // 즉시 UI 상호작용 차단 및 숨김
    try {
      // 타이머 즉시 정지
      if (QuizState.timerInterval) {
        clearInterval(QuizState.timerInterval);
        QuizState.timerInterval = null;
      }

      const btnCheck = document.getElementById("ptg-btn-check-answer");
      const btnNext = document.getElementById("ptg-btn-next-question");
      const toolbar = document.querySelector(".ptg-quiz-toolbar");
      const actions = document.querySelector(".ptg-quiz-actions");
      const cardWrapper = document.querySelector(".ptg-quiz-card-wrapper");
      if (btnCheck) {
        btnCheck.disabled = true;
        btnCheck.style.display = "none";
      }
      if (btnNext) {
        btnNext.disabled = true;
        btnNext.style.display = "none";
      }
      if (toolbar) {
        toolbar.style.display = "none";
      }
      if (actions) {
        actions.style.display = "none";
      }
      if (cardWrapper) {
        cardWrapper.style.display = "none";
      }
      // 진행 상태 바는 유지하여 사용자가 종료 상태를 인지할 수 있게 함
    } catch (e) {
      // ignore
    }

    // 현재 문제까지 답안 제출한 경우, 그 문제의 답안도 저장
    const currentQuestionId = QuizState.questionId;
    if (currentQuestionId && !QuizState.isAnswered) {
      // 현재 문제에 대한 답안이 있으면 저장
      let userAnswer = "";
      if (typeof PTGQuizUI !== "undefined") {
        userAnswer = PTGQuizUI.getSelectedAnswer({
          answerName: "ptg-answer",
          textAnswerId: "ptg-user-answer",
        });
      } else {
        userAnswer = QuizState.userAnswer;
      }

      // 답안이 있으면 저장
      if (userAnswer) {
        try {
          const normalizedAnswer = circleToNumber(userAnswer);
          const response = await PTGPlatform.post(
            `ptg-quiz/v1/questions/${currentQuestionId}/attempt`,
            {
              answer: normalizedAnswer,
              elapsed: QuizState.timerSeconds,
            }
          );

          if (response && response.data) {
            QuizState.answers.push({
              questionId: currentQuestionId,
              isCorrect: response.data.is_correct,
              userAnswer: userAnswer,
              correctAnswer: response.data.correct_answer,
            });
          }
        } catch (error) {
          console.error("포기 시 답안 저장 오류:", error);
          // 오류가 발생해도 계속 진행
        }
      }
    }

    // 퀴즈 완료 처리 (Give Up은 결과를 보여주되, 미응시 문제는 오답 처리)
    try {
      // 타이머 정지
      if (QuizState.timerInterval) {
        clearInterval(QuizState.timerInterval);
        QuizState.timerInterval = null;
      }

      // 미응시 문제 오답 처리 (DB 저장은 안 함, 통계용)
      const answeredIds = new Set(QuizState.answers.map((a) => a.questionId));
      QuizState.questions.forEach((qid) => {
        if (!answeredIds.has(qid)) {
          QuizState.answers.push({
            questionId: qid,
            isCorrect: false,
            userAnswer: "",
            correctAnswer: "", // 정답 몰라도 통계/재시작에는 문제 없음
          });
        }
      });

      // UI 숨기기
      const cardWrapper = document.querySelector(".ptg-quiz-card-wrapper");
      const actions = document.querySelector(".ptg-quiz-actions");
      const toolbar = document.querySelector(".ptg-quiz-toolbar");
      if (toolbar) toolbar.style.display = "none";
      if (cardWrapper) cardWrapper.style.display = "none";
      if (actions) actions.style.display = "none";

      // 진행 상태 바 숨기기
      hideProgressSection();

      // 결과 화면 표시 (finishQuiz 재사용)
      // finishQuiz는 appState가 'terminated'이면 상태를 변경하지 않음
      // 따라서 결과만 표시됨
      finishQuiz();
    } catch (e) {
      console.error("포기하기 처리 중 오류:", e);
      // 오류 시 안전하게 초기화
      setState("idle");
      showFilterSection();
    } finally {
      // 만약 어떤 이유로 종료 상태가 아니라면 버튼을 복구
      if (
        QuizState.appState !== "terminated" &&
        QuizState.appState !== "finished" &&
        QuizState.appState !== "idle"
      ) {
        QuizState.giveUpInProgress = false;
        const btnGiveup = document.getElementById("ptgates-giveup-btn");
        if (btnGiveup) {
          btnGiveup.disabled = false;
          btnGiveup.style.pointerEvents = "";
          try {
            btnGiveup.removeAttribute("disabled");
          } catch (e) {}
        }
      }
    }
  }

  /**
   * 퀴즈 완료 처리
   */
  function finishQuiz() {
    // 타이머 정지 (안전)
    if (QuizState.timerInterval) {
      clearInterval(QuizState.timerInterval);
      QuizState.timerInterval = null;
    }

    // 통계 계산
    const correctCount = QuizState.answers.filter((a) => a.isCorrect).length;
    const incorrectCount = QuizState.answers.length - correctCount;
    const accuracy =
      QuizState.answers.length > 0
        ? Math.round((correctCount / QuizState.answers.length) * 100)
        : 0;

    // 소요 시간 계산
    let totalTime = 0;
    if (QuizState.startTime) {
      totalTime = Math.floor((Date.now() - QuizState.startTime) / 1000);
    } else {
      // startTime이 없으면 타이머 초기값에서 현재 타이머 값을 빼서 계산
      // 문제 수 × 50초가 초기값이므로, 초기값 - 현재값 = 경과 시간
      // 하지만 정확하지 않으므로 0으로 표시
      totalTime = 0;
    }

    // 완료 화면 표시
    showQuizResult({
      accuracy: accuracy,
      correct: correctCount,
      incorrect: incorrectCount,
      totalTime: totalTime,
    });

    // 상태 전환: terminated 상태면 유지, 아니면 finished로 전환
    if (QuizState.appState !== "terminated") {
      setState("finished");
    } else {
      applyUIForState();
    }

    // [NEW] Mock Exam 모드에서는 결과 제출
    // [NEW] Mock Exam 모드이거나 모의고사 컨텍스트(교시 정보 있음)인 경우 결과 제출
    if (
      QuizState.mode === "mock_exam" ||
      (QuizState.session && QuizState.exam_course)
    ) {
      submitMockExam();
    }
  }

  /**
   * 모의고사 결과 제출
   */
  async function submitMockExam() {
    // 로딩 표시
    const resultSection = document.getElementById("ptg-quiz-result-section");
    if (!resultSection) return;

    // 로딩 추가 (기존 내용 유지)
    let loadingDiv = document.getElementById("ptg-mock-loading");
    if (!loadingDiv) {
      loadingDiv = document.createElement("div");
      loadingDiv.id = "ptg-mock-loading";
      loadingDiv.className = "ptg-quiz-loading";
      loadingDiv.style.cssText =
        "display:flex; flex-direction:column; align-items:center; justify-content:center; padding: 40px; border-top: 1px solid #eee; margin-top: 30px;";
      loadingDiv.innerHTML =
        '<div class="ptg-loader"></div><p style="margin-top:15px; color:#666;">결과를 제출하고 채점 중입니다...</p>';
      resultSection.appendChild(loadingDiv);
    }

    try {
      const payload = {
        session_code: QuizState.session,
        exam_course: QuizState.exam_course,
        start_time: QuizState.startTime
          ? new Date(QuizState.startTime).toISOString()
          : new Date().toISOString(),
        end_time: new Date().toISOString(),
        answers: QuizState.answers.map((a) => ({
          question_id: a.questionId,
          is_correct: a.isCorrect,
          user_answer: a.userAnswer,
        })),
        question_ids: QuizState.questions,
      };

      const response = await PTGPlatform.post("ptg-mock/v1/submit", payload);

      // 로딩 제거
      if (loadingDiv) loadingDiv.remove();

      if (response && response.success) {
        // 결과 브리핑 표시 (리다이렉트 없음)
        // 과목별 점수 테이블 생성
        let subjectRows = "";
        if (response.subjects && Array.isArray(response.subjects)) {
          // Group by Category
          const groups = {};
          const groupOrder = [];

          response.subjects.forEach((subj) => {
            const cat = subj.category || "기타";
            if (!groups[cat]) {
              groups[cat] = [];
              groupOrder.push(cat);
            }
            groups[cat].push(subj);
          });

          subjectRows = `
            <div class="ptg-mock-subject-table" style="margin: 20px 0; border: 1px solid #e0e0e0; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <table style="width:100%; border-collapse: collapse; font-size: 14px;">
                    <thead>
                        <tr style="background: #4a69bd; color: #ffffff;">
                            <th style="padding: 14px; text-align: center; font-weight: 600; width: 22%; border-right: 1px solid rgba(255,255,255,0.3);">대분류</th>
                            <th style="padding: 14px; text-align: center; font-weight: 600; width: 33%; border-right: 1px solid rgba(255,255,255,0.3);">세부과목</th>
                            <th style="padding: 14px; text-align: center; font-weight: 600; border-right: 1px solid rgba(255,255,255,0.3);">문제수</th>
                            <th style="padding: 14px; text-align: center; font-weight: 600; border-right: 1px solid rgba(255,255,255,0.3);">정답</th>
                            <th style="padding: 14px; text-align: center; font-weight: 600; border-right: 1px solid rgba(255,255,255,0.3);">오답</th>
                            <th style="padding: 14px; text-align: center; font-weight: 600; border-right: 1px solid rgba(255,255,255,0.3);">점수</th>
                            <th style="padding: 14px; text-align: center; font-weight: 600;">결과</th>
                        </tr>
                    </thead>
                    <tbody>
          `;

          let totalQ = 0;
          let totalC = 0;
          let totalW = 0;

          groupOrder.forEach((catName) => {
            const subjs = groups[catName];
            let subQ = 0;
            let subC = 0;
            let subW = 0;

            // Render Subjects
            subjs.forEach((subj, idx) => {
              const t = parseInt(subj.total);
              const c = parseInt(subj.correct);
              const w = t - c;

              subQ += t;
              subC += c;
              subW += w;

              const catDisplay =
                idx === 0
                  ? `<span style="font-weight:700; color:#333;">${catName}</span>`
                  : "";
              const encodedSubj = encodeURIComponent(subj.subject);
              // [SECURE REVIEW LINK]
              const reviewToken = subj.review_token || "";
              const wrongLink =
                w > 0 && reviewToken
                  ? `<a href="/mock-review/?token=${encodeURIComponent(
                      reviewToken
                    )}" target="_blank" style="color:#d63031; text-decoration:underline; font-weight:bold;">${w}</a>`
                  : w > 0
                  ? `<span style="color:#d63031; font-weight:bold;">${w}</span>` // No token available fallback
                  : `<span style="color:#ccc;">0</span>`;

              subjectRows += `
                        <tr style="border-top: 1px solid #f0f0f0;">
                            <td style="padding: 12px; text-align: center; color: #333; border-right: 1px solid #d0d0d0;">${catDisplay}</td>
                            <td style="padding: 12px; text-align: left; color: #555;">${subj.subject}</td>
                            <td style="padding: 12px; text-align: center; color: #666;">${t}</td>
                            <td style="padding: 12px; text-align: center; color: #666;">${c}</td>
                            <td style="padding: 12px; text-align: center;">${wrongLink}</td>
                            <td style="padding: 12px; text-align: center; font-weight: bold; color: #333;">${subj.score}점</td>
                            <td style="padding: 12px; text-align: center; color: #ccc;">-</td>
                        </tr>
                  `;
            });

            // Subtotal Row
            const subScore = subQ > 0 ? Math.round((subC / subQ) * 100) : 0;
            const isFail = subScore < 40;
            const subStatusText = isFail ? "과락" : "통과";
            const subStatusColor = isFail ? "#e74c3c" : "#2ecc71";

            subjectRows += `
                    <tr style="background-color: #f1f4f8; border-top: 1px solid #dce4ec; border-bottom: 1px solid #dce4ec;">
                        <td colspan="2" style="padding: 12px; text-align: center; font-weight: 700; color: #2c3e50; border-right: 1px solid #dcdcdc;">${catName} 소계</td>
                        <td style="padding: 12px; text-align: center; font-weight: 700; color: #2c3e50;">${subQ}</td>
                        <td style="padding: 12px; text-align: center; font-weight: 700; color: #2c3e50;">${subC}</td>
                        <td style="padding: 12px; text-align: center; font-weight: 700; color: #d63031;">${subW}</td>
                        <td style="padding: 12px; text-align: center; font-weight: 800; color: #2c3e50;">${subScore}점</td>
                        <td style="padding: 12px; text-align: center; font-weight: 800; color: ${subStatusColor};">${subStatusText}</td>
                    </tr>
              `;

            totalQ += subQ;
            totalC += subC;
            totalW += subW;
          });

          // Grand Total Row
          const totalStatusColor = response.is_pass ? "#2ecc71" : "#e74c3c";
          const totalStatusText = response.is_pass ? "합격" : "불합격";

          subjectRows += `
                        <tr style="border-top: 3px solid #bdc3c7; background-color: #ecf0f1;">
                            <td colspan="2" style="padding: 16px; text-align: center; font-size: 16px; font-weight: 800; color: #2c3e50;">전체 합계</td>
                            <td style="padding: 16px; text-align: center; font-size: 16px; font-weight: 800; color: #2c3e50;">${totalQ}</td>
                            <td style="padding: 16px; text-align: center; font-size: 16px; font-weight: 800; color: #2c3e50;">${totalC}</td>
                            <td style="padding: 16px; text-align: center; font-size: 16px; font-weight: 800; color: #d63031;">${totalW}</td>
                            <td style="padding: 16px; text-align: center; font-size: 16px; font-weight: 800; color: #2c3e50;">${response.score}점</td>
                            <td style="padding: 16px; text-align: center; font-size: 16px; font-weight: 800; color: ${totalStatusColor};">${totalStatusText}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
          `;
        }

        const resultHtml = `
            <div id="ptg-mock-briefing-container" class="ptg-mock-result-briefing" style="text-align:center; padding: 20px 10px; max-width: 900px; margin: 20px auto 0; border-top: 2px dashed #eee;">
                <h2 style="margin-bottom: 20px; font-weight:700; color:#333;">
                    ${QuizState.year ? QuizState.year + "년 " : ""}
                    ${
                      QuizState.session
                        ? (QuizState.session > 1000
                            ? QuizState.session - 1000
                            : QuizState.session) + "회차 "
                        : ""
                    }
                    ${
                      QuizState.exam_course
                        ? QuizState.exam_course + "교시 "
                        : ""
                    }
                    모의고사 결과
                </h2>
                
                <div class="ptg-mock-summary-card" style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 25px;">
                    <div style="font-size: 14px; color: #666; margin-bottom: 5px;">총점 (평균)</div>
                    <div class="ptg-mock-score" style="font-size: 42px; font-weight: 800; color: #2c3e50; line-height: 1.2;">
                        ${
                          response.score !== null &&
                          response.score !== undefined
                            ? response.score
                            : 0
                        }<span style="font-size: 20px; font-weight: 600;">점</span>
                    </div>
                    <div class="ptg-mock-status" style="margin-top: 10px; font-size: 18px; font-weight: bold; color: ${
                      response.is_pass ? "#28a745" : "#dc3545"
                    };">
                        ${response.is_pass ? "🎉 합격입니다!" : "불합격입니다"}
                    </div>
                </div>

                ${subjectRows}
                
                <div class="ptg-mock-actions" style="margin-top: 30px; display: flex; gap: 10px; justify-content: center;">
                    <button onclick="window.location.reload()" class="ptg-btn ptg-btn-primary" style="padding: 12px 30px; font-size: 16px; border:none; border-radius:6px; cursor:pointer; font-weight: 600;">확인 (마침)</button>
                </div>
                
                <p style="margin-top: 20px; font-size: 13px; color: #999;">
                    * 상세 결과 및 오답 노트는 '학습현황의 시험결과' 메뉴에서 확인하실 수 있습니다.
                </p>
            </div>
        `;
        // 기존 브리핑 영역 제거
        const oldBriefing = document.getElementById(
          "ptg-mock-briefing-container"
        );
        if (oldBriefing) oldBriefing.remove();

        resultSection.insertAdjacentHTML("beforeend", resultHtml);
      } else {
        throw new Error(response.message || "제출 실패");
      }
    } catch (error) {
      console.error("결과 제출 오류:", error);
      if (resultSection) {
        const errorHtml = `<div class="ptg-error-message" style="margin-top: 20px; padding: 15px; background: #fff0f0; border-radius: 8px; text-align: center;">
            <p style="color: #d63031; font-weight: bold;">결과 제출 중 오류가 발생했습니다.</p>
            <p style="font-size: 13px; color: #666;">${
              error.message || "잠시 후 다시 시도해주세요."
            }</p>
            <button onclick="submitMockExam()" class="ptg-btn ptg-btn-sm" style="margin-top: 10px;">다시 시도</button>
        </div>`;
        resultSection.insertAdjacentHTML("beforeend", errorHtml);
      }
    }
  }

  /**
   * 완료 화면 표시（Mock Exam 모드에서는 사용되지 않거나 제출 실패 시 표시됨）
   */
  function showQuizResult(stats) {
    const section = document.getElementById("ptg-quiz-result-section");
    if (!section) return;

    // 통계 업데이트
    const accuracyEl = document.getElementById("ptg-quiz-result-accuracy");
    const correctEl = document.getElementById("ptg-quiz-result-correct");
    const incorrectEl = document.getElementById("ptg-quiz-result-incorrect");
    const timeEl = document.getElementById("ptg-quiz-result-time");

    if (accuracyEl) accuracyEl.textContent = stats.accuracy + "%";
    if (correctEl) correctEl.textContent = stats.correct + "개";
    if (incorrectEl) incorrectEl.textContent = stats.incorrect + "개";
    if (timeEl) timeEl.textContent = formatTime(stats.totalTime);

    section.style.display = "block";

    // 다시 시작 버튼 이벤트
    const restartBtn = document.getElementById("ptg-quiz-restart-btn");
    if (restartBtn) {
      restartBtn.onclick = function () {
        // 페이지 새로고침
        window.location.reload();
      };
    }

    // 학습현황으로 바로가기 버튼 이벤트
    const dashboardBtn = document.getElementById("ptg-quiz-dashboard-btn");
    if (dashboardBtn) {
      dashboardBtn.onclick = function () {
        const dashboardUrl = this.getAttribute("data-dashboard-url");
        if (dashboardUrl) {
          window.location.href = dashboardUrl;
        } else {
          // 대시보드 URL이 없으면 홈으로 이동
          window.location.href = "/";
        }
      };
    }

    // 틀린 문제 박스 클릭 이벤트 (틀린 문제가 있을 때만)
    const incorrectStatItem = document.getElementById(
      "ptg-quiz-stat-incorrect"
    );
    if (incorrectStatItem && stats.incorrect > 0) {
      incorrectStatItem.onclick = function () {
        restartWithIncorrectQuestions();
      };
    } else if (incorrectStatItem) {
      // 틀린 문제가 없으면 클릭 불가
      incorrectStatItem.style.cursor = "default";
      incorrectStatItem.style.opacity = "0.6";
    }
  }

  /**
   * 틀린 문제만 다시 시작
   */
  async function restartWithIncorrectQuestions() {
    // 틀린 문제 ID 목록 추출
    const incorrectQuestionIds = QuizState.answers
      .filter((a) => !a.isCorrect)
      .map((a) => a.questionId);

    if (incorrectQuestionIds.length === 0) {
      PTG_quiz_alert("틀린 문제가 없습니다.");
      return;
    }

    // 완료 화면 숨기기
    const resultSection = document.getElementById("ptg-quiz-result-section");
    if (resultSection) {
      resultSection.style.display = "none";
    }

    // 퀴즈 상태 초기화
    QuizState.questions = incorrectQuestionIds;
    QuizState.currentIndex = 0;
    QuizState.questionId = incorrectQuestionIds[0];
    QuizState.answers = []; // 통계 초기화
    QuizState.isAnswered = false;
    QuizState.userAnswer = "";
    QuizState.tempAnswer = null;
    QuizState.startTime = Date.now(); // 시작 시간 재설정
    QuizState.giveUpInProgress = false; // 포기하기 플래그 초기화 (재시작 시 포기하기 버튼 다시 동작하도록)

    // 타이머 초기화 및 설정
    if (QuizState.timerInterval) {
      clearInterval(QuizState.timerInterval);
      QuizState.timerInterval = null;
    }
    // 타이머 설정: 문제 수 × 50초
    QuizState.timerSeconds = incorrectQuestionIds.length * 50;

    // 상태 전환
    setState("running");
    applyUIForState();

    // 진행 상태 섹션 표시
    showProgressSection();

    // 진행률 업데이트
    updateProgress(1, incorrectQuestionIds.length);

    // 타이머 표시 업데이트
    updateTimerDisplay();

    // 퀴즈 UI 표시
    // 퀴즈 UI 표시 (loadQuestion 완료 후 표시됨)
    // showQuizUI();

    // 포기하기 버튼 활성화
    const btnGiveup = document.getElementById("ptgates-giveup-btn");
    if (btnGiveup) {
      btnGiveup.disabled = false;
      btnGiveup.style.pointerEvents = "auto";
    }

    // 타이머 시작
    const container = document.getElementById("ptg-quiz-container");
    if (container && container.dataset.unlimited !== "1") {
      startTimer();
    }

    // 버튼 이벤트 재바인딩 (틀린 문제 다시 풀기 시)
    // loadQuestion이 호출되기 전에 이벤트가 바인딩되어야 하므로
    // loadQuestion 내부에서 버튼이 표시된 후에 이벤트를 재바인딩하도록 함
    // 여기서는 버튼이 제대로 표시되고 활성화되는지만 확인
    setTimeout(() => {
      const btnCheckAnswer = document.getElementById("ptg-btn-check-answer");
      if (btnCheckAnswer) {
        btnCheckAnswer.disabled = false;
        // 이벤트 리스너가 없으면 추가
        if (!btnCheckAnswer.hasAttribute("data-listener-attached")) {
          btnCheckAnswer.addEventListener("click", checkAnswer);
          btnCheckAnswer.setAttribute("data-listener-attached", "true");
        }
      }

      const btnNext = document.getElementById("ptg-btn-next-question");
      if (btnNext) {
        btnNext.disabled = false;
        // 이벤트 리스너가 없으면 추가
        if (!btnNext.hasAttribute("data-listener-attached")) {
          btnNext.addEventListener("click", loadNextQuestion);
          btnNext.setAttribute("data-listener-attached", "true");
        }
      }
    }, 100);

    // 문제 카드 완전 초기화 (틀린 문제 다시 풀기 시 기존 DOM 제거)
    const questionCardEl = document.getElementById("ptg-quiz-card");
    if (questionCardEl) {
      // 기존 내용 완전 제거
      questionCardEl.innerHTML = "";
      // 로딩 클래스 제거
      questionCardEl.classList.remove("loading-active");
    }

    // 해설 영역도 완전 제거
    const explanationEl = document.getElementById("ptg-quiz-explanation");
    if (explanationEl) {
      explanationEl.remove();
    }

    // 첫 번째 문제 로드
    await loadQuestion();

    // 헤더로 스크롤
    if (window.PTGQuizToolbar && window.PTGQuizToolbar.scrollToHeader) {
      window.PTGQuizToolbar.scrollToHeader();
    }
  }

  /**
   * 시간 포맷팅 (초 → MM:SS)
   */
  function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${String(mins).padStart(2, "0")}:${String(secs).padStart(2, "0")}`;
  }

  /**
   * 실전 모의 학습Tip 모달 설정 (공통 팝업 유틸리티 사용)
   */
  function setupTipModal() {
    const tipBtn = document.getElementById("ptg-quiz-tip-btn");
    if (!tipBtn) return;

    // 공통 팝업 유틸리티 사용
    tipBtn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      // 공통 팝업 유틸리티가 로드되었는지 확인
      if (
        typeof window.PTGTips === "undefined" ||
        typeof window.PTGTips.show !== "function"
      ) {
        console.warn(
          "[PTG Quiz] 공통 팝업 유틸리티가 아직 로드되지 않았습니다."
        );
        return;
      }

      // 공통 팝업 표시 (내용은 중앙 저장소에서 자동 가져옴)
      window.PTGTips.show("quiz-tip");
    });
  }

  /**
   * 에러 표시
   */
  function showError(message) {
    if (
      typeof PTGPlatform !== "undefined" &&
      typeof PTGPlatform.showError === "function"
    ) {
      PTGPlatform.showError(message);
    } else {
      // 기본 에러 표시
      console.error("[PTG Quiz] 오류:", message);
      const errorEl = document.getElementById("ptg-quiz-card");
      if (errorEl) {
        errorEl.innerHTML =
          '<div style="color: red; padding: 20px; text-align: center; background: #fff3cd; border: 2px solid #ffc107; border-radius: 4px;"><strong>오류:</strong> ' +
          message +
          "</div>";
      }
    }
  }

  /**
   * 디바운스 헬퍼
   */
  function debounce(func, wait) {
    // PTGPlatform이 없으면 기본 debounce 사용
    if (
      typeof window.PTGPlatform !== "undefined" &&
      typeof window.PTGPlatform.debounce === "function"
    ) {
      return window.PTGPlatform.debounce(func, wait);
    }
    // 기본 debounce 구현
    let timeout;
    return function (...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), wait);
    };
  }

  // 전역으로 노출 (먼저 노출하여 템플릿에서 접근 가능하도록)
  window.PTGQuiz = {
    init,
    loadQuestion,
    QuizState, // quiz-toolbar.js에서 접근 가능하도록 노출
    toggleBookmark: function () {
      if (window.PTGQuizToolbar && window.PTGQuizToolbar.toggleBookmark) {
        return window.PTGQuizToolbar.toggleBookmark();
      }
    },
    toggleReview: function () {
      if (window.PTGQuizToolbar && window.PTGQuizToolbar.toggleReview) {
        return window.PTGQuizToolbar.toggleReview();
      }
    },
    toggleNotesPanel: function (force) {
      if (window.PTGQuizToolbar && window.PTGQuizToolbar.toggleNotesPanel) {
        return window.PTGQuizToolbar.toggleNotesPanel(force);
      }
    },
    toggleDrawing: function (force) {
      if (window.PTGQuizDrawing && window.PTGQuizDrawing.toggleDrawing) {
        return window.PTGQuizDrawing.toggleDrawing(force);
      }
    },
    selectFilterAndStart: selectFilterAndStart,
    startQuizWithParams: startQuizWithParams,
  };

  /**
   * 그리드 등에서 직접 과목/세부과목 선택 후 퀴즈 시작 (Sequential Loading)
   */
  async function selectFilterAndStart(session, subject, subsubject) {
    const sessionSelect = document.getElementById("ptg-quiz-filter-session");
    const subjectSelect = document.getElementById("ptg-quiz-filter-subject");
    const subSubjectSelect = document.getElementById(
      "ptg-quiz-filter-subsubject"
    );

    // 1. Session 설정
    if (sessionSelect) {
      sessionSelect.value = String(session);
      // 단순 UI 업데이트용 이벤트 실행 (다른 리스너가 있을 수 있으므로)
      try {
        sessionSelect.dispatchEvent(new Event("change"));
      } catch (e) {}
    }

    // 2. 과목 목록 로드 (await)
    await loadSubjectsForSession(session);

    // 3. Subject 설정
    if (subjectSelect) {
      // 로드된 옵션 중에 해당 과목이 있는지 확인
      const optionExists = Array.from(subjectSelect.options).some(
        (opt) => opt.value === subject
      );
      if (optionExists) {
        subjectSelect.value = subject;
        try {
          subjectSelect.dispatchEvent(new Event("change"));
        } catch (e) {}
      }
    }

    // 4. 세부과목 목록 로드 (await)
    await populateSubSubjects(session, subject);

    // 5. SubSubject 설정
    if (subSubjectSelect && subsubject) {
      // 로드된 옵션 중에 해당 세부과목이 있는지 확인
      const subExists = Array.from(subSubjectSelect.options).some(
        (opt) => opt.value === subsubject
      );
      if (subExists) {
        subSubjectSelect.value = subsubject;
        try {
          subSubjectSelect.dispatchEvent(new Event("change"));
        } catch (e) {}
      }
    }

    // 6. 시작
    startQuizFromFilter();
  }

  /**
   * [NEW] Mock Retry 모드 시작 (오답 다시 풀기)
   */
  /**
   * [NEW] Mock Retry 모드 시작 (오답 다시 풀기)
   */
  async function startQuizFromMockRetry(mockExamId, wrongOnly, random) {
    console.log("[PTG Quiz] Mock Retry Start:", {
      mockExamId,
      wrongOnly,
      random,
    });

    // 표준 init() 실행 차단 (중복 실행 방지)
    QuizState.initializing = true;
    QuizState.isInitialized = true;

    // initInterval 정지 시도 (scope 내 변수 접근)
    try {
      if (typeof initInterval !== "undefined" && initInterval) {
        clearInterval(initInterval);
      }
    } catch (e) {}

    const container = document.getElementById("ptg-quiz-container");
    let loader = null;

    if (container) {
      // 기존 필터/그리드 UI 숨기기
      const filterSection = document.getElementById("ptg-quiz-filter-section");
      const gridSection = document.getElementById("ptg-quiz-grid-section");

      if (filterSection) filterSection.style.display = "none";
      if (gridSection) gridSection.style.display = "none";

      // 로딩 메시지 추가
      loader = document.createElement("div");
      loader.id = "ptg-mock-retry-loader";
      loader.className = "ptg-quiz-loading";
      loader.style.padding = "40px";
      loader.style.textAlign = "center";
      loader.innerHTML =
        '<p style="font-size:16px;">오답 문제를 불러오는 중입니다...</p>';
      container.appendChild(loader);
    }

    try {
      const endpoint = "ptg-quiz/v1/questions/mock-retry";
      const query = {
        mock_exam_id: mockExamId,
        wrong_only: wrongOnly,
        random: random,
      };

      const response = await PTGPlatform.get(endpoint, query);

      // 로딩 제거
      if (loader && loader.parentNode) loader.parentNode.removeChild(loader);
      if (!response || !response.success)
        throw new Error(response.message || "데이터 로드 실패");

      const questions = response.data; // Objects array
      if (!Array.isArray(questions) || questions.length === 0) {
        alert("틀린 문제가 없습니다.");
        window.location.href = "/dashboard";
        return;
      }

      console.log("[PTG Quiz] Questions Loaded:", questions.length);

      const ids = questions.map((q) => q.question_id || q.id);

      // 퀴즈 상태 초기화
      QuizState.questions = ids;
      QuizState.currentIndex = 0;
      QuizState.questionId = ids[0];
      QuizState.answers = [];
      QuizState.isAnswered = false;
      QuizState.userAnswer = "";
      QuizState.startTime = Date.now();
      QuizState.mode = "mock_retry";

      // 타이머 설정
      const urlParams = new URL(window.location.href).searchParams;
      const courseStr = String(urlParams.get("course_no") || "");
      let timerMinutes = 50;
      if (courseStr.includes("1")) timerMinutes = 90;
      else if (courseStr.includes("2") || courseStr.includes("3"))
        timerMinutes = 75;

      // 일단 Unlimited 여부 확인
      if (container && container.dataset.unlimited === "1") {
        // Unlimited
      } else {
        // Set timer
        QuizState.timerSeconds = timerMinutes * 60;
        startTimer();
      }

      // 이벤트 리스너 설정 (필수: init 차단으로 인해 수동 호출)
      setupEventListeners();

      setState("running");
      showProgressSection();
      updateProgress(1, ids.length);

      await loadQuestion();
    } catch (e) {
      console.error(e);
      if (loader && loader.parentNode) loader.parentNode.removeChild(loader);
      alert("오류: " + e.message);
      // window.location.href = '/dashboard';
    }
  }

  // DOM 로드 완료 시 초기화
  function autoInit() {
    // 1. Mock Retry 모드 우선 체크 (가장 먼저 실행)
    if (typeof window !== "undefined" && window.location) {
      const urlParams = new URL(window.location.href).searchParams;
      if (urlParams.get("mode") === "mock_retry") {
        const mockExamId = urlParams.get("mock_exam_id");
        if (mockExamId) {
          // startQuizFromMockRetry 호출 후 종료
          startQuizFromMockRetry(
            mockExamId,
            urlParams.get("wrong_only"),
            urlParams.get("random")
          );
          return;
        }
      }
    }

    const container = document.getElementById("ptg-quiz-container");
    if (container) {
      try {
        init();

        // [UI Sync] Checkbox sync from URL
        if (typeof window !== "undefined" && window.location) {
          const urlParams = new URL(window.location.href).searchParams;

          if (
            urlParams.get("review_only") === "1" ||
            urlParams.get("review_only") === "true"
          ) {
            const reviewCheckbox = document.getElementById(
              "ptg-quiz-filter-review"
            );
            if (reviewCheckbox) {
              reviewCheckbox.checked = true;
            }
          }
          if (
            urlParams.get("wrong_only") === "1" ||
            urlParams.get("wrong_only") === "true"
          ) {
            const wrongCheckbox = document.getElementById(
              "ptg-quiz-filter-wrong"
            );
            if (wrongCheckbox) {
              wrongCheckbox.checked = true;
            }
          }

          if (
            urlParams.get("auto_start") === "1" ||
            urlParams.get("auto_start") === "true"
          ) {
            // Wait slightly for init to complete then start
            setTimeout(() => {
              startQuizFromFilter();
            }, 500);
          }
        }
      } catch (e) {
        console.error("[PTG Quiz] 초기화 오류:", e);
      }
    }
  }

  // DOM 로드 후 시도
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      setTimeout(autoInit, 100);
    });
  } else {
    setTimeout(autoInit, 100);
  }

  // 추가 보장: 재시도 (최대 10회)
  var initAttempts = 0;
  var initInterval = setInterval(function () {
    initAttempts++;
    const container = document.getElementById("ptg-quiz-container");
    if (
      container &&
      typeof window.PTGQuiz !== "undefined" &&
      typeof window.PTGQuiz.init === "function"
    ) {
      clearInterval(initInterval);
      try {
        init();
      } catch (e) {
        console.error("[PTG Quiz] 초기화 오류:", e);
      }
    } else if (initAttempts >= 10) {
      console.error("[PTG Quiz] 자동 초기화 실패");
      clearInterval(initInterval);
    }
  }, 500);

  // init() 함수나 초기화 시점에서 모달 존재 확인
})();
