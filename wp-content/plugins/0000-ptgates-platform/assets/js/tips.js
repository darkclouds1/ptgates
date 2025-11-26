/**
 * PTGates Platform - 공통 팝업(Tip) 유틸리티
 * 
 * 모든 모듈에서 사용할 수 있는 공통 팝업 창 관리 시스템
 * - 중복 팝업 방지
 * - 일관된 UI/UX 제공
 * - -tip으로 끝나는 팝업 이름 규칙 적용
 */

(function() {
    'use strict';
    
    // 전역 네임스페이스
    window.PTGTips = window.PTGTips || {};
    
    /**
     * 팝업 관리자
     */
    const TipManager = {
        // 활성화된 팝업 추적 (중복 방지)
        activeTips: {},
        
        /**
         * 팝업 표시
         * 
         * @param {string} tipName 팝업 이름 (예: 'map-tip', 'study-tip', 'quiz-tip')
         * @param {object} options 옵션 (선택사항 - 기본값은 tips-content.js에서 가져옴)
         * @param {string} options.title 팝업 제목 (옵션, 기본값 사용 시 생략 가능)
         * @param {string} options.content 팝업 내용 (옵션, 기본값 사용 시 생략 가능)
         * @param {number} options.maxWidth 최대 너비 (옵션, 기본값 사용 시 생략 가능)
         * @param {function} options.onClose 닫기 콜백 (옵션)
         */
        show: function(tipName, options) {
            // 팝업 이름이 -tip으로 끝나는지 확인
            if (!tipName || !tipName.endsWith('-tip')) {
                console.warn('[PTG Tips] 팝업 이름은 "-tip"으로 끝나야 합니다:', tipName);
                return;
            }
            
            // 이미 열려있는 팝업이 있으면 닫기
            if (this.activeTips[tipName]) {
                this.close(tipName);
            }
            
            // 중앙 저장소에서 팝업 내용 가져오기
            const defaultContent = (window.PTGTips && window.PTGTips.Contents) 
                ? window.PTGTips.Contents.get(tipName) 
                : null;
            
            // 옵션 병합 (전달된 options가 우선, 없으면 기본값 사용)
            const opts = {
                title: (options && options.title) || (defaultContent && defaultContent.title) || '안내',
                content: (options && options.content) || (defaultContent && defaultContent.content) || '',
                maxWidth: (options && options.maxWidth) || (defaultContent && defaultContent.maxWidth) || 600,
                onClose: (options && options.onClose) || null
            };
            
            // 내용이 없으면 경고
            if (!opts.content) {
                console.warn('[PTG Tips] 팝업 내용을 찾을 수 없습니다:', tipName);
                return;
            }
            
            // 모달 HTML 생성
            const modalId = 'ptg-tip-' + tipName.replace(/-tip$/, '');
            const modal = this.createModal(modalId, tipName, opts);
            
            // body에 추가
            document.body.appendChild(modal);
            
            // 활성화 상태로 표시
            this.activeTips[tipName] = {
                modal: modal,
                modalId: modalId,
                onClose: opts.onClose
            };
            
            // 애니메이션 적용
            setTimeout(function() {
                modal.classList.add('ptg-tip-show');
            }, 10);
            
            // body 스크롤 방지
            document.body.style.overflow = 'hidden';
            
            // ESC 키 핸들러 등록
            this.setupEscapeHandler(tipName);
        },
        
        /**
         * 팝업 닫기
         * 
         * @param {string} tipName 팝업 이름
         */
        close: function(tipName) {
            if (!this.activeTips[tipName]) {
                return;
            }
            
            const tip = this.activeTips[tipName];
            const modal = tip.modal;
            
            // 닫기 애니메이션
            modal.classList.remove('ptg-tip-show');
            
            // 애니메이션 완료 후 제거
            setTimeout(function() {
                if (modal && modal.parentNode) {
                    modal.parentNode.removeChild(modal);
                }
                document.body.style.overflow = '';
                
                // 콜백 실행
                if (tip.onClose && typeof tip.onClose === 'function') {
                    tip.onClose();
                }
            }, 200);
            
            // 추적에서 제거
            delete this.activeTips[tipName];
        },
        
        /**
         * 모든 팝업 닫기
         */
        closeAll: function() {
            const tipNames = Object.keys(this.activeTips);
            tipNames.forEach(function(name) {
                TipManager.close(name);
            });
        },
        
        /**
         * 모달 HTML 생성
         * 
         * @param {string} modalId 모달 ID
         * @param {string} tipName 팝업 이름
         * @param {object} options 옵션
         * @returns {HTMLElement} 모달 요소
         */
        createModal: function(modalId, tipName, options) {
            const modal = document.createElement('div');
            modal.id = modalId;
            modal.className = 'ptg-tip-modal';
            modal.setAttribute('data-tip-name', tipName);
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('aria-labelledby', modalId + '-title');
            
            modal.innerHTML = `
                <div class="ptg-tip-backdrop"></div>
                <div class="ptg-tip-content" style="max-width: ${options.maxWidth}px;">
                    <div class="ptg-tip-header">
                        <h2 id="${modalId}-title" class="ptg-tip-title">${this.escapeHtml(options.title)}</h2>
                        <button type="button" 
                                class="ptg-tip-close" 
                                aria-label="닫기"
                                data-tip-close="${tipName}">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="ptg-tip-body">
                        ${options.content}
                    </div>
                </div>
            `;
            
            // 닫기 버튼 이벤트
            const closeBtn = modal.querySelector('.ptg-tip-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    TipManager.close(tipName);
                });
            }
            
            // 배경 클릭 시 닫기
            const backdrop = modal.querySelector('.ptg-tip-backdrop');
            if (backdrop) {
                backdrop.addEventListener('click', function() {
                    TipManager.close(tipName);
                });
            }
            
            return modal;
        },
        
        /**
         * ESC 키 핸들러 설정
         * 
         * @param {string} tipName 팝업 이름
         */
        setupEscapeHandler: function(tipName) {
            const handler = function(e) {
                if (e.key === 'Escape' && TipManager.activeTips[tipName]) {
                    TipManager.close(tipName);
                    document.removeEventListener('keydown', handler);
                }
            };
            document.addEventListener('keydown', handler);
        },
        
        /**
         * HTML 이스케이프
         * 
         * @param {string} text 텍스트
         * @returns {string} 이스케이프된 텍스트
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        /**
         * 팝업이 열려있는지 확인
         * 
         * @param {string} tipName 팝업 이름
         * @returns {boolean}
         */
        isOpen: function(tipName) {
            return !!this.activeTips[tipName];
        }
    };
    
    // 전역으로 노출
    window.PTGTips.show = TipManager.show.bind(TipManager);
    window.PTGTips.close = TipManager.close.bind(TipManager);
    window.PTGTips.closeAll = TipManager.closeAll.bind(TipManager);
    window.PTGTips.isOpen = TipManager.isOpen.bind(TipManager);
    
})();

