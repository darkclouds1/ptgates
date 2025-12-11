/**
 * PTGates Admin Create Question JavaScript
 */

var PTGates_Admin_Create = {
    config: {
        apiUrl: '',
        restUrl: '', // REST API 기본 URL
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
            this.config.restUrl = ptgAdmin.restUrl || ptgAdmin.apiUrl; // REST API 기본 URL
            this.config.ajaxUrl = ptgAdmin.ajaxUrl;
            this.config.nonce = ptgAdmin.nonce;
            console.log('[PTGates Admin] Config loaded:', {
                apiUrl: this.config.apiUrl,
                restUrl: this.config.restUrl,
                hasNonce: !!this.config.nonce
            });
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

        // Image Preview
        jQuery(document).on('change', '#ptg-create-image-input', function(e) {
            self.handleImagePreview(e.target.files[0]);
        });

        // Form Submit
        jQuery(document).on('submit', s.form, function(e) {
            e.preventDefault();
            self.submitForm();
        });
    },

    loadSubjects: function() {
        var self = this;
        // 1200-ptgates-quiz의 REST API 사용 (DB에서 직접 가져오기)
        // 모든 교시의 과목을 합쳐서 가져오기
        if (!self.config.restUrl) {
            self.config.restUrl = self.config.apiUrl;
        }
        var quizApiUrl = self.config.restUrl.replace('ptg-admin/v1', 'ptg-quiz/v1');
        var url = quizApiUrl + 'subjects'; // session 파라미터 없이 모든 교시의 과목 가져오기
        
        console.log('[PTGates Admin] Loading subjects from:', url);
        
        jQuery.ajax({
            url: url,
            method: 'GET',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
            success: function(response) {
                console.log('[PTGates Admin] Subjects API response:', response);
                var $select = jQuery(self.config.selectors.subjectSelect);
                $select.html('<option value="">과목 선택</option>');
                
                var subjects = [];
                if (response && Array.isArray(response)) {
                    subjects = response;
                } else if (response && response.success && Array.isArray(response.data)) {
                    subjects = response.data;
                } else {
                    console.warn('[PTGates Admin] Unexpected response format:', response);
                }
                
                console.log('[PTGates Admin] Parsed subjects:', subjects);
                
                // 중복 제거
                var uniqueSubjects = [];
                subjects.forEach(function(subject) {
                    if (uniqueSubjects.indexOf(subject) === -1) {
                        uniqueSubjects.push(subject);
                    }
                });
                
                console.log('[PTGates Admin] Unique subjects:', uniqueSubjects);
                
                uniqueSubjects.forEach(function(subjectName) {
                    $select.append(jQuery('<option>', {
                        value: subjectName,
                        text: subjectName
                    }));
                });
                
                if (uniqueSubjects.length === 0) {
                    console.warn('[PTGates Admin] No subjects loaded. Check API endpoint and database.');
                }
            },
            error: function(xhr, status, error) {
                console.error('[PTGates Admin] 과목 로드 오류:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                alert('과목 목록을 불러올 수 없습니다. 콘솔을 확인해주세요.');
            }
        });
    },

    updateSubsubjects: function(subjectName) {
        var self = this;
        var $subSelect = jQuery(this.config.selectors.subsubjectSelect);
        
        $subSelect.html('<option value="">세부과목 선택</option>');

        if (!subjectName) {
            return;
        }
        
        // 1200-ptgates-quiz의 REST API 사용
        // 모든 교시에서 해당 과목의 세부과목을 합쳐서 가져오기
        var quizApiUrl = self.config.restUrl.replace('ptg-admin/v1', 'ptg-quiz/v1');
        
        // 먼저 교시 목록 가져오기
        jQuery.ajax({
            url: quizApiUrl + 'sessions',
            method: 'GET',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
            success: function(sessionsResponse) {
                var sessions = [];
                if (sessionsResponse && Array.isArray(sessionsResponse)) {
                    sessions = sessionsResponse;
                } else if (sessionsResponse && sessionsResponse.success && Array.isArray(sessionsResponse.data)) {
                    sessions = sessionsResponse.data;
                }
                
                // 모든 교시에서 세부과목 가져오기
                var allSubsubjects = [];
                var completedRequests = 0;
                
                if (sessions.length === 0) {
                    // 교시 목록이 없으면 기본값 [1, 2] 사용
                    sessions = [1, 2];
                }
                
                sessions.forEach(function(session) {
                    var url = quizApiUrl + 'subsubjects?session=' + encodeURIComponent(session) + '&subject=' + encodeURIComponent(subjectName);
                    jQuery.ajax({
                        url: url,
                        method: 'GET',
                        beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
                        success: function(response) {
                            var subsubjects = [];
                            if (response && Array.isArray(response)) {
                                subsubjects = response;
                            } else if (response && response.success && Array.isArray(response.data)) {
                                subsubjects = response.data;
                            }
                            
                            // 각 교시별로 가져온 세부과목은 이미 sort_order 순서로 정렬되어 있음
                            // 중복 제거하면서 순서 유지 (먼저 나온 것이 우선)
                            subsubjects.forEach(function(subsubject) {
                                if (allSubsubjects.indexOf(subsubject) === -1) {
                                    allSubsubjects.push(subsubject);
                                }
                            });
                            
                            completedRequests++;
                            if (completedRequests === sessions.length) {
                                // 모든 요청 완료 후 세부과목 표시 (이미 sort_order 순서로 정렬됨)
                                // sort() 제거 - DB에서 이미 정렬된 순서 유지
                                allSubsubjects.forEach(function(subsubject) {
                                    $subSelect.append(jQuery('<option>', { value: subsubject, text: subsubject }));
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('세부과목 로드 오류 (교시 ' + session + '):', error);
                            completedRequests++;
                            if (completedRequests === sessions.length) {
                                // 모든 요청 완료 후 세부과목 표시 (에러가 있어도)
                                allSubsubjects.sort();
                                allSubsubjects.forEach(function(subsubject) {
                                    $subSelect.append(jQuery('<option>', { value: subsubject, text: subsubject }));
                                });
                            }
                        }
                    });
                });
            },
            error: function(xhr, status, error) {
                console.error('교시 목록 로드 오류:', error);
                // 교시 목록을 가져오지 못하면 기본값 [1, 2] 사용
                var sessions = [1, 2];
                var allSubsubjects = [];
                var completedRequests = 0;
                
                sessions.forEach(function(session) {
                    var url = quizApiUrl + 'subsubjects?session=' + encodeURIComponent(session) + '&subject=' + encodeURIComponent(subjectName);
                    jQuery.ajax({
                        url: url,
                        method: 'GET',
                        beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
                        success: function(response) {
                            var subsubjects = [];
                            if (response && Array.isArray(response)) {
                                subsubjects = response;
                            } else if (response && response.success && Array.isArray(response.data)) {
                                subsubjects = response.data;
                            }
                            
                            // 각 교시별로 가져온 세부과목은 이미 sort_order 순서로 정렬되어 있음
                            subsubjects.forEach(function(subsubject) {
                                if (allSubsubjects.indexOf(subsubject) === -1) {
                                    allSubsubjects.push(subsubject);
                                }
                            });
                            
                            completedRequests++;
                            if (completedRequests === sessions.length) {
                                // 모든 요청 완료 후 세부과목 표시 (이미 sort_order 순서로 정렬됨)
                                // sort() 제거 - DB에서 이미 정렬된 순서 유지
                                allSubsubjects.forEach(function(subsubject) {
                                    $subSelect.append(jQuery('<option>', { value: subsubject, text: subsubject }));
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('세부과목 로드 오류 (교시 ' + session + '):', error);
                            completedRequests++;
                            if (completedRequests === sessions.length) {
                                allSubsubjects.sort();
                                allSubsubjects.forEach(function(subsubject) {
                                    $subSelect.append(jQuery('<option>', { value: subsubject, text: subsubject }));
                                });
                            }
                        }
                    });
                });
            }
        });
    },

    /**
     * 이미지 미리보기 처리
     */
    handleImagePreview: function(file) {
        if (!file || !file.type.match('image.*')) {
            return;
        }

        var reader = new FileReader();
        var self = this;
        
        reader.onload = function(e) {
            jQuery('#ptg-create-image-preview').show();
            jQuery('#ptg-create-image-preview-img').attr('src', e.target.result);
            jQuery('#ptg-create-image-info').text(
                '파일명: ' + file.name + ' (' + (file.size / 1024).toFixed(2) + ' KB)'
            );
        };
        
        reader.readAsDataURL(file);
    },

    /**
     * 이미지 리사이징 및 최적화 (클라이언트 측)
     * @param {File} file 원본 파일
     * @param {number} maxWidth 최대 너비
     * @param {number} maxHeight 최대 높이
     * @param {number} quality JPEG 품질 (0-1)
     * @returns {Promise<Blob>} 최적화된 이미지 Blob
     */
    optimizeImage: function(file, maxWidth, maxHeight, quality) {
        return new Promise(function(resolve, reject) {
            var reader = new FileReader();
            
            reader.onload = function(e) {
                var img = new Image();
                
                img.onload = function() {
                    var canvas = document.createElement('canvas');
                    var ctx = canvas.getContext('2d');
                    
                    // 리사이징 계산
                    var width = img.width;
                    var height = img.height;
                    
                    if (width > maxWidth || height > maxHeight) {
                        var ratio = Math.min(maxWidth / width, maxHeight / height);
                        width = width * ratio;
                        height = height * ratio;
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    
                    // 이미지 그리기
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    // Blob으로 변환
                    canvas.toBlob(function(blob) {
                        if (blob) {
                            console.log('[PTGates Admin] 이미지 최적화 완료:', {
                                원본크기: (file.size / 1024).toFixed(2) + ' KB',
                                최적화크기: (blob.size / 1024).toFixed(2) + ' KB',
                                감소율: ((1 - blob.size / file.size) * 100).toFixed(1) + '%',
                                크기: width + 'x' + height
                            });
                            resolve(blob);
                        } else {
                            reject(new Error('이미지 최적화 실패'));
                        }
                    }, file.type === 'image/png' ? 'image/png' : 'image/jpeg', quality);
                };
                
                img.onerror = function() {
                    reject(new Error('이미지 로드 실패'));
                };
                
                img.src = e.target.result;
            };
            
            reader.onerror = function() {
                reject(new Error('파일 읽기 실패'));
            };
            
            reader.readAsDataURL(file);
        });
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

        var formData = new FormData();
        
        // 일반 폼 데이터 추가
        formData.append('content', $form.find('textarea[name="content"]').val());
        formData.append('answer', $form.find('input[name="answer"]').val());
        formData.append('explanation', $form.find('textarea[name="explanation"]').val());
        formData.append('subject', $form.find('select[name="subject"]').val());
        formData.append('subsubject', $form.find('select[name="subsubject"]').val());
        formData.append('exam_year', $form.find('input[name="exam_year"]').val());
        formData.append('exam_session', $form.find('input[name="exam_session"]').val());
        formData.append('difficulty', $form.find('select[name="difficulty"]').val());
        formData.append('is_active', $form.find('input[name="is_active"]').is(':checked') ? 1 : 0);
        
        // 이미지 파일 처리
        var imageInput = document.getElementById('ptg-create-image-input');
        var imageFile = imageInput && imageInput.files.length > 0 ? imageInput.files[0] : null;
        
        if (imageFile) {
            $btn.text('이미지 최적화 중...').prop('disabled', true);
            
            // 이미지 최적화 (500px, 품질 0.85)
            self.optimizeImage(imageFile, 500, 500, 0.85)
                .then(function(optimizedBlob) {
                    // 최적화된 이미지를 FormData에 추가
                    var optimizedFile = new File([optimizedBlob], imageFile.name, {
                        type: imageFile.type === 'image/png' ? 'image/png' : 'image/jpeg',
                        lastModified: Date.now()
                    });
                    formData.append('question_image', optimizedFile);
                    
                    // 실제 업로드 시작
                    self.uploadForm(formData, $btn);
                })
                .catch(function(error) {
                    console.error('[PTGates Admin] 이미지 최적화 실패:', error);
                    alert('이미지 최적화에 실패했습니다. 원본 파일로 업로드를 시도합니다.');
                    // 원본 파일로 업로드 시도
                    formData.append('question_image', imageFile);
                    self.uploadForm(formData, $btn);
                });
        } else {
            // 이미지가 없으면 바로 업로드
            self.uploadForm(formData, $btn);
        }
    },

    uploadForm: function(formData, $btn) {
        var self = this;
        
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
                var $form = jQuery(self.config.selectors.form);
                
                if (response.success) {
                    alert('문제가 성공적으로 등록되었습니다. (ID: ' + response.data.question_id + ')');
                    // Reset form
                    $form[0].reset();
                    // 이미지 미리보기 숨기기
                    jQuery('#ptg-create-image-preview').hide();
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
                
                // REST API 에러 응답 파싱
                var errorMessage = '서버 통신 오류: ' + status + ' ' + error;
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response && response.message) {
                        errorMessage = response.message;
                    } else if (response && response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                } catch (e) {
                    // JSON 파싱 실패 시 기본 메시지 사용
                }
                
                alert('저장에 실패했습니다: ' + errorMessage);
            }
        });
    }
};

jQuery(document).ready(function() {
    PTGates_Admin_Create.init();
});
