/**
 * PTGates Admin 문제 목록 JavaScript
 * Refactored to use Module Pattern and Namespace Event Binding
 * Updated: Inline Editing Support
 */

var PTGates_Admin_List = {
    // 설정값 (Selectors & Config)
    config: {
        apiUrl: '', // init에서 설정
        ajaxUrl: '', // init에서 설정
        nonce: '',  // init에서 설정
        selectors: {
            // Filters
            yearFilter: '#ptg-year-filter',
            examSessionFilter: '#ptg-exam-session-filter',
            sessionFilter: '#ptg-session-filter',
            subjectFilter: '#ptg-subject-filter',
            subsubjectFilter: '#ptg-subsubject-filter',
            
            // Search
            searchInput: '#ptg-search-input',
            searchBtn: '#ptg-search-btn',
            clearBtn: '#ptg-clear-search',
            
            // List & Pagination
            listContainer: '#ptg-questions-list',
            paginationContainer: '#ptg-pagination',
            resultCount: '#ptg-result-count',
            
            // Inline Edit
            editTrigger: '.pt-admin-edit-btn',
            editWrapper: '.ptg-inline-edit-form',
            saveBtn: '.pt-btn-save-edit',
            cancelBtn: '.pt-btn-cancel-edit',
            
            // Question Card Elements
            card: '.ptg-question-card',
            viewContent: '.ptg-question-content',
            viewActions: '.ptg-question-actions'
        }
    },

    state: {
        currentPage: 1,
        currentSearch: '',
        filters: {
            year: '',
            examSession: '',
            session: '',
            subject: '',
            subsubject: ''
        }
    },

    init: function() {
        console.log('[PTGates Admin] List Module Initialized');
        
        // 전역 설정 가져오기
        if (typeof ptgAdmin !== 'undefined') {
            this.config.apiUrl = ptgAdmin.apiUrl;
            this.config.ajaxUrl = ptgAdmin.ajaxUrl;
            this.config.nonce = ptgAdmin.nonce;
        } else {
            console.error('[PTGates Admin] ptgAdmin global object not found.');
            return;
        }

        this.bindEvents();
        this.loadInitialData();
    },

    bindEvents: function() {
        var self = this;
        var s = self.config.selectors;

        // 1. 편집 버튼 클릭 (Inline Edit)
        jQuery(document).off('click.ptAdminList', s.editTrigger).on('click.ptAdminList', s.editTrigger, function(e) {
            e.preventDefault();
            var $btn = jQuery(this);
            var $card = $btn.closest(s.card);
            var questionId = $btn.data('id');

            // 중복 실행 방지
            if ($card.find(s.editWrapper).length > 0) {
                return;
            }

            console.log('[PTGates Admin] Inline Edit clicked. ID:', questionId);
            self.startInlineEdit($card, questionId, $btn);
        });

        // 2. 검색 버튼
        jQuery(document).on('click.ptAdminList', s.searchBtn, function() {
            self.state.currentSearch = jQuery(s.searchInput).val().trim();
            self.state.currentPage = 1;
            self.loadQuestions();
        });

        // 3. 검색 엔터키
        jQuery(document).on('keypress.ptAdminList', s.searchInput, function(e) {
            if (e.which === 13) {
                jQuery(s.searchBtn).click();
            }
        });

        // 4. 초기화 버튼
        jQuery(document).on('click.ptAdminList', s.clearBtn, function() {
            self.resetFilters();
        });

        // 5. 필터 변경 이벤트들
        jQuery(document).on('change.ptAdminList', s.yearFilter, function() {
            self.state.filters.year = jQuery(this).val();
            self.state.filters.examSession = '';
            self.resetSelectOptions(jQuery(s.examSessionFilter), '회차');
            if (self.state.filters.year) {
                self.loadExamSessions(self.state.filters.year);
            }
        });

        jQuery(document).on('change.ptAdminList', s.examSessionFilter, function() {
            self.state.filters.examSession = jQuery(this).val();
        });

        jQuery(document).on('change.ptAdminList', s.sessionFilter, function() {
            self.state.filters.session = jQuery(this).val();
            self.state.filters.subject = '';
            self.state.filters.subsubject = '';
            self.resetSelectOptions(jQuery(s.subjectFilter), '과목');
            self.resetSelectOptions(jQuery(s.subsubjectFilter), '세부과목');
            self.loadSubjects(self.state.filters.session);
        });

        jQuery(document).on('change.ptAdminList', s.subjectFilter, function() {
            self.state.filters.subject = jQuery(this).val();
            self.state.filters.subsubject = '';
            self.resetSelectOptions(jQuery(s.subsubjectFilter), '세부과목');
            if (self.state.filters.subject) {
                self.updateSubsubjects(self.state.filters.subject);
            }
        });

        jQuery(document).on('change.ptAdminList', s.subsubjectFilter, function() {
            self.state.filters.subsubject = jQuery(this).val();
        });

        // 6. 인라인 편집 - 취소
        jQuery(document).on('click.ptAdminList', s.cancelBtn, function(e) {
            e.preventDefault();
            var $wrapper = jQuery(this).closest(s.editWrapper);
            var $card = $wrapper.closest(s.card);
            
            // 편집 폼 제거 및 보기 모드 복구
            $wrapper.remove();
            $card.find(s.viewContent).show();
            $card.find(s.viewActions).show();
        });

        // 7. 인라인 편집 - 저장
        jQuery(document).on('click.ptAdminList', s.saveBtn, function(e) {
            e.preventDefault();
            var $wrapper = jQuery(this).closest(s.editWrapper);
            self.saveInlineEdit($wrapper);
        });

        // 8. 페이지네이션
        jQuery(document).on('click.ptAdminList', '.ptg-pagination-btn', function() {
            self.state.currentPage = jQuery(this).data('page');
            self.loadQuestions();
        });

        // 9. 이미지 미리보기 (Inline Edit)
        jQuery(document).on('change.ptAdminList', 'input[name="question_image"]', function(e) {
            var file = e.target.files[0];
            var $wrapper = jQuery(this).closest(s.editWrapper);
            var $previewContainer = $wrapper.find('.ptg-image-preview-container');
            
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    if ($previewContainer.length === 0) {
                        $previewContainer = jQuery('<div class="ptg-image-preview-container"><img class="ptg-image-preview"><p class="ptg-image-filename"></p></div>');
                        $wrapper.find('input[name="question_image"]').before($previewContainer);
                    }
                    $previewContainer.find('img').attr('src', e.target.result);
                    $previewContainer.find('.ptg-image-filename').text(file.name);
                }
                reader.readAsDataURL(file);
            }
        });

        // 10. 이미지 삭제 버튼
        jQuery(document).on('click.ptAdminList', '.ptg-btn-delete-image', function(e) {
            e.preventDefault();
            var $wrapper = jQuery(this).closest(s.editWrapper);
            
            if (confirm('이미지를 삭제하시겠습니까? 저장 시 반영됩니다.')) {
                $wrapper.find('input[name="delete_image"]').val('1');
                $wrapper.find('.ptg-image-preview-container').hide();
                $wrapper.find('input[name="question_image"]').val(''); // 파일 입력 초기화
            }
        });
    },

    loadInitialData: function() {
        this.loadExamYears();
        this.loadSessions();
        // 초기 안내 메시지
        jQuery(this.config.selectors.listContainer).html('<p style="text-align: center; color: #666; padding: 40px;">검색 또는 필터를 사용하여 문제를 조회하세요.</p>');
    },

    resetFilters: function() {
        var s = this.config.selectors;
        jQuery(s.searchInput).val('');
        this.state.currentSearch = '';
        this.state.filters = { year: '', examSession: '', session: '', subject: '', subsubject: '' };
        this.state.currentPage = 1;

        jQuery(s.yearFilter).val('');
        jQuery(s.sessionFilter).val('');
        this.resetSelectOptions(jQuery(s.examSessionFilter), '회차');
        this.resetSelectOptions(jQuery(s.subjectFilter), '과목');
        this.resetSelectOptions(jQuery(s.subsubjectFilter), '세부과목');
        
        this.loadSubjects(); // Reload all subjects
        
        jQuery(s.listContainer).html('<p style="text-align: center; color: #666; padding: 40px;">검색 또는 필터를 사용하여 문제를 조회하세요.</p>');
        jQuery(s.resultCount).hide();
        jQuery(s.paginationContainer).html('');
    },

    resetSelectOptions: function($select, label) {
        $select.html('<option value="">' + label + '</option>');
    },

    // --- Data Loading Methods ---

    loadExamYears: function() {
        var self = this;
        jQuery.ajax({
            url: self.config.apiUrl + 'exam-years',
            method: 'GET',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
            success: function(response) {
                if (response.success && Array.isArray(response.data)) {
                    var $select = jQuery(self.config.selectors.yearFilter);
                    self.resetSelectOptions($select, '년도');
                    response.data.forEach(function(year) {
                        $select.append(jQuery('<option>', { value: year, text: year + '년' }));
                    });
                }
            }
        });
    },

    loadExamSessions: function(year) {
        var self = this;
        if (!year) return;
        jQuery.ajax({
            url: self.config.apiUrl + 'exam-sessions?year=' + encodeURIComponent(year),
            method: 'GET',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
            success: function(response) {
                if (response.success && Array.isArray(response.data)) {
                    var $select = jQuery(self.config.selectors.examSessionFilter);
                    self.resetSelectOptions($select, '회차');
                    response.data.forEach(function(session) {
                        $select.append(jQuery('<option>', { value: session, text: session + '회' }));
                    });
                }
            }
        });
    },

    loadSessions: function() {
        var self = this;
        jQuery.ajax({
            url: self.config.apiUrl + 'sessions',
            method: 'GET',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
            success: function(response) {
                if (response.success && Array.isArray(response.data)) {
                    var $select = jQuery(self.config.selectors.sessionFilter);
                    self.resetSelectOptions($select, '교시');
                    response.data.forEach(function(session) {
                        $select.append(jQuery('<option>', { value: session.id, text: session.name }));
                    });
                    self.loadSubjects();
                }
            }
        });
    },

    loadSubjects: function(session) {
        var self = this;
        var url = self.config.apiUrl + 'subjects' + (session ? '?session=' + session : '');
        jQuery.ajax({
            url: url,
            method: 'GET',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
            success: function(response) {
                if (response.success && response.data) {
                    var $select = jQuery(self.config.selectors.subjectFilter);
                    self.resetSelectOptions($select, '과목');
                    response.data.forEach(function(item) {
                        $select.append(jQuery('<option>', {
                            value: item.name,
                            text: item.name,
                            'data-session': item.session,
                            'data-subsubjects': JSON.stringify(item.subsubjects)
                        }));
                    });
                }
            }
        });
    },

    updateSubsubjects: function(subjectName) {
        var $subjectSelect = jQuery(this.config.selectors.subjectFilter);
        var selectedOption = $subjectSelect.find('option:selected');
        var subsubjectsJson = selectedOption.attr('data-subsubjects');
        
        if (subsubjectsJson) {
            try {
                var subsubjects = JSON.parse(subsubjectsJson);
                var $subSelect = jQuery(this.config.selectors.subsubjectFilter);
                this.resetSelectOptions($subSelect, '세부과목');
                subsubjects.forEach(function(subsubject) {
                    $subSelect.append(jQuery('<option>', { value: subsubject, text: subsubject }));
                });
            } catch (e) {
                console.error('세부과목 파싱 오류:', e);
            }
        }
    },

    loadQuestions: function() {
        var self = this;
        var params = {
            page: self.state.currentPage,
            per_page: 20
        };

        // Add filters
        if (self.state.filters.subsubject) params.subsubject = self.state.filters.subsubject;
        else if (self.state.filters.subject) params.subject = self.state.filters.subject;

        if (self.state.filters.year) params.exam_year = self.state.filters.year;
        if (self.state.filters.examSession) params.exam_session = self.state.filters.examSession;
        
        var sessionValue = jQuery(self.config.selectors.sessionFilter).val();
        if (sessionValue) {
            params.exam_course = sessionValue.endsWith('교시') ? sessionValue : sessionValue + '교시';
        }

        if (self.state.currentSearch) params.search = self.state.currentSearch;

        console.log('[PTG Admin] loadQuestions params:', params);
        jQuery(self.config.selectors.listContainer).html('<p class="ptg-loading">로딩 중...</p>');

        jQuery.ajax({
            url: self.config.apiUrl + 'questions',
            method: 'GET',
            data: params,
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
            success: function(response) {
                if (response.success && response.data) {
                    self.renderQuestions(response.data.questions);
                    self.renderPagination(response.data);
                    self.updateResultCount(response.data.total, params);
                } else {
                    jQuery(self.config.selectors.listContainer).html('<p>문제를 불러올 수 없습니다.</p>');
                    jQuery(self.config.selectors.resultCount).hide();
                }
            },
            error: function() {
                jQuery(self.config.selectors.listContainer).html('<p>문제를 불러오는 중 오류가 발생했습니다.</p>');
            }
        });
    },

    // --- Rendering Methods ---

    renderQuestions: function(questions) {
        if (questions.length === 0) {
            jQuery(this.config.selectors.listContainer).html('<p>문제가 없습니다.</p>');
            return;
        }

        var html = '<div class="ptg-questions-grid">';
        var self = this;

        questions.forEach(function(q) {
            var content = self.cleanText(q.content || '');
            var explanation = q.explanation || ''; // 사용자 요청: 해설은 원본 그대로 표시

            var year = q.exam_years ? q.exam_years.split(',')[0] : '';
            var session = q.exam_sessions ? q.exam_sessions.split(',')[0] : '';
            var course = q.exam_courses ? q.exam_courses.split(',')[0] : '';
            var mainSubject = q.main_subjects ? q.main_subjects.split(',')[0] : '';
            var subsubject = q.subsubjects ? q.subsubjects.split(',')[0] : (q.subjects ? q.subjects.split(',')[0] : '');

            var metaParts = [];
            if (year) metaParts.push(year + '년');
            if (session) metaParts.push(session + '회');
            if (course) metaParts.push(course);
            if (mainSubject) metaParts.push(mainSubject);
            var metaInfo = metaParts.length > 0 ? metaParts.join(' ') : '-';

            // 이미지 아이콘 표시
            var imageIcon = q.question_image ? '<span class="ptg-image-indicator" title="이미지 있음">🖼️</span>' : '';

            html += `
                <div class="ptg-question-card">
                    <div class="ptg-question-header">
                        <div class="ptg-question-id-info">
                            <strong>문제 ID: ${q.question_id}</strong>
                            <span class="ptg-question-meta-info">${metaInfo}</span>
                            ${imageIcon}
                        </div>
                        <span class="ptg-question-subsubjects">${subsubject || '-'}</span>
                    </div>
                    <div class="ptg-question-content">
                        <div class="ptg-question-field">
                            <label>지문:</label>
                            <div class="ptg-question-text">${self.escapeHtml(content)}</div>
                        </div>
                        <div class="ptg-question-field">
                            <label>정답:</label>
                            <div class="ptg-question-text">${self.escapeHtml(q.answer || '-')}</div>
                        </div>
                        <div class="ptg-question-field">
                            <label>해설:</label>
                            <div class="ptg-question-text">${self.escapeHtml(explanation)}</div>
                        </div>
                        <div class="ptg-question-meta">
                            <span>난이도: ${q.difficulty || '-'}</span>
                            <span>활성: ${q.is_active ? '예' : '아니오'}</span>
                        </div>
                    </div>
                    <div class="ptg-question-actions">
                        <button class="pt-admin-edit-btn" data-id="${q.question_id}">✏️ 편집</button>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        jQuery(this.config.selectors.listContainer).html(html);
    },

    renderPagination: function(data) {
        if (data.total_pages <= 1) {
            jQuery(this.config.selectors.paginationContainer).html('');
            return;
        }

        var html = '<div class="ptg-pagination-controls">';
        if (data.page > 1) {
            html += `<button class="ptg-pagination-btn" data-page="${data.page - 1}">이전</button>`;
        }
        html += `<span>페이지 ${data.page} / ${data.total_pages} (총 ${data.total}개)</span>`;
        if (data.page < data.total_pages) {
            html += `<button class="ptg-pagination-btn" data-page="${data.page + 1}">다음</button>`;
        }
        html += '</div>';
        jQuery(this.config.selectors.paginationContainer).html(html);
    },

    updateResultCount: function(total, params) {
        var $countEl = jQuery(this.config.selectors.resultCount);
        if (total > 0) {
            var conditionText = '';
            var conditions = [];
            if (params.search) conditions.push('검색: "' + params.search + '"');
            if (params.subsubject) conditions.push('세부과목: ' + params.subsubject);
            else if (params.subject) conditions.push('과목: ' + params.subject);
            if (params.exam_year) conditions.push('년도: ' + params.exam_year);
            if (params.exam_session) conditions.push('회차: ' + params.exam_session);
            if (params.exam_course) conditions.push('교시: ' + params.exam_course);
            
            if (conditions.length > 0) conditionText = ' (' + conditions.join(', ') + ')';
            $countEl.text('총 ' + total.toLocaleString() + '개' + conditionText).show();
        } else {
            $countEl.hide();
        }
    },

    // --- Inline Edit Functionality ---

    startInlineEdit: function($card, questionId, $btn) {
        var self = this;
        var s = self.config.selectors;
        var originalBtnText = $btn.text();
        
        $btn.text('로딩...').prop('disabled', true);

        jQuery.ajax({
            url: self.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pt_get_question_edit_form',
                question_id: questionId,
                security: self.config.nonce
            },
            success: function(response) {
                $btn.text(originalBtnText).prop('disabled', false);

                if (response.success) {
                    // 1. Hide view mode
                    $card.find(s.viewContent).hide();
                    $card.find(s.viewActions).hide();

                    // 2. Append edit form
                    $card.append(response.data);
                } else {
                    alert('오류: ' + (response.data || '폼을 불러올 수 없습니다.'));
                }
            },
            error: function(xhr, status, error) {
                $btn.text(originalBtnText).prop('disabled', false);
                console.error('[PTGates Admin] AJAX Error:', status, error, xhr.responseText);
                alert('서버 통신 오류: ' + status + ' ' + error + '\n' + (xhr.responseText ? xhr.responseText.substring(0, 100) : ''));
            }
        });
    },

    saveInlineEdit: function($wrapper) {
        var self = this;
        var $btn = $wrapper.find(self.config.selectors.saveBtn);
        
        // FormData 객체 생성 (파일 업로드 지원)
        var formData = new FormData();
        formData.append('action', 'pt_update_question_inline');
        formData.append('security', self.config.nonce);
        formData.append('question_id', $wrapper.find('input[name="question_id"]').val());
        formData.append('content', $wrapper.find('textarea[name="content"]').val());
        formData.append('answer', $wrapper.find('input[name="answer"]').val());
        formData.append('explanation', $wrapper.find('textarea[name="explanation"]').val());
        formData.append('difficulty', $wrapper.find('select[name="difficulty"]').val());
        formData.append('is_active', $wrapper.find('input[name="is_active"]').is(':checked') ? 1 : 0);
        formData.append('delete_image', $wrapper.find('input[name="delete_image"]').val());
        
        // 파일 추가
        var fileInput = $wrapper.find('input[name="question_image"]')[0];
        if (fileInput && fileInput.files.length > 0) {
            formData.append('question_image', fileInput.files[0]);
        }

        $btn.text('저장 중...').prop('disabled', true);

        jQuery.ajax({
            url: self.config.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false, // 파일 전송 시 필수
            contentType: false, // 파일 전송 시 필수
            success: function(response) {
                if (response.success) {
                    alert('저장되었습니다.');
                    // Reload list to reflect changes
                    self.loadQuestions();
                } else {
                    alert('저장에 실패했습니다: ' + (response.data || '알 수 없는 오류'));
                    $btn.text('저장').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('[PTGates Admin] Save Error:', status, error, xhr.responseText);
                alert('서버 통신 오류: ' + status + ' ' + error + '\n' + (xhr.responseText ? xhr.responseText.substring(0, 100) : ''));
                $btn.text('저장').prop('disabled', false);
            }
        });
    },

    // --- Utilities ---

    cleanText: function(text) {
        if (!text) return '';
        var cleaned = text.replace(/_x000D_/g, '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        cleaned = cleaned.replace(/\n{2,}\s*([①-⑳])/g, '\n$1');
        cleaned = cleaned.replace(/\n{2,}/g, '\n');
        return cleaned;
    },

    escapeHtml: function(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize on ready
jQuery(document).ready(function() {
    PTGates_Admin_List.init();
});
