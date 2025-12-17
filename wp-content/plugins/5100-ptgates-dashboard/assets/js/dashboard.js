(function ($) {
  "use strict";

  const Dashboard = {
    greetingCycleIndex: 0,
    init: function () {
      this.$container = $("#ptg-dashboard-app");
      if (this.$container.length === 0) return;

      this.fetchSummary();
      this.bindEvents();
      this.injectStyles();
    },

    injectStyles: function () {
      const styles = `
            /* New Dashboard Grid Layout */
            .ptg-dash-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 16px;
                margin-bottom: 30px;
            }

            .ptg-dash-card {
                background: #ffffff;
                padding: 16px 20px;
                border-radius: 12px;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
                display: flex;
                flex-direction: row; /* Horizontal layout */
                align-items: center;
                justify-content: flex-start;
                text-align: left;
                border: 1px solid #e5e7eb;
                transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s;
                cursor: pointer;
                height: auto;
                min-height: auto; /* Remove fixed height */
                text-decoration: none;
                color: inherit;
            }

            .ptg-dash-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
                border-color: #d1d5db;
            }

            .ptg-card-icon {
                font-size: 1.6rem; /* Smaller icon */
                margin-bottom: 0;
                margin-right: 14px;
                line-height: 1;
                flex-shrink: 0;
            }

            .ptg-card-title {
                font-size: 0.95rem;
                font-weight: 600;
                color: #374151;
                margin-bottom: 0;
                flex: 1; /* Push stat to the right */
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .ptg-card-stat {
                font-size: 0.9rem;
                color: #6b7280;
                font-weight: 500;
                margin-left: 10px;
                white-space: nowrap;
            }

            .ptg-card-stat strong {
                color: #111827;
                font-weight: 700;
            }

            /* Hide progress bar for compact single-row layout */
            .ptg-card-progress {
                display: none; 
            }
            
            /* Responsive */
            @media (max-width: 1024px) {
                .ptg-dash-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }

            @media (max-width: 640px) {
                .ptg-dash-grid {
                    grid-template-columns: 1fr; /* Stack on mobile for better horizontal space */
                    gap: 10px;
                }
                
                .ptg-dash-card {
                    padding: 14px 16px;
                }
            }
            /* Payment Management Styles */
            .ptg-account-link {
                background: none;
                border: 1px solid #e5e7eb;
                font: inherit;
                cursor: pointer;
                width: 100%;
                text-align: left;
            }

            .ptg-pm-tabs {
                display: flex;
                gap: 10px;
                margin-bottom: 20px;
                border-bottom: 1px solid #e5e7eb;
            }

            .ptg-pm-tab {
                padding: 10px 20px;
                background: none;
                border: none;
                border-bottom: 2px solid transparent;
                font-size: 15px;
                font-weight: 600;
                color: #6b7280;
                cursor: pointer;
                transition: all 0.2s;
            }

            .ptg-pm-tab:hover {
                color: #374151;
            }

            .ptg-pm-tab.is-active {
                color: #4f46e5;
                border-bottom-color: #4f46e5;
            }

            .ptg-pm-content {
                display: none;
                animation: ptg-fade-in 0.3s ease;
            }

            .ptg-pm-content.is-active {
                display: block;
            }

            @keyframes ptg-fade-in {
                from { opacity: 0; transform: translateY(5px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* History List */
            .ptg-history-list {
                border-top: 1px solid #e5e7eb;
            }

            .ptg-history-item {
                padding: 16px 0;
                border-bottom: 1px solid #e5e7eb;
                display: flex;
                flex-direction: column;
                gap: 4px;
                font-size: 14px;
                color: #374151;
            }

            .ptg-history-item.ptg-history-empty {
                text-align: center;
                padding: 40px 0;
                color: #6b7280;
                font-style: italic;
            }

            .ptg-history-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .ptg-history-date {
                font-size: 13px;
                color: #6b7280;
            }

            .ptg-history-product {
                font-weight: 600;
                color: #111827;
                font-size: 15px;
            }

            .ptg-history-amount {
                font-weight: 600;
                color: #374151;
            }

            .ptg-history-status {
                font-size: 13px;
                padding: 2px 8px;
                border-radius: 9999px;
                background-color: #f3f4f6;
                color: #4b5563;
            }
            .ptg-history-status.completed {
                background-color: #dcfce7;
                color: #166534;
            }

            /* User Provided Styles (Scoped) */
            .ptg-membership-wrapper {
                font-family: "Pretendard", "Noto Sans KR", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
                color: #1f2933;
                line-height: 1.7;
            }

            .ptg-section-title {
                font-size: 24px;
                font-weight: 700;
                color: #4c51bf;
                margin-bottom: 8px;
            }

            .ptg-section-sub {
                font-size: 16px;
                color: #4b5563;
                margin-bottom: 32px;
            }

            .ptg-section-sub b {
                font-weight: 600;
            }

            .ptg-card {
                background: #ffffff;
                border-radius: 18px;
                padding: 24px 24px 28px;
                box-shadow: 0 18px 45px rgba(15, 23, 42, 0.09);
                border: 1px solid rgba(148, 163, 184, 0.3);
                margin-bottom: 24px;
                position: relative;
                overflow: hidden;
            }

            .ptg-card::before {
                content: "";
                position: absolute;
                inset: 0;
                background: radial-gradient(circle at top left, rgba(129, 140, 248, 0.18), transparent 52%);
                pointer-events: none;
                z-index: 0;
            }

            .ptg-card-inner {
                position: relative;
                z-index: 1;
            }

            .ptg-plan-title {
                font-size: 18px;
                font-weight: 700;
                margin-bottom: 8px;
            }

            .ptg-plan-title span {
                font-size: 14px;
                font-weight: 600;
            }

            .ptg-plan-title .badge-free {
                color: #10b981;
            }

            .ptg-plan-title .badge-premium {
                color: #7c3aed;
            }

            .ptg-plan-sub {
                font-size: 14px;
                color: #6b7280;
                margin-bottom: 12px;
            }

            .ptg-price {
                font-size: 15px;
                font-weight: 600;
                color: #111827;
                margin-bottom: 12px;
            }

            .ptg-price small {
                font-size: 13px;
                font-weight: 500;
                color: #6b21a8;
            }

            .ptg-benefits {
                list-style: none;
                padding-left: 0;
                margin: 0;
            }

            .ptg-benefits li {
                position: relative;
                padding-left: 18px;
                font-size: 14px;
                color: #374151;
                margin-bottom: 4px;
            }

            .ptg-benefits li::before {
                content: "•";
                position: absolute;
                left: 4px;
                top: 0;
                color: #a855f7;
            }

            .ptg-badge-line {
                font-size: 13px;
                color: #6b7280;
                margin-top: 12px;
            }

            .ptg-badge-line strong {
                color: #111827;
                font-weight: 600;
            }

            .ptg-premium-highlight {
                background: linear-gradient(135deg, #ede9fe, #e0f2fe);
                border-radius: 12px;
                padding: 10px 12px;
                font-size: 13px;
                color: #4b5563;
                margin-top: 14px;
            }

            .ptg-premium-highlight b {
                color: #7c3aed;
            }

            .ptg-note {
                font-size: 13px;
                color: #6b7280;
                margin-top: 10px;
            }

            /* 제한 초과 유입용 히어로 섹션 */
            .ptg-limit-hero {
                background: linear-gradient(135deg, #eef2ff, #fdf2ff);
                border-radius: 18px;
                padding: 20px 22px 22px;
                border: 1px solid rgba(129, 140, 248, 0.35);
                box-shadow: 0 18px 40px rgba(79, 70, 229, 0.17);
                margin-bottom: 28px;
                position: relative;
                overflow: hidden;
            }

            .ptg-limit-hero::before {
                content: "";
                position: absolute;
                width: 220px;
                height: 220px;
                background: radial-gradient(circle, rgba(129, 140, 248, 0.32), transparent 60%);
                right: -80px;
                top: -80px;
                opacity: 0.9;
                pointer-events: none;
            }

            .ptg-limit-title {
                position: relative;
                z-index: 1;
                font-size: 18px;
                font-weight: 700;
                color: #1f2937;
                margin-bottom: 6px;
            }

            .ptg-limit-text {
                position: relative;
                z-index: 1;
                font-size: 14px;
                color: #4b5563;
                margin-bottom: 10px;
            }

            .ptg-limit-list {
                position: relative;
                z-index: 1;
                font-size: 13px;
                color: #374151;
                margin: 0 0 14px;
                padding-left: 18px;
            }

            .ptg-limit-list li {
                margin-bottom: 3px;
            }

            .ptg-cta-row {
                position: relative;
                z-index: 1;
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 10px;
            }

            .ptg-cta-btn {
                display: inline-block;
                padding: 8px 16px;
                border-radius: 999px;
                background: linear-gradient(135deg, #4f46e5, #7c3aed);
                color: #ffffff;
                font-size: 14px;
                font-weight: 600;
                text-decoration: none;
                box-shadow: 0 10px 25px rgba(79, 70, 229, 0.4);
                border: none;
                cursor: pointer;
                transition: transform 0.08s ease, box-shadow 0.08s ease, opacity 0.08s ease;
            }

            .ptg-cta-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 14px 30px rgba(79, 70, 229, 0.5);
                opacity: 0.96;
            }

            .ptg-cta-caption {
                font-size: 12px;
                color: #4b5563;
            }

            .ptg-btn-secondary {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 6px 16px;
                background-color: #ffffff;
                color: #6b7280;
                border: 1px solid #e5e7eb;
                border-radius: 999px;
                font-size: 13px;
                font-weight: 400;
                text-decoration: none;
                cursor: pointer;
                transition: all 0.2s;
            }

            .ptg-btn-secondary:hover {
                background-color: #f9fafb;
                border-color: #d1d5db;
                color: #374151;
            }

            /* Plan Selection Card (Gradient Wrapper) */
            .ptg-plan-selection-card {
                background: #ffffff;
                border-radius: 20px;
                padding: 30px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
                border: 1px solid rgba(229, 231, 235, 0.5);
                position: relative;
                overflow: hidden;
            }
            .ptg-plan-selection-card::before {
                content: "";
                position: absolute;
                inset: 0;
                background: radial-gradient(800px circle at top left, rgba(129, 140, 248, 0.12), transparent 40%);
                pointer-events: none;
                z-index: 0;
            }

            .ptg-link-arrow {
                display: inline-block;
                width: 6px;
                height: 6px;
                border-right: 2px solid #9ca3af;
                border-bottom: 2px solid #9ca3af;
                transform: rotate(45deg);
                margin-left: auto;
                transition: transform 0.2s;
                margin-bottom: 2px;
            }
            
            button[data-toggle-payment][aria-expanded="true"] .ptg-link-arrow {
                transform: rotate(-135deg);
                margin-bottom: -2px;
            }

            /* 플랜 비교표 */
            .ptg-plan-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 10px;
                margin-top: 12px;
            }

            .ptg-plan-cell {
                background: #f9fafb;
                border-radius: 14px;
                padding: 10px 10px 12px;
                border: 1px solid rgba(148, 163, 184, 0.4);
                font-size: 13px;
                cursor: pointer;
                transition: all 0.2s;
            }

            .ptg-plan-cell:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                border-color: #4f46e5;
            }

            .ptg-plan-cell.popular {
                border-color: #7c3aed;
                box-shadow: 0 10px 30px rgba(124, 58, 237, 0.25);
                background: linear-gradient(135deg, #f5f3ff, #eef2ff);
                position: relative;
            }

            .ptg-plan-name {
                font-weight: 700;
                margin-bottom: 4px;
                color: #111827;
            }

            .ptg-plan-month {
                font-size: 12px;
                color: #6b7280;
                margin-bottom: 6px;
            }

            .ptg-plan-price {
                font-size: 14px;
                font-weight: 700;
                color: #111827;
                margin-bottom: 4px;
            }

            .ptg-plan-monthly {
                font-size: 12px;
                color: #6b7280;
            }

            .ptg-plan-tag {
                display: inline-block;
                margin-top: 6px;
                padding: 2px 8px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 600;
            }

            .ptg-plan-tag.popular-tag {
                background: rgba(124, 58, 237, 0.1);
                color: #7c3aed;
            }

            .ptg-plan-tag.value-tag {
                background: rgba(16, 185, 129, 0.08);
                color: #059669;
            }

            /* 반응형 */
            @media (max-width: 768px) {
                .ptg-membership-wrapper {
                    margin-top: 24px;
                }
                .ptg-card {
                    padding: 20px 18px 22px;
                }
                .ptg-limit-hero {
                    padding: 18px 16px 20px;
                }
                .ptg-cta-row {
                    flex-direction: column;
                    align-items: flex-start;
                }
                .ptg-plan-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 480px) {
                .ptg-plan-grid {
                    grid-template-columns: 1fr;
                }
            }
        `;
      $("<style>").text(styles).appendTo("head");
    },

    bindEvents: function () {
      // Quick Actions
      this.$container.on("click", "[data-action], [data-url]", function (e) {
        e.preventDefault();
        const action = $(this).data("action");
        const url = $(this).data("url");

        if (action === "scroll-top") {
          const target = document.querySelector(".e-n-tabs-heading");
          if (target) {
            target.scrollIntoView({ behavior: "smooth", block: "start" });
          } else {
            console.warn("Target element .e-n-tabs-heading not found");
          }
          return;
        }

        if (url) {
          window.location.href = url;
        }
      });

      // Learning Day 카드 선택 효과
      this.$container.on("click", ".ptg-learning-day", function (e) {
        e.stopPropagation();
        const $day = $(this);
        // 같은 카드 내의 다른 day는 선택 해제
        $day.siblings(".ptg-learning-day").removeClass("is-active");
        // 현재 카드 토글
        $day.toggleClass("is-active");
      });

      // 과목별 학습 기록 - 세부과목 클릭 시 Study 페이지로 이동
      this.$container.on(
        "click",
        ".ptg-dash-learning .ptg-subject-item",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          const $item = $(this);
          // 세부과목명을 직접 텍스트에서 가져오기 (가장 안전한 방법)
          const subjectName = $item.find(".ptg-subject-name").text().trim();
          if (subjectName) {
            // Study 페이지 URL 가져오기 (PHP에서 전달된 값 사용)
            let studyBaseUrl =
              (window.ptg_dashboard_vars &&
                window.ptg_dashboard_vars.study_url) ||
              "";

            // Study URL이 없으면 fallback으로 /ptg_study/ 사용
            if (!studyBaseUrl || studyBaseUrl === "#" || studyBaseUrl === "") {
              studyBaseUrl = "/ptg_study/";
              console.warn(
                "Dashboard: Study page URL not found, using fallback /ptg_study/. Please ensure a page with [ptg_study] shortcode exists."
              );
            }

            // 1100 Study 플러그인과 동일한 방식으로 URL 파라미터 추가
            // URLSearchParams를 사용하여 쿼리 파라미터 구성
            const url = new URL(studyBaseUrl, window.location.origin);
            url.searchParams.set("subject", subjectName); // encodeURIComponent는 URLSearchParams가 자동 처리
            const finalUrl = url.toString();

            // 디버깅용 로그 (개발 환경에서만)
            if (window.console && window.console.log) {
              console.log("Dashboard: Navigating to Study page", {
                studyBaseUrl: studyBaseUrl,
                subjectName: subjectName,
                finalUrl: finalUrl,
              });
            }

            window.location.href = finalUrl;
          } else {
            console.warn(
              "Dashboard: subject name not found on clicked item",
              $item
            );
          }
        }
      );

      // Payment Management Toggle
      this.$container.on("click", "[data-toggle-payment]", function (e) {
        e.preventDefault();
        const $btn = $(this);
        const $section = $("#ptg-payment-management");

        if ($section.is(":visible")) {
          $section.hide();
          $btn.attr("aria-expanded", "false");
        } else {
          $section.show();
          $btn.attr("aria-expanded", "true");
          // Smooth scroll
          $section[0].scrollIntoView({ behavior: "smooth", block: "start" });
        }
      });

      // Payment Management Tabs
      this.$container.on("click", "[data-pm-tab]", function (e) {
        e.preventDefault();
        const tabName = $(this).data("pm-tab");

        // Update Tabs
        $(".ptg-pm-tab").removeClass("is-active");
        $(this).addClass("is-active");

        // Update Content
        $(".ptg-pm-content").removeClass("is-active");
        $("#ptg-pm-content-" + tabName).addClass("is-active");
      });

      // 사용자 데이터 초기화 버튼
      const self = this;
      $(document).on("click", "#ptg-reset-user-data-btn", function (e) {
        e.preventDefault();
        e.stopPropagation();

        const confirmed = confirm(
          "모든 학습 기록과 데이터가 삭제됩니다.\n\n" +
            "삭제되는 데이터:\n" +
            "- 학습 기록 (ptgates_user_states)\n" +
            "- 퀴즈 결과 (ptgates_user_results)\n" +
            "- 암기카드 (ptgates_flashcards, ptgates_flashcard_sets)\n" +
            "- 마이노트 메모 (ptgates_user_memos)\n" +
            "- 드로잉 데이터 (ptgates_user_drawings)\n\n" +
            "이 작업은 되돌릴 수 없습니다. 계속하시겠습니까?"
        );

        if (!confirmed) {
          return;
        }

        self.resetUserData();
      });
    },

    /**
     * 결제 시작 (PC/Mobile 분기)
     */
    initiatePayment: function (productCode, price, productName) {
      if (
        !confirm(
          productName +
            " (" +
            price.toLocaleString() +
            "원)을 결제하시겠습니까?"
        )
      ) {
        return;
      }

      // 1. Device Check
      var isMobile =
        /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
          navigator.userAgent
        );
      var deviceType = isMobile ? "mobile" : "pc";

      // 2. Loading UI
      var overlay = document.createElement("div");
      overlay.id = "ptg-pay-loading";
      overlay.style.cssText =
        "position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.8);z-index:99999;display:flex;justify-content:center;align-items:center;font-size:18px;font-weight:bold;";
      overlay.innerHTML = "결제 준비 중입니다...";
      document.body.appendChild(overlay);

      // 3. API Call
      $.ajax({
        url: "/wp-json/ptg-dash/v1/payment/prepare",
        method: "POST",
        headers: {
          "X-WP-Nonce": window.ptg_dashboard_vars.nonce || "",
        },
        data: {
          product_code: productCode,
          device_type: deviceType,
        },
        success: function (response) {
          if (document.getElementById("ptg-pay-loading"))
            document.body.removeChild(
              document.getElementById("ptg-pay-loading")
            );

          // Form 찾기
          // dashboard.js가 실행되는 페이지에 폼이 없을 수 있으므로 동적 생성
          let form = document.getElementById("ptg-payment-form");
          if (!form) {
            // Create Form if not exists
            const formHtml = `
                    <form id="ptg-payment-form" method="POST" style="display:none;">
                        <!-- StdPay PC Fields -->
                        <input type="hidden" name="version" >
                        <input type="hidden" name="gopaymethod" >
                        <input type="hidden" name="mid" >
                        <input type="hidden" name="oid" >
                        <input type="hidden" name="price" >
                        <input type="hidden" name="timestamp" >
                        <input type="hidden" name="use_chkfake" >
                        <input type="hidden" name="signature" >
                        <input type="hidden" name="verification" >
                        <input type="hidden" name="mKey" >
                        <input type="hidden" name="currency" >
                        <input type="hidden" name="goodname" >
                        <input type="hidden" name="buyername" >
                        <input type="hidden" name="buyertel" >
                        <input type="hidden" name="buyeremail" >
                        <input type="hidden" name="returnUrl" >
                        <input type="hidden" name="closeUrl" >
                        <input type="hidden" name="acceptmethod" >
                        <input type="hidden" name="payViewType" value="overlay">
                        <input type="hidden" name="charset" value="UTF-8">
                        
                        <!-- Mobile Specific (Smart Pay) -->
                        <input type="hidden" name="P_MID" >
                        <input type="hidden" name="P_OID" >
                        <input type="hidden" name="P_AMT" >
                        <input type="hidden" name="P_UNAME" >
                        <input type="hidden" name="P_GOODS" >
                        <input type="hidden" name="P_NEXT_URL" >
                        <input type="hidden" name="P_NOTI_URL" >
                        <input type="hidden" name="P_HPP_METHOD" value="1">
                    </form>`;
            $("body").append(formHtml);
            form = document.getElementById("ptg-payment-form");
          }

          if (deviceType === "mobile") {
            form.action = "https://stgmobile.inicis.com/smart/payment/";
            form.acceptCharset = "UTF-8";

            if (response.mid) form.P_MID.value = response.mid;
            if (response.oid) form.P_OID.value = response.oid;
            if (response.price) form.P_AMT.value = response.price;
            if (response.buyername) form.P_UNAME.value = response.buyername;
            if (response.goodname) form.P_GOODS.value = response.goodname;
            if (response.P_NEXT_URL)
              form.P_NEXT_URL.value = response.P_NEXT_URL;
            if (response.P_NOTI_URL)
              form.P_NOTI_URL.value = response.P_NOTI_URL;

            form.submit();
          } else {
            // Check INIStdPay
            if (typeof INIStdPay === "undefined") {
              // Load Script Dynamically
              $.getScript("https://stgstdpay.inicis.com/stdjs/INIStdPay.js")
                .done(function () {
                  executePcPayment(form, response);
                })
                .fail(function () {
                  alert("결제 모듈 로드 실패");
                });
            } else {
              executePcPayment(form, response);
            }
          }
        },
        error: function (xhr) {
          if (document.getElementById("ptg-pay-loading"))
            document.body.removeChild(
              document.getElementById("ptg-pay-loading")
            );
          alert(
            "오류 발생: " +
              (xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText)
          );
        },
      });

      function executePcPayment(form, response) {
        form.version.value = response.version || "1.0";
        form.gopaymethod.value = response.gopaymethod || "Card";
        form.mid.value = response.mid;
        form.oid.value = response.oid;
        form.price.value = response.price;
        form.timestamp.value = response.timestamp;
        form.use_chkfake.value = response.use_chkfake || "Y";
        form.signature.value = response.signature;
        form.verification.value = response.verification;
        form.mKey.value = response.mKey;
        form.currency.value = response.currency || "WON";
        form.goodname.value = response.goodname;
        form.buyername.value = response.buyername;
        form.buyertel.value = response.buyertel || "010-0000-0000"; // 임시
        form.buyeremail.value = response.buyeremail;
        form.returnUrl.value = response.returnUrl;
        form.closeUrl.value = response.closeUrl;
        form.acceptmethod.value = response.acceptmethod || "centerCd(Y)";

        try {
          INIStdPay.pay("ptg-payment-form");
        } catch (e) {
          alert("결제 모듈 실행 오류: " + e.message);
        }
      }
    },

    openPricingGuide: function () {
      const modalId = "ptg-pricing-modal";
      if ($("#" + modalId).length > 0) {
        $("#" + modalId).fadeIn();
        return;
      }

      // Fetch the HTML content
      $.get(
        "/wp-content/plugins/5100-ptgates-dashboard/assets/html/pricing-guide.html",
        function (data) {
          const parser = new DOMParser();
          const doc = parser.parseFromString(data, "text/html");

          // Extract styles and remove body selector to prevent global override
          let styles = doc.querySelector("style").innerHTML;
          styles = styles.replace(/body\s*{[^}]*}/, "");

          const content = doc.querySelector(
            ".ptg-membership-wrapper"
          ).outerHTML;

          // Create Modal HTML
          const modalHtml = `
                <div id="${modalId}" class="ptg-modal-overlay" style="display:none;">
                    <div class="ptg-modal-container">
                        <button type="button" class="ptg-modal-close">×</button>
                        <div class="ptg-modal-content">
                            ${content}
                        </div>
                    </div>
                </div>
            `;

          // Inject Styles
          $("head").append(`<style>${styles}</style>`);
          $("head").append(`
                <style>
                    .ptg-modal-overlay {
                        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                        background: rgba(0, 0, 0, 0.5); z-index: 9999;
                        display: flex; justify-content: center; align-items: center;
                        backdrop-filter: blur(5px);
                    }
                    .ptg-modal-container {
                        background: #f5f7fb; width: 90%; max-width: 1000px; max-height: 90vh;
                        border-radius: 12px; position: relative; overflow: hidden;
                        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                        display: flex; flex-direction: column;
                    }
                    .ptg-modal-close {
                        position: absolute; top: 15px; right: 20px;
                        background: none; border: none; font-size: 28px; color: #6b7280;
                        cursor: pointer; z-index: 10; line-height: 1;
                    }
                    .ptg-modal-content {
                        overflow-y: auto; padding: 20px; height: 100%;
                    }
                    /* Override wrapper margin for modal */
                    .ptg-modal-content .ptg-membership-wrapper {
                        margin: 0 auto !important;
                        padding: 20px 0 !important;
                    }
                </style>
            `);

          $("body").append(modalHtml);

          const $modal = $("#" + modalId);

          // Close function
          const closeModal = () => {
            $modal.fadeOut();
            $(document).off("keydown.ptgModal");
          };

          // Event Handlers
          $modal.on("click", function (e) {
            if (e.target === this) closeModal();
          });

          $modal.find(".ptg-modal-close").on("click", closeModal);

          $(document).on("keydown.ptgModal", function (e) {
            if (e.key === "Escape") closeModal();
          });

          $modal.fadeIn();
        }
      );
    },

    resetUserData: function () {
      const self = this;
      const restUrl = window.ptg_dashboard_vars
        ? window.ptg_dashboard_vars.rest_url
        : "/wp-json/ptg-dash/v1/";
      const nonce = window.ptg_dashboard_vars
        ? window.ptg_dashboard_vars.nonce
        : "";

      const $btn = $("#ptg-reset-user-data-btn");
      const originalText = $btn.text();

      // 버튼 비활성화
      $btn.prop("disabled", true).text("처리 중...");

      $.ajax({
        url: restUrl + "reset-user-data",
        method: "POST",
        dataType: "json",
        beforeSend: function (xhr) {
          if (nonce) {
            xhr.setRequestHeader("X-WP-Nonce", nonce);
          }
        },
        success: function (response) {
          if (response && response.success) {
            // window.ptg_dashboard_vars 업데이트 (즉시 반영)
            if (window.ptg_dashboard_vars) {
              window.ptg_dashboard_vars.study_count = 0;
              window.ptg_dashboard_vars.quiz_count = 0;
              window.ptg_dashboard_vars.flashcard_count = 0; // 암기카드도 삭제됨
            }

            // 멤버십 섹션의 "학습 이용 현황" 숫자 즉시 업데이트
            const $usageItems = $("#ptg-membership-details .ptg-usage-item");
            if ($usageItems.length >= 2) {
              // 과목|Study 업데이트
              const $studyValue = $usageItems.eq(0).find(".ptg-usage-value");
              if ($studyValue.length) {
                const studyLimit =
                  window.ptg_dashboard_vars?.study_limit >= 999999
                    ? "무제한"
                    : (
                        window.ptg_dashboard_vars?.study_limit || 0
                      ).toLocaleString();
                $studyValue.html(`0 / ${studyLimit}`);
              }

              // 실전|Quiz 업데이트
              const $quizValue = $usageItems.eq(1).find(".ptg-usage-value");
              if ($quizValue.length) {
                const quizLimit =
                  window.ptg_dashboard_vars?.quiz_limit >= 999999
                    ? "무제한"
                    : (
                        window.ptg_dashboard_vars?.quiz_limit || 0
                      ).toLocaleString();
                $quizValue.html(`0 / ${quizLimit}`);
              }

              // 암기카드 업데이트
              const $flashcardValue = $usageItems
                .eq(2)
                .find(".ptg-usage-value");
              if ($flashcardValue.length) {
                const flashcardLimit =
                  window.ptg_dashboard_vars?.flashcard_limit >= 999999
                    ? "무제한"
                    : (
                        window.ptg_dashboard_vars?.flashcard_limit || 0
                      ).toLocaleString();
                $flashcardValue.html(`0 / ${flashcardLimit}`);
              }
            }

            // REST API로 최신 데이터 다시 가져오기 (캐시 무시)
            self.fetchSummary();

            alert("데이터가 성공적으로 초기화되었습니다.");
            $btn.prop("disabled", false).text(originalText);
          } else {
            alert(
              response && response.message
                ? response.message
                : "데이터 초기화 중 오류가 발생했습니다."
            );
            $btn.prop("disabled", false).text(originalText);
          }
        },
        error: function (xhr, status, error) {
          let errorMessage = "데이터 초기화 중 오류가 발생했습니다.";

          try {
            if (xhr.responseText) {
              const errorData = JSON.parse(xhr.responseText);
              if (errorData && errorData.message) {
                errorMessage = errorData.message;
              }
            }
          } catch (e) {
            console.error("Error parsing response:", e);
          }

          alert(errorMessage);
          $btn.prop("disabled", false).text(originalText);
        },
      });
    },

    fetchSummary: function () {
      const self = this;
      const restUrl = window.ptg_dashboard_vars
        ? window.ptg_dashboard_vars.rest_url
        : "/wp-json/ptg-dash/v1/";
      const nonce = window.ptg_dashboard_vars
        ? window.ptg_dashboard_vars.nonce
        : "";

      // 캐시 무시를 위한 타임스탬프 추가 (초기화 후 즉시 반영)
      const cacheBuster = new Date().getTime();

      $.ajax({
        url: restUrl + "summary?force_refresh=1&_t=" + cacheBuster,
        method: "GET",
        dataType: "json",
        beforeSend: function (xhr) {
          if (nonce) {
            xhr.setRequestHeader("X-WP-Nonce", nonce);
          }
        },
        success: function (data) {
          if (data && typeof data === "object") {
            // window.ptg_dashboard_vars 업데이트 (최신 데이터 반영)
            if (window.ptg_dashboard_vars) {
              if (data.flashcard && data.flashcard.total !== undefined) {
                window.ptg_dashboard_vars.flashcard_count =
                  data.flashcard.total || 0;
              }
              if (data.study_progress !== undefined) {
                window.ptg_dashboard_vars.study_count =
                  data.study_progress || 0;
              }
              if (data.quiz_progress !== undefined) {
                window.ptg_dashboard_vars.quiz_count = data.quiz_progress || 0;
              }
            }

            self.render(data);

            // render() 후 멤버십 섹션의 숫자 다시 업데이트 (render()가 전체를 다시 렌더링하므로)
            if (window.ptg_dashboard_vars) {
              const $usageItems = $("#ptg-membership-details .ptg-usage-item");
              if ($usageItems.length >= 3) {
                // 과목|Study 업데이트
                const $studyValue = $usageItems.eq(0).find(".ptg-usage-value");
                if ($studyValue.length) {
                  const studyLimit =
                    window.ptg_dashboard_vars?.study_limit >= 999999
                      ? "무제한"
                      : (
                          window.ptg_dashboard_vars?.study_limit || 0
                        ).toLocaleString();
                  $studyValue.html(
                    `${(
                      window.ptg_dashboard_vars?.study_count || 0
                    ).toLocaleString()} / ${studyLimit}`
                  );
                }

                // 실전|Quiz 업데이트
                const $quizValue = $usageItems.eq(1).find(".ptg-usage-value");
                if ($quizValue.length) {
                  const quizLimit =
                    window.ptg_dashboard_vars?.quiz_limit >= 999999
                      ? "무제한"
                      : (
                          window.ptg_dashboard_vars?.quiz_limit || 0
                        ).toLocaleString();
                  $quizValue.html(
                    `${(
                      window.ptg_dashboard_vars?.quiz_count || 0
                    ).toLocaleString()} / ${quizLimit}`
                  );
                }

                // 암기카드 업데이트
                const $flashcardValue = $usageItems
                  .eq(2)
                  .find(".ptg-usage-value");
                if ($flashcardValue.length) {
                  const flashcardLimit =
                    window.ptg_dashboard_vars?.flashcard_limit >= 999999
                      ? "무제한"
                      : (
                          window.ptg_dashboard_vars?.flashcard_limit || 0
                        ).toLocaleString();
                  $flashcardValue.html(
                    `${(
                      window.ptg_dashboard_vars?.flashcard_count || 0
                    ).toLocaleString()} / ${flashcardLimit}`
                  );
                }
              }
            }
          } else {
            console.error("Invalid response data:", data);
            self.$container.html("<p>데이터 형식이 올바르지 않습니다.</p>");
          }
        },
        error: function (xhr, status, error) {
          // 상세 에러 로깅
          console.error("Dashboard fetch error details:", {
            status: xhr.status,
            statusText: xhr.statusText,
            responseText: xhr.responseText
              ? xhr.responseText.substring(0, 500)
              : "No response text",
            error: error,
            url: restUrl + "summary",
          });

          let errorMessage = "데이터를 불러오는 중 오류가 발생했습니다.";

          // JSON 응답 파싱 시도
          try {
            if (xhr.responseText) {
              const errorData = JSON.parse(xhr.responseText);
              if (errorData) {
                if (errorData.message) {
                  errorMessage = errorData.message;
                } else if (errorData.code) {
                  errorMessage = "오류 코드: " + errorData.code;
                }
              }
            }
          } catch (e) {
            console.error("Error parsing error response:", e);
            // HTML 응답일 경우 (예: PHP Fatal Error)
            if (xhr.responseText && xhr.responseText.includes("<")) {
              errorMessage += " (서버 오류)";
            }
          }

          // 상태 코드별 메시지
          if (xhr.status === 401 || xhr.status === 403) {
            errorMessage = "로그인이 필요하거나 권한이 없습니다.";
          } else if (xhr.status === 404) {
            errorMessage = "API 엔드포인트를 찾을 수 없습니다.";
          } else if (xhr.status === 500) {
            errorMessage = "서버 내부 오류가 발생했습니다.";
          }

          self.$container.html(`
                        <div class="ptg-error-message">
                            <p>⚠️ ${errorMessage}</p>
                            <small>상태: ${xhr.status} ${xhr.statusText}</small>
                        </div>
                    `);
        },
      });
    },

    render: function (data) {
      const {
        user_name,
        premium,
        today_reviews,
        progress,
        recent_activity,
        bookmarks,
        study_progress,
        flashcard,
        mynote_count,
      } = data;
      const learningRecords = data.learning_records || { subjects: [] };

      // Calculate Percentages
      const totalQuestions = progress.total || 1; // Avoid division by zero

      // Billing History HTML Generation
      const billingHistory = Array.isArray(data.billing_history)
        ? data.billing_history
        : [];

      const historyHtml =
        billingHistory.length > 0
          ? billingHistory
              .map((item) => {
                const date = new Date(item.transaction_date);
                const formattedDate =
                  date.getFullYear() +
                  "." +
                  String(date.getMonth() + 1).padStart(2, "0") +
                  "." +
                  String(date.getDate()).padStart(2, "0") +
                  " " +
                  String(date.getHours()).padStart(2, "0") +
                  ":" +
                  String(date.getMinutes()).padStart(2, "0");

                const statusMap = {
                  paid: "결제완료",
                  failed: "실패",
                  refunded: "환불",
                  pending: "대기",
                };
                const statusText = statusMap[item.status] || item.status;
                const statusClass = item.status === "paid" ? "completed" : "";
                const amount = Number(item.amount).toLocaleString();

                // Expiry Date Formatting
                let expiryHtml = "";
                if (item.expiry_date) {
                  const expDate = new Date(item.expiry_date);
                  const formattedExp =
                    expDate.getFullYear() +
                    "." +
                    String(expDate.getMonth() + 1).padStart(2, "0") +
                    "." +
                    String(expDate.getDate()).padStart(2, "0");
                  expiryHtml = ` <span style="color: #6b7280; font-weight: normal; font-size: 13px;">(~ ${formattedExp})</span>`;
                }

                return `
                <div class="ptg-history-item">
                    <div class="ptg-history-row">
                        <span class="ptg-history-date">${this.escapeHtml(
                          formattedDate
                        )}</span>
                        <span class="ptg-history-status ${statusClass}">${this.escapeHtml(
                  statusText
                )}</span>
                    </div>
                    <div class="ptg-history-row" style="margin-top: 6px;">
                        <span class="ptg-history-product">
                            ${this.escapeHtml(item.product_name)}
                            ${expiryHtml}
                        </span>
                        <span class="ptg-history-amount">${amount}원</span>
                    </div>
                </div>
            `;
              })
              .join("")
          : `
            <div class="ptg-history-item ptg-history-empty">
                아직 결제 내역이 없습니다.
            </div>
        `;

      // 1. Study Progress: (study_count > 0) / totalQuestions
      const studyPercent = Math.min(
        100,
        Math.round(((study_progress || 0) / totalQuestions) * 100)
      );

      // 2. Mock Exam (Quiz) Progress: Same as Overall Progress (solved / total)
      const quizPercent = progress.percent || 0;

      // 3. Flashcard Progress: (Total - Due) / Total (Retention Rate)
      // If total is 0, progress is 0.
      let flashcardPercent = 0;
      if (flashcard && flashcard.total > 0) {
        flashcardPercent = Math.min(
          100,
          Math.round(
            ((flashcard.total - flashcard.due) / flashcard.total) * 100
          )
        );
      }

      // 1. Welcome Section
      const greeting = this.getGreeting();
      const greetingText = this.escapeHtml(greeting.text);
      const greetingAttr = greeting.translation
        ? ` data-translation="${this.escapeHtml(greeting.translation)}"`
        : "";
      const greetingHtml = `<span class="ptg-greeting ${
        greeting.isEnglish ? "is-english" : ""
      }"${greetingAttr}>${greetingText}</span>`;

      // 멤버십 등급 라벨 (API에서 전달받은 값 사용)
      // 모든 등급에서 "멤버십" 단어 제거
      const membershipLabel = premium.grade
        ? premium.grade
        : premium.status === "active"
        ? "Premium"
        : "Free";

      const welcomeHtml = `
                <div class="ptg-dash-welcome">
                    <div class="ptg-welcome-text">
                        <h2>${this.formatName(user_name)}님,</h2>
                        <div class="ptg-greeting-wrapper">${greetingHtml}</div>
                    </div>
                    <div id="ptg-membership-toggle" class="ptg-dash-premium-badge ${
                      premium.status === "active" ? "is-active" : "is-free"
                    }" style="cursor: pointer;" onclick="document.getElementById('ptg-membership-details').style.display = document.getElementById('ptg-membership-details').style.display === 'none' ? 'block' : 'none';">
                        <span class="ptg-badge-label">
                            ${membershipLabel}
                            <span class="ptg-settings-icon">⚙️</span>
                        </span>
                        ${
                          premium.expiry
                            ? `<small>(${premium.expiry} 만료)</small>`
                            : ""
                        }
                    </div>
                </div>

                <!-- Membership Details Toggle Section -->
                <div id="ptg-membership-details" style="display: none;">
                            <!-- Usage Limits -->
                    <section class="ptg-mb-section">
                        <div class="ptg-mb-section-header">
                            <h2 class="ptg-mb-section-title">📊 학습 이용 현황</h2>
                            ${
                              premium.status === "active" &&
                              (premium.grade === "Premium" ||
                                premium.grade === "Admin")
                                ? `<button type="button" id="ptg-reset-user-data-btn" class="ptg-reset-data-btn" title="모든 학습 기록과 데이터를 초기화합니다">
                                    초기화
                                  </button>`
                                : ""
                            }
                        </div>
                        <div class="ptg-usage-grid">
                            <div class="ptg-usage-item">
                                <span class="ptg-usage-label">과목|Study</span>
                                <div class="ptg-usage-value">
                                    ${(
                                      window.ptg_dashboard_vars?.study_count ||
                                      0
                                    ).toLocaleString()} / ${
        window.ptg_dashboard_vars?.study_limit === -1 ||
        window.ptg_dashboard_vars?.study_limit >= 999999
          ? "무제한"
          : (window.ptg_dashboard_vars?.study_limit || 0).toLocaleString()
      }
                                </div>
                            </div>
                            <div class="ptg-usage-item">
                                <span class="ptg-usage-label">실전|Quiz</span>
                                <div class="ptg-usage-value">
                                    ${(
                                      window.ptg_dashboard_vars?.quiz_count || 0
                                    ).toLocaleString()} / ${
        window.ptg_dashboard_vars?.quiz_limit === -1 ||
        window.ptg_dashboard_vars?.quiz_limit >= 999999
          ? "무제한"
          : (window.ptg_dashboard_vars?.quiz_limit || 0).toLocaleString()
      }
                                </div>
                            </div>
                            <div class="ptg-usage-item">
                                <span class="ptg-usage-label">암기카드</span>
                                <div class="ptg-usage-value">
                                    ${(
                                      window.ptg_dashboard_vars
                                        ?.flashcard_count || 0
                                    ).toLocaleString()} / ${
        window.ptg_dashboard_vars?.flashcard_limit === -1 ||
        window.ptg_dashboard_vars?.flashcard_limit >= 999999
          ? "무제한"
          : (window.ptg_dashboard_vars?.flashcard_limit || 0).toLocaleString()
      }
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Account Management -->
                    <section class="ptg-mb-section">
                        <h2 class="ptg-mb-section-title">⚙️ 계정 관리</h2><br>
                        <div class="ptg-account-links">
                            <a href="https://ptgates.com/account/?tab=profile" class="ptg-account-link">
                                <span class="ptg-link-icon">👤</span>
                                <span class="ptg-link-text">프로필 수정</span>
                                <span class="ptg-link-arrow">→</span>
                            </a>
                            <a href="https://ptgates.com/account/?tab=security" class="ptg-account-link">
                                <span class="ptg-link-icon">🔒</span>
                                <span class="ptg-link-text">비밀번호 변경</span>
                                <span class="ptg-link-arrow">→</span>
                            </a>
                            <button type="button" class="ptg-account-link" data-toggle-payment>
                                <span class="ptg-link-icon">💳</span>
                                <span class="ptg-link-text">결제 관리</span>
                                <span class="ptg-link-arrow"></span>
                            </button>
                        </div>

                        <!-- Payment Management Section (Hidden by default) -->
                        <div id="ptg-payment-management" style="display: none; margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 24px;">
                            
                            <!-- Tabs -->
                            <div class="ptg-pm-tabs">
                                <button type="button" class="ptg-pm-tab is-active" data-pm-tab="product">상품 선택 및 결제</button>
                                <button type="button" class="ptg-pm-tab" data-pm-tab="history">결제 내역</button>
                            </div>

                            <!-- Tab Content: Product Selection -->
                            <div id="ptg-pm-content-product" class="ptg-pm-content is-active">
                                <div class="ptg-membership-wrapper" style="margin: 0; padding: 0;">
                                    
                                    <!-- Plan Grid (Always Visible) -->
                                    <div class="ptg-plan-selection-card">
                                        <div class="ptg-card-inner">
                                            <div class="ptg-plan-title">Premium 플랜 선택</div>
                                            <p class="ptg-plan-sub">
                                                학습 기간과 예산에 맞게 Premium 이용 기간을 선택하세요.
                                            </p>

                                            <div class="ptg-plan-grid">
                                                ${
                                                  data.products &&
                                                  data.products.length > 0
                                                    ? data.products
                                                        .map((p) => {
                                                          const isPopular =
                                                            p.featured_level >
                                                            0;
                                                          const tag = isPopular
                                                            ? '<span class="ptg-plan-tag popular-tag">RECOMMENDED</span>'
                                                            : "";
                                                          const popularClass =
                                                            isPopular
                                                              ? "popular"
                                                              : "";

                                                          // Convert price to number just in case
                                                          const priceVal =
                                                            parseInt(p.price);

                                                          return `
                                                        <div class="ptg-plan-cell ${popularClass}" onclick="Dashboard.initiatePayment('${
                                                            p.product_code
                                                          }', ${priceVal}, '${
                                                            p.title
                                                          }')">
                                                            <div class="ptg-plan-name">${
                                                              p.title
                                                            }</div>
                                                            <div class="ptg-plan-month">${
                                                              p.description ||
                                                              ""
                                                            }</div>
                                                            <div class="ptg-plan-price">${priceVal.toLocaleString()}원</div>
                                                            <div class="ptg-plan-monthly">${
                                                              p.price_label ||
                                                              ""
                                                            }</div>
                                                            ${tag}
                                                        </div>
                                                        `;
                                                        })
                                                        .join("")
                                                    : '<div style="grid-column: 1 / -1; text-align:center; padding: 20px;">판매 중인 상품이 없습니다.</div>'
                                                }
                                            </div>
                                            
                                            <div style="text-align: center; margin-top: 20px;">
                                                <button type="button" class="ptg-cta-btn" style="background: #f3f4f6; color: #4b5563; box-shadow: none;" onclick="Dashboard.openPricingGuide()">
                                                    ℹ️ 이용가격 안내 보기
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab Content: Payment History -->
                            <div id="ptg-pm-content-history" class="ptg-pm-content">
                                <div class="ptg-history-list">
                                    ${historyHtml}
                                </div>
                            </div>
                        </div>

                    </section>
                </div>
            `;

      // 2. Cards Grid (Merged Stats & Actions)
      // Order: Study, Quiz, Bookmark, Review, My Note, Flashcard
      const cardsHtml = `
            <div class="ptg-dash-grid">
                <!-- 1. Subject | Study -->
                <a href="${
                  (window.ptg_dashboard_vars &&
                    window.ptg_dashboard_vars.study_url) ||
                  "/ptg_study/"
                }" class="ptg-dash-card">
                    <div class="ptg-card-icon">📚</div>
                    <div class="ptg-card-title">과목|Study</div>
                    <div class="ptg-card-stat">${studyPercent}%</div>
                </a>

                <!-- 2. Practice | Quiz -->
                <a href="/ptg_quiz/" class="ptg-dash-card">
                    <div class="ptg-card-icon">📝</div>
                    <div class="ptg-card-title">실전|Quiz</div>
                    <div class="ptg-card-stat">${quizPercent}%</div>
                </a>

                <!-- 3. Bookmark -->
                <a href="/bookmark/" class="ptg-dash-card">
                    <div class="ptg-card-icon">🔖</div>
                    <div class="ptg-card-title">북마크</div>
                    <div class="ptg-card-stat"><strong>${this.escapeHtml(
                      bookmarks?.count ?? 0
                    )}</strong> 문제</div>
                </a>

                <!-- 4. Review | Quiz -->
                <a href="/ptg_quiz/?review_only=1&auto_start=1" class="ptg-dash-card">
                    <div class="ptg-card-icon">🧠</div>
                    <div class="ptg-card-title">복습|Quiz</div>
                    <div class="ptg-card-stat">
                        <strong>${(
                          today_reviews || 0
                        ).toLocaleString()}</strong> 문제
                    </div>
                </a>
                <!-- 5. My Note -->
                <a href="/mynote/" class="ptg-dash-card">
                    <div class="ptg-card-icon">🗒️</div>
                    <div class="ptg-card-title">마이노트</div>
                    <div class="ptg-card-stat"><strong>${
                      mynote_count || 0
                    }</strong> 문제</div>
                </a>

                <!-- 6. Flashcard -->
                <a href="/flashcards/" class="ptg-dash-card">
                    <div class="ptg-card-icon">🃏</div>
                    <div class="ptg-card-title">암기카드</div>
                    <div class="ptg-card-stat">${flashcardPercent}%</div>
                </a>
            </div>
      `;

      // 5. Subject Learning Records
      const learningHtml = this.renderLearningRecords(learningRecords);

      // Combine all sections (Row 1: Welcome, Row 2: Cards Grid, Row 3: Learning)
      this.$container.html(welcomeHtml + cardsHtml + learningHtml);
      this.bindLearningTipModal();
    },

    renderLearningRecords: function (records) {
      const subjectSessions = Array.isArray(records.subjects)
        ? records.subjects
        : [];

      if (!subjectSessions.length) {
        return "";
      }

      const subjectHtml = `
                <div class="ptg-course-categories">
                    ${subjectSessions
                      .map((session) => this.buildSessionGroup(session))
                      .join("")}
                </div>
            `;

      return `
                <div class="ptg-dash-learning">
                    <div class="ptg-study-header ptg-learning-header">
                        <h2>🗝️ 과목 별 학습 기록</h2>
                        <button type="button" class="ptg-study-tip-trigger" data-learning-tip-open>[학습Tip]</button>
                    </div>
                    ${subjectHtml}
                    ${this.buildLearningTipModal()}
                </div>
            `;
    },

    buildSessionGroup: function (session) {
      if (!session || !Array.isArray(session.subjects)) {
        return "";
      }

      const subjectsHtml = session.subjects
        .map((subject) => this.buildSubjectCard(session.session, subject))
        .join("");

      return `
                <div class="ptg-session-group" data-session="${this.escapeHtml(
                  session.session
                )}">
                    <div class="ptg-session-grid">
                        ${subjectsHtml}
                    </div>
                </div>
            `;
    },

    buildSubjectCard: function (session, subject) {
      if (!subject) {
        return "";
      }

      const subList = Array.isArray(subject.subsubjects)
        ? subject.subsubjects
        : [];
      const description = subject.description
        ? `<p class="ptg-category-desc">${this.escapeHtml(
            subject.description
          )}</p>`
        : "";

      // 세부과목별 study와 quiz 총계 계산
      let totalStudy = 0;
      let totalQuiz = 0;
      if (subList.length > 0) {
        subList.forEach((sub) => {
          totalStudy += typeof sub.study === "number" ? sub.study : 0;
          totalQuiz += typeof sub.quiz === "number" ? sub.quiz : 0;
        });
      }

      // 헤더 오른쪽 끝에 총계 표시
      const totalCountsHtml = `<span class="ptg-category-total">Study(${totalStudy}) / Quiz(${totalQuiz})</span>`;

      const subsHtml = subList.length
        ? subList
            .map((sub) => {
              // 1100 Study 플러그인과 동일하게 rawurlencode (encodeURIComponent)로 인코딩해서 저장
              const encodedSubjectId = encodeURIComponent(sub.name);
              const studyCount = typeof sub.study === "number" ? sub.study : 0;
              const quizCount = typeof sub.quiz === "number" ? sub.quiz : 0;
              return `
                        <li class="ptg-subject-item" data-subject-id="${this.escapeHtml(
                          encodedSubjectId
                        )}">
                            <span class="ptg-subject-name">${this.escapeHtml(
                              sub.name
                            )}</span>
                            <span class="ptg-subject-counts">(${studyCount}/${quizCount})</span>
                        </li>
                    `;
            })
            .join("")
        : '<li class="ptg-subject-item is-empty">데이터가 없습니다.</li>';

      return `
                <section class="ptg-category" data-category-id="${this.escapeHtml(
                  subject.id
                )}">
                    <header class="ptg-category-header">
                        <h4 class="ptg-category-title">
                            <span class="ptg-session-badge">${this.escapeHtml(
                              session
                            )}교시</span>
                            <span class="ptg-category-name">${this.escapeHtml(
                              subject.name
                            )}</span>
                            ${totalCountsHtml}
                        </h4>
                        ${description}
                    </header>
                    <ul class="ptg-subject-list ptg-subject-list--stack">
                        ${subsHtml}
                    </ul>
                </section>
            `;
    },

    buildLearningTipModal: function () {
      return `
                <div id="ptg-learning-tip-modal" class="ptg-learning-tip-modal" aria-hidden="true">
                    <div class="ptg-learning-tip-backdrop" data-learning-tip-close></div>
                    <div class="ptg-learning-tip-dialog" role="dialog" aria-modal="true">
                        <div class="ptg-learning-tip-header">
                            <h3>과목 별 학습 기록 안내</h3>
                            <button type="button" class="ptg-learning-tip-close" data-learning-tip-close aria-label="닫기">&times;</button>
                        </div>
                        <div class="ptg-learning-tip-body">
                            <section>
                                <h4>📚 데이터 확인 방법</h4>
                                <ul>
                                    <li>각 세부과목 오른쪽의 <strong>Study</strong>/<strong>Quiz</strong> 수치로 학습 빈도를 확인하세요.</li>
                                    <li>최근 학습 데이터 기준으로 업데이트되며, 학습 시 즉시 집계됩니다.</li>
                                </ul>
                            </section>
                            <section>
                                <h4>🎯 활용 팁</h4>
                                <ul>
                                    <li>Study 대비 Quiz 비율을 보고 복습이 필요한 세부과목을 파악하세요.</li>
                                    <li>어려운 과목은 암기카드나 마이노트로 연결하여 반복 학습하세요.</li>
                                </ul>
                            </section>
                        </div>
                    </div>
                </div>
            `;
    },

    bindLearningTipModal: function () {
      const $modal = this.$container.find("#ptg-learning-tip-modal");
      if (!$modal.length) {
        return;
      }

      this.$container.off("click.dashboardTip", "[data-learning-tip-open]");
      this.$container.on(
        "click.dashboardTip",
        "[data-learning-tip-open]",
        function (e) {
          e.preventDefault();
          $modal.addClass("is-open").attr("aria-hidden", "false");
        }
      );

      this.$container.off(
        "click.dashboardTipClose",
        "[data-learning-tip-close]"
      );
      this.$container.on(
        "click.dashboardTipClose",
        "[data-learning-tip-close]",
        function (e) {
          e.preventDefault();
          $modal.removeClass("is-open").attr("aria-hidden", "true");
        }
      );

      this.$container.off("click.dashboardDayToggle", ".ptg-learning-date-row");
      this.$container.on(
        "click.dashboardDayToggle",
        ".ptg-learning-date-row",
        function (e) {
          e.preventDefault();
          const $row = $(this);
          const $day = $row.closest(".ptg-learning-day");
          const $content = $day.find(".ptg-learning-day-content");
          const isOpen = $day.hasClass("is-open");

          $day.toggleClass("is-open");
          $row.attr("aria-expanded", !isOpen);
          $content.stop(true, true).slideToggle(160);
        }
      );
    },

    escapeHtml: function (text) {
      if (text === null || text === undefined) return "";
      const safeText = String(text);
      return safeText
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    },

    formatName: function (name) {
      const safe = this.escapeHtml(name || "");
      const parts = safe.trim().split(/\s+/).filter(Boolean);

      if (parts.length === 2) {
        return `${parts[1]}${parts[0]}`;
      }
      return safe || "학습자";
    },

    getGreeting: function () {
      const englishGreetings = [
        {
          text: "✨ BE THE LIGHT. KEEP GOING.",
          translation: "빛이 되어라. 멈추지 말고 계속 나아가라.",
        },
        {
          text: "🧭 LIFE IS A JOURNEY, NOT THE DESTINATION.",
          translation: "인생은 여정이지, 목적지가 아니다.",
        },
        {
          text: '⏰ "If you want to make your dream come true, the first thing you have to do is to wake up."',
          translation:
            "꿈을 이루고 싶다면, 가장 먼저 해야 할 일은 잠에서 깨어나는 것이다.",
        },
        {
          text: '🔥 "If you plant fire in your heart, it will burn against the wind."',
          translation:
            "당신의 가슴 속에 불꽃을 심는다면, 그 불꽃은 바람에 맞서 타오를 것이다.",
        },
        {
          text: '💖 "Give up worrying about what others think of you. What they think isn\'t important. What is important is how you feel about yourself."',
          translation:
            "남들이 당신을 어떻게 생각할지 걱정하는 것을 멈추세요. 중요한 것은 당신이 자신에 대해 어떻게 느끼느냐입니다.",
        },
        {
          text: '🌌 "Something to accept the face of the arrogance you have to lose to recognize own fantasy."',
          translation:
            "자신의 환상을 깨닫기 위해 버려야 할 오만함의 민낯을 받아들이세요.",
        },
      ];

      const koreanGreetings = [
        { text: "학습을 이어가볼까요? 👋" },
        { text: "오늘도 화이팅입니다! 💪" },
        { text: "새로운 도전을 시작해볼까요? 🚀" },
        { text: "꾸준한 학습이 답입니다! 📚" },
        { text: "한 걸음씩 나아가요! 🎯" },
        { text: "오늘의 목표를 달성해봐요! ⭐" },
        { text: "지금이 바로 시작할 때입니다! 🌟" },
        { text: "작은 실천이 큰 변화를 만듭니다! ✨" },
        { text: "오늘도 성장하는 하루가 되길! 🌱" },
        { text: "포기하지 않는 당신이 멋져요! 💎" },
        { text: "매일 조금씩, 꾸준히! 📖" },
        { text: "도전하는 모습이 아름답습니다! 🌈" },
        { text: "오늘도 한 문제씩 풀어봐요! 🎓" },
        { text: "노력하는 당신을 응원합니다! 👏" },
        { text: "작은 시작이 큰 성과를 만듭니다! 🎁" },
      ];

      const isEnglishTurn = this.greetingCycleIndex % 3 === 0;
      this.greetingCycleIndex = (this.greetingCycleIndex + 1) % 3;

      const pool = isEnglishTurn ? englishGreetings : koreanGreetings;
      const randomIndex = Math.floor(Math.random() * pool.length);
      const selection = pool[randomIndex];

      return {
        text: selection.text,
        translation: selection.translation || "",
        isEnglish: Boolean(selection.translation),
      };
    },
  };

  // Expose Dashboard to global scope for inline event handlers
  window.Dashboard = Dashboard;

  $(document).ready(function () {
    Dashboard.init();
  });
})(jQuery);
