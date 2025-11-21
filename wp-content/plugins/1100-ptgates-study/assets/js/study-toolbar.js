(function ($) {
    'use strict';

    var PTGStudyToolbar = {
        init: function() {
            // Bind global events once
            this.bindGlobalEvents();
            
            // Initial check for toolbars
            this.initToolbars();

            // Optional: Set up a safe observer if needed, or rely on external triggers
            // For now, we'll rely on the existing periodic check but make it safe,
            // or better, expose initToolbars to be called by the lesson loader.
            
            // Expose to global scope for external calls (e.g. after AJAX load)
            window.PTGStudyToolbar = this;

            // Safe periodic check (polling) as a fallback for AJAX loads
            // This is safer than MutationObserver if we guard correctly
            setInterval(function() {
                PTGStudyToolbar.initToolbars();
            }, 1000);
        },

        initToolbars: function() {
            // 1. Find new question containers that haven't been initialized
            // We look for .ptg-lesson-item or .question-container depending on actual markup
            // Based on previous file, it was .ptg-lesson-item
            var $newItems = $('.ptg-lesson-item').not('[data-toolbar-init="true"]');
            
            if ($newItems.length === 0) {
                return; 
            }

            console.log('[PTG Study Toolbar] Initializing ' + $newItems.length + ' items.');

            $newItems.each(function() {
                var $item = $(this);
                
                // Mark as initialized immediately to prevent re-entry
                $item.attr('data-toolbar-init', 'true');

                var questionId = $item.data('lesson-id') || $item.data('question-id');
                if (!questionId) return;

                // Check if toolbar button already exists (prevent duplicates)
                if ($item.find('.ptg-contextual-action-btn').length > 0) {
                    return;
                }

                // Find the question text element
                var $questionText = $item.find('.ptg-question-text').first();
                if ($questionText.length === 0) return;

                // Wrap question text in a header with action button if not already wrapped
                if (!$questionText.parent().hasClass('ptg-question-header')) {
                    $questionText.wrap('<div class="ptg-question-header" style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px;"><div style="flex: 1;"></div></div>');

                    var $header = $questionText.closest('.ptg-question-header');
                    $header.append(
                        '<button class="ptg-contextual-action-btn" data-question-id="' + questionId + '" title="도구 메뉴" aria-label="문제 도구 메뉴 열기">' +
                        '⋮' +
                        '</button>'
                    );

                    // Add toolbar after header
                    $header.after(
                        '<div class="ptg-question-toolbar" style="display: none;">' +
                            '<div class="ptg-toolbar-icons">' +
                                '<button class="ptg-toolbar-btn ptg-btn-bookmark" data-action="bookmark" data-question-id="' + questionId + '" title="북마크">' +
                                    '<span class="ptg-toolbar-icon">🔖</span>' +
                                '</button>' +
                                '<button class="ptg-toolbar-btn ptg-btn-review" data-action="review" data-question-id="' + questionId + '" title="복습 표시">' +
                                    '<span class="ptg-toolbar-icon">🔁</span>' +
                                '</button>' +
                                '<button class="ptg-toolbar-btn ptg-btn-notes" data-action="memo" data-question-id="' + questionId + '" title="메모">' +
                                    '<span class="ptg-toolbar-icon">📝</span>' +
                                '</button>' +
                                '<button class="ptg-toolbar-btn ptg-btn-flashcard" data-action="flashcard" data-question-id="' + questionId + '" title="암기카드">' +
                                    '<span class="ptg-toolbar-icon">🗂️</span>' +
                                '</button>' +
                            '</div>' +
                        '</div>'
                    );
                    
                    // Initial status fetch
                    PTGStudyToolbar.updateToolbarStatus(questionId);
                }
            });
        },

        updateToolbarStatus: function(questionId) {
            if (!questionId) return;
            
            $.ajax({
                url: (window.location.origin || '') + '/wp-json/ptg-quiz/v1/questions/' + questionId + '/user-status',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': (window.ptgStudy && window.ptgStudy.api_nonce) || (window.wpApiSettings && window.wpApiSettings.nonce) || ''
                },
                success: function(status) {
                    var $item = $('.ptg-lesson-item[data-lesson-id="' + questionId + '"], .ptg-lesson-item[data-question-id="' + questionId + '"]');
                    var $toolbar = $item.find('.ptg-question-toolbar');
                    
                    if (status.bookmark) {
                        $toolbar.find('.ptg-btn-bookmark').addClass('is-active');
                    } else {
                        $toolbar.find('.ptg-btn-bookmark').removeClass('is-active');
                    }
                    
                    if (status.review) {
                        $toolbar.find('.ptg-btn-review').addClass('is-active');
                    } else {
                        $toolbar.find('.ptg-btn-review').removeClass('is-active');
                    }
                    
                    if (status.memo) {
                        $toolbar.find('.ptg-btn-notes').addClass('is-active');
                    } else {
                        $toolbar.find('.ptg-btn-notes').removeClass('is-active');
                    }
                    
                    if (status.flashcard) {
                        $toolbar.find('.ptg-btn-flashcard').addClass('is-active');
                    } else {
                        $toolbar.find('.ptg-btn-flashcard').removeClass('is-active');
                    }
                },
                error: function(err) {
                    console.error('Failed to fetch user status for question ' + questionId, err);
                }
            });
        },

        bindGlobalEvents: function() {
            // Contextual Action Button - Toggle Toolbar
            $(document).off('click', '.ptg-contextual-action-btn').on('click', '.ptg-contextual-action-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $btn = $(this);
                var $lessonItem = $btn.closest('.ptg-lesson-item');
                var $toolbar = $lessonItem.find('.ptg-question-toolbar');

                // Close all other toolbars
                $('.ptg-question-toolbar').not($toolbar).slideUp(200);
                $('.ptg-contextual-action-btn').not($btn).css({
                    'background': 'transparent',
                    'border-color': '#ddd',
                    'color': '#666'
                });

                // Toggle current toolbar
                $toolbar.slideToggle(200, function () {
                    if ($toolbar.is(':visible')) {
                        $btn.css({
                            'background': '#4a90e2',
                            'border-color': '#4a90e2',
                            'color': 'white'
                        });
                    } else {
                        $btn.css({
                            'background': 'transparent',
                            'border-color': '#ddd',
                            'color': '#666'
                        });
                    }
                });
            });

            // Bookmark Handler
            $(document).off('click', '.ptg-btn-bookmark').on('click', '.ptg-btn-bookmark', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $btn = $(this);
                var questionId = $btn.data('question-id');
                var isActive = $btn.hasClass('is-active');

                $btn.toggleClass('is-active');

                $.ajax({
                    url: (window.location.origin || '') + '/wp-json/ptg-quiz/v1/questions/' + questionId + '/state',
                    method: 'PATCH',
                    data: JSON.stringify({ bookmarked: !isActive }),
                    contentType: 'application/json',
                    headers: {
                        'X-WP-Nonce': (window.ptgStudy && window.ptgStudy.api_nonce) || (window.wpApiSettings && window.wpApiSettings.nonce) || ''
                    },
                    success: function (response) {
                        console.log('Bookmark saved');
                    },
                    error: function (xhr) {
                        console.error('Bookmark failed');
                        $btn.toggleClass('is-active');
                        alert('북마크 저장에 실패했습니다.');
                    }
                });
            });

            // Review Handler
            $(document).off('click', '.ptg-btn-review').on('click', '.ptg-btn-review', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $btn = $(this);
                var questionId = $btn.data('question-id');
                var isActive = $btn.hasClass('is-active');

                $btn.toggleClass('is-active');

                $.ajax({
                    url: (window.location.origin || '') + '/wp-json/ptg-quiz/v1/questions/' + questionId + '/state',
                    method: 'PATCH',
                    data: JSON.stringify({ needs_review: !isActive }),
                    contentType: 'application/json',
                    headers: {
                        'X-WP-Nonce': (window.ptgStudy && window.ptgStudy.api_nonce) || (window.wpApiSettings && window.wpApiSettings.nonce) || ''
                    },
                    success: function (response) {
                        console.log('Review mark saved');
                    },
                    error: function (xhr) {
                        console.error('Review mark failed');
                        $btn.toggleClass('is-active');
                        alert('복습 표시 저장에 실패했습니다.');
                    }
                });
            });

            // Memo Handler
            $(document).off('click', '.ptg-btn-notes').on('click', '.ptg-btn-notes', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $btn = $(this);
                var questionId = $btn.data('question-id');
                var $lessonItem = $btn.closest('.ptg-lesson-item');
                
                // Check if memo area already exists
                var $existingMemo = $lessonItem.find('.ptg-memo-inline-area');
                if ($existingMemo.length > 0) {
                    // Toggle visibility
                    $existingMemo.slideToggle(200);
                    return;
                }

                // Get current memo and show inline textarea
                $.ajax({
                    url: (window.location.origin || '') + '/wp-json/ptg-quiz/v1/questions/' + questionId + '/memo',
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': (window.ptgStudy && window.ptgStudy.api_nonce) || (window.wpApiSettings && window.wpApiSettings.nonce) || ''
                    },
                    success: function (response) {
                        var currentContent = response && response.content ? response.content : '';
                        PTGStudyToolbar.showInlineMemo($lessonItem, questionId, currentContent);
                    },
                    error: function () {
                        PTGStudyToolbar.showInlineMemo($lessonItem, questionId, '');
                    }
                });
            });

            // Flashcard Handler
            $(document).off('click', '.ptg-btn-flashcard').on('click', '.ptg-btn-flashcard', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var questionId = $(this).data('question-id');
                var $lessonItem = $(this).closest('.ptg-lesson-item');

                // Helper function to convert HTML to text while preserving line breaks
                function htmlToText($element) {
                    var clone = $element.clone();
                    // Replace <br> with newline
                    clone.find('br').replaceWith('\n');
                    // Get text content
                    return clone.text().trim();
                }

                // Get complete question content (text + options as displayed)
                var questionText = '';
                
                // Get question text (including question number)
                var $questionText = $lessonItem.find('.ptg-question-text');
                if ($questionText.length > 0) {
                    questionText = htmlToText($questionText);
                }
                
                // Get question options
                var $questionOptions = $lessonItem.find('.ptg-question-options');
                if ($questionOptions.length > 0) {
                    var optionsText = '';
                    $questionOptions.find('.ptg-question-option').each(function() {
                        optionsText += '\n' + $(this).text().trim();
                    });
                    questionText += optionsText;
                }
                
                // Get answer and explanation separately
                var answerText = '';
                var $answerContent = $lessonItem.find('.answer-content');
                
                if ($answerContent.length > 0) {
                    // Extract answer (first <p> contains "정답: X")
                    var $answerP = $answerContent.find('p').first();
                    var answerValue = '';
                    if ($answerP.length > 0) {
                        // Get just the answer value (e.g., "1", "2", etc.)
                        answerValue = $answerP.text().replace(/정답:\s*/, '').trim();
                    }
                    
                    // Extract explanation (inside the <div> after <hr>)
                    var $explanationDiv = $answerContent.find('div').last();
                    var explanationValue = '';
                    if ($explanationDiv.length > 0) {
                        explanationValue = htmlToText($explanationDiv);
                    }
                    
                    // Combine: "정답: X" + newline + explanation
                    answerText = '정답: ' + answerValue;
                    if (explanationValue) {
                        answerText += '\n' + explanationValue;
                    }
                }

                PTGStudyToolbar.showFlashcardModal(questionId, questionText, answerText);
            });
        },

        showInlineMemo: function($lessonItem, questionId, initialContent) {
            // Create inline memo area HTML
            var memoHtml = 
                '<div class="ptg-memo-inline-area" style="margin-top: 16px; padding: 16px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px;">' +
                    '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">' +
                        '<strong style="font-size: 14px; color: #333;">📝 메모</strong>' +
                        '<button class="ptg-memo-close-btn" style="background: transparent; border: none; color: #666; cursor: pointer; font-size: 20px; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">&times;</button>' +
                    '</div>' +
                    '<textarea class="ptg-memo-textarea" data-question-id="' + questionId + '" placeholder="메모를 입력하세요... (포커스를 잃으면 자동 저장됩니다)" style="width: 100%; min-height: 100px; padding: 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-family: inherit; font-size: 14px; line-height: 1.6; resize: vertical; box-sizing: border-box;">' + 
                        (initialContent || '') + 
                    '</textarea>' +
                    '<div class="ptg-memo-status" style="margin-top: 8px; font-size: 12px; color: #666; min-height: 18px;"></div>' +
                '</div>';
            
            // Append to lesson item
            $lessonItem.find('.ptg-lesson-answer-area').after(memoHtml);
            
            var $memoArea = $lessonItem.find('.ptg-memo-inline-area');
            var $textarea = $memoArea.find('.ptg-memo-textarea');
            var $status = $memoArea.find('.ptg-memo-status');
            
            // Focus on textarea
            $textarea.focus();
            
            // Close button handler
            $memoArea.find('.ptg-memo-close-btn').on('click', function() {
                $memoArea.slideUp(200, function() {
                    $(this).remove();
                });
            });
            
            // Auto-save on blur
            $textarea.on('blur', function() {
                var content = $(this).val();
                var qId = $(this).data('question-id');
                
                $status.text('저장 중...').css('color', '#666');
                
                $.ajax({
                    url: (window.location.origin || '') + '/wp-json/ptg-quiz/v1/questions/' + qId + '/memo',
                    method: 'POST',
                    data: JSON.stringify({ content: content }),
                    contentType: 'application/json',
                    headers: {
                        'X-WP-Nonce': (window.ptgStudy && window.ptgStudy.api_nonce) || (window.wpApiSettings && window.wpApiSettings.nonce) || ''
                    },
                    success: function () {
                        $status.text('✓ 저장되었습니다').css('color', '#10b981');
                        
                        // Update toolbar icon status
                        var $item = $('.ptg-lesson-item[data-lesson-id="' + qId + '"], .ptg-lesson-item[data-question-id="' + qId + '"]');
                        var $toolbar = $item.find('.ptg-question-toolbar');
                        if (content.trim().length > 0) {
                            $toolbar.find('.ptg-btn-notes').addClass('is-active');
                        } else {
                            $toolbar.find('.ptg-btn-notes').removeClass('is-active');
                        }

                        setTimeout(function() {
                            $status.fadeOut(300, function() {
                                $(this).text('').show();
                            });
                        }, 2000);
                    },
                    error: function () {
                        $status.text('✗ 저장 실패').css('color', '#ef4444');
                    }
                });
            });
        },


        showFlashcardModal: function(questionId, front, back) {
            if ($('#ptg-study-flashcard-modal').length === 0) {
                var modalHtml = 
                    '<div id="ptg-study-flashcard-modal" class="ptg-modal" style="display: none;">' +
                        '<div class="ptg-modal-overlay"></div>' +
                        '<div class="ptg-modal-content">' +
                            '<div class="ptg-modal-header">' +
                                '<h3>암기카드 만들기</h3>' +
                                '<button class="ptg-modal-close">&times;</button>' +
                            '</div>' +
                            '<div class="ptg-modal-body">' +
                                '<div class="form-group">' +
                                    '<label>앞면 (질문)</label>' +
                                    '<textarea id="ptg-flashcard-front" rows="4"></textarea>' +
                                '</div>' +
                                '<div class="form-group">' +
                                    '<label>뒷면 (답변/해설)</label>' +
                                    '<textarea id="ptg-flashcard-back" rows="4"></textarea>' +
                                '</div>' +
                            '</div>' +
                            '<div class="ptg-modal-footer">' +
                                '<div class="ptg-flashcard-status" style="flex: 1; font-size: 14px; color: #666;"></div>' +
                                '<button class="ptg-btn ptg-btn-secondary ptg-modal-cancel">취소</button>' +
                                '<button class="ptg-btn ptg-btn-primary ptg-flashcard-save">저장</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                $('body').append(modalHtml);

                $('#ptg-study-flashcard-modal').on('click', '.ptg-modal-close, .ptg-modal-cancel, .ptg-modal-overlay', function () {
                    $('#ptg-study-flashcard-modal').fadeOut(200);
                });

                // Save handler (bound once)
                $('#ptg-study-flashcard-modal').on('click', '.ptg-flashcard-save', function () {
                    var frontText = $('#ptg-flashcard-front').val();
                    var backText = $('#ptg-flashcard-back').val();
                    var qId = $('#ptg-study-flashcard-modal').data('question-id');
                    var $status = $('#ptg-study-flashcard-modal .ptg-flashcard-status');

                    // Validate input
                    if (!frontText || !backText) {
                        $status.text('✗ 앞면과 뒷면 내용을 모두 입력해주세요').css('color', '#ef4444');
                        return;
                    }

                    if (!qId) {
                        $status.text('✗ 문제 ID를 찾을 수 없습니다').css('color', '#ef4444');
                        return;
                    }

                    console.log('Flashcard save attempt:', { qId: qId, frontLength: frontText.length, backLength: backText.length });

                    $status.text('세트 정보 확인 중...').css('color', '#666');

                    // First, get the user's default set_id
                    $.ajax({
                        url: (window.location.origin || '') + '/wp-json/ptg-flash/v1/sets',
                        method: 'GET',
                        headers: {
                            'X-WP-Nonce': (window.ptgStudy && window.ptgStudy.api_nonce) || (window.wpApiSettings && window.wpApiSettings.nonce) || ''
                        },
                        success: function(sets) {
                            console.log('Sets fetched successfully:', sets);
                            var setId = (sets && sets.length > 0) ? sets[0].set_id : 1;
                            console.log('Using set_id:', setId);
                            
                            $status.text('저장 중...').css('color', '#666');
                            
                            // Now create the flashcard
                            $.ajax({
                                url: (window.location.origin || '') + '/wp-json/ptg-flash/v1/cards',
                                method: 'POST',
                                data: JSON.stringify({
                                    set_id: setId,
                                    source_type: 'question',
                                    source_id: qId,
                                    front: frontText,
                                    back: backText
                                }),
                                contentType: 'application/json',
                                headers: {
                                    'X-WP-Nonce': (window.ptgStudy && window.ptgStudy.api_nonce) || (window.wpApiSettings && window.wpApiSettings.nonce) || ''
                                },
                                success: function (response) {
                                    console.log('Flashcard saved successfully:', response);
                                    $status.text('✓ 저장되었습니다').css('color', '#10b981');
                                    
                                    // Update toolbar icon status
                                    var $item = $('.ptg-lesson-item[data-lesson-id="' + qId + '"], .ptg-lesson-item[data-question-id="' + qId + '"]');
                                    var $toolbar = $item.find('.ptg-question-toolbar');
                                    $toolbar.find('.ptg-btn-flashcard').addClass('is-active');

                                    setTimeout(function() {
                                        $('#ptg-study-flashcard-modal').fadeOut(200, function() {
                                            $status.text('').css('color', '#666');
                                        });
                                    }, 1500);
                                },
                                error: function (xhr, status, error) {
                                    console.error('Flashcard save failed:', {
                                        status: xhr.status,
                                        statusText: xhr.statusText,
                                        response: xhr.responseJSON || xhr.responseText,
                                        error: error
                                    });
                                    
                                    var errorMsg = '✗ 저장 실패';
                                    if (xhr.responseJSON && xhr.responseJSON.message) {
                                        errorMsg += ': ' + xhr.responseJSON.message;
                                    } else if (xhr.status === 404) {
                                        errorMsg += ': API 없음';
                                    } else if (xhr.status === 401 || xhr.status === 403) {
                                        errorMsg += ': 권한 없음';
                                    }
                                    $status.text(errorMsg).css('color', '#ef4444');
                                }
                            });
                        },
                        error: function(xhr, status, error) {
                            console.error('Sets fetch failed:', {
                                status: xhr.status,
                                statusText: xhr.statusText,
                                response: xhr.responseJSON || xhr.responseText,
                                error: error
                            });
                            $status.text('✗ 세트 정보를 가져올 수 없습니다').css('color', '#ef4444');
                        }
                    });
                });
            }

            $('#ptg-flashcard-front').val(front);
            $('#ptg-flashcard-back').val(back);
            // Clear status message when opening modal
            $('#ptg-study-flashcard-modal .ptg-flashcard-status').text('').css('color', '#666');
            $('#ptg-study-flashcard-modal').data('question-id', questionId).fadeIn(200);
        }
    };

    // Initialize on ready
    $(document).ready(function() {
        PTGStudyToolbar.init();
    });

})(jQuery);
