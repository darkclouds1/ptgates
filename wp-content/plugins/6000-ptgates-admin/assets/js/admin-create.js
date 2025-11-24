/**
 * PTGates Admin Create Question JavaScript
 */

var PTGates_Admin_Create = {
    config: {
        apiUrl: '',
        ajaxUrl: '',
        nonce: '',
        selectors: {
            form: '#ptg-create-question-form',
            subjectSelect: '#ptg-create-subject',
            subsubjectSelect: '#ptg-create-subsubject',
            submitBtn: '#ptg-create-question-form button[type="submit"]'
        }
    },

    init: function() {
        console.log('[PTGates Admin] Create Module Initialized');
        
        if (typeof ptgAdmin !== 'undefined') {
            this.config.apiUrl = ptgAdmin.apiUrl;
            this.config.ajaxUrl = ptgAdmin.ajaxUrl;
            this.config.nonce = ptgAdmin.nonce;
        } else {
            console.error('[PTGates Admin] ptgAdmin global object not found.');
            return;
        }

        this.bindEvents();
        this.loadSubjects();
    },

    bindEvents: function() {
        var self = this;
        var s = self.config.selectors;

        // Subject Change
        jQuery(document).on('change', s.subjectSelect, function() {
            var subject = jQuery(this).val();
            self.updateSubsubjects(subject);
        });

        // Form Submit
        jQuery(document).on('submit', s.form, function(e) {
            e.preventDefault();
            self.submitForm();
        });
    },

    loadSubjects: function() {
        var self = this;
        // Load all subjects (from all sessions)
        jQuery.ajax({
            url: self.config.apiUrl + 'subjects',
            method: 'GET',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
            success: function(response) {
                if (response.success && response.data) {
                    var $select = jQuery(self.config.selectors.subjectSelect);
                    $select.html('<option value="">과목 선택</option>');
                    
                    // Deduplicate subjects by name
                    var uniqueSubjects = {};
                    response.data.forEach(function(item) {
                        if (!uniqueSubjects[item.name]) {
                            uniqueSubjects[item.name] = {
                                name: item.name,
                                subsubjects: []
                            };
                        }
                        // Merge subsubjects
                        if (item.subsubjects && Array.isArray(item.subsubjects)) {
                            item.subsubjects.forEach(function(sub) {
                                if (uniqueSubjects[item.name].subsubjects.indexOf(sub) === -1) {
                                    uniqueSubjects[item.name].subsubjects.push(sub);
                                }
                            });
                        }
                    });

                    Object.values(uniqueSubjects).forEach(function(item) {
                        $select.append(jQuery('<option>', {
                            value: item.name,
                            text: item.name,
                            'data-subsubjects': JSON.stringify(item.subsubjects)
                        }));
                    });
                }
            }
        });
    },

    updateSubsubjects: function(subjectName) {
        var $subjectSelect = jQuery(this.config.selectors.subjectSelect);
        var selectedOption = $subjectSelect.find('option:selected');
        var subsubjectsJson = selectedOption.attr('data-subsubjects');
        var $subSelect = jQuery(this.config.selectors.subsubjectSelect);
        
        $subSelect.html('<option value="">세부과목 선택</option>');

        if (subsubjectsJson) {
            try {
                var subsubjects = JSON.parse(subsubjectsJson);
                subsubjects.forEach(function(subsubject) {
                    $subSelect.append(jQuery('<option>', { value: subsubject, text: subsubject }));
                });
            } catch (e) {
                console.error('세부과목 파싱 오류:', e);
            }
        }
    },

    submitForm: function() {
        var self = this;
        var s = self.config.selectors;
        var $form = jQuery(s.form);
        var $btn = jQuery(s.submitBtn);
        
        // Validation
        if (!$form.find('select[name="subject"]').val()) {
            alert('과목을 선택해주세요.');
            return;
        }
        if (!$form.find('select[name="subsubject"]').val()) {
            alert('세부과목을 선택해주세요.');
            return;
        }

        var formData = new FormData($form[0]);
        
        $btn.text('저장 중...').prop('disabled', true);

        jQuery.ajax({
            url: self.config.apiUrl + 'questions',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
            success: function(response) {
                $btn.text('문제 등록').prop('disabled', false);
                if (response.success) {
                    alert('문제가 성공적으로 등록되었습니다. (ID: ' + response.data.question_id + ')');
                    // Reset form but keep year/session
                    $form[0].reset();
                    // Restore year/session values (they are hidden inputs but reset clears them? No, reset restores to default value)
                    // But we want to keep the selection of subject/subsubject? 
                    // Requirement says: "저장된 문제도 편집모드에서 과목, 세부과목 변경 가능하도록 해야 함."
                    // For new registration, usually we clear the form.
                    // Let's clear content/answer/explanation but maybe keep subject?
                    // Standard behavior is reset all.
                    
                    // Re-select default difficulty/active
                    $form.find('select[name="difficulty"]').val('2');
                    $form.find('input[name="is_active"]').prop('checked', true);
                } else {
                    alert('오류: ' + (response.message || '알 수 없는 오류'));
                }
            },
            error: function(xhr, status, error) {
                $btn.text('문제 등록').prop('disabled', false);
                console.error('[PTGates Admin] Create Error:', status, error, xhr.responseText);
                alert('서버 통신 오류: ' + status + ' ' + error);
            }
        });
    }
};

jQuery(document).ready(function() {
    PTGates_Admin_Create.init();
});
