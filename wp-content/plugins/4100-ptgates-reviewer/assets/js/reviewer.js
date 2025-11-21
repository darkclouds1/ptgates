(function($) {
    'use strict';

    const Reviewer = {
        init: function() {
            this.container = $('#ptg-reviewer-app');
            if (!this.container.length) return;

            this.config = window.ptgReviewer || {};
            this.mode = this.container.data('mode') || 'today';

            this.loadReviews();
        },

        loadReviews: function() {
            const self = this;
            
            $.ajax({
                url: self.config.restUrl + 'today',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
                },
                success: function(response) {
                    self.renderList(response);
                },
                error: function(err) {
                    self.container.html('<div class="ptg-error">복습 목록을 불러오는데 실패했습니다.</div>');
                    console.error(err);
                }
            });
        },

        renderList: function(questions) {
            if (!questions || questions.length === 0) {
                this.container.html('<div class="ptg-empty">오늘 복습할 내용이 없습니다. 🎉</div>');
                return;
            }

            let html = '<div class="ptg-review-list">';
            html += '<h3>오늘의 복습 (' + questions.length + ')</h3>';
            html += '<ul>';
            
            questions.forEach(function(q) {
                // Assuming there is a way to link to the quiz, e.g., a shortcode page or direct link
                // For now, we'll just show the title and a "Start" button that might link to a quiz page
                // In a real scenario, this URL structure needs to be defined in the platform settings or passed via config
                const quizUrl = '/quiz/?id=' + q.question_id; 
                
                html += '<li class="ptg-review-item">';
                html += '<span class="ptg-review-title">[' + q.type + '] ' + (q.content ? q.content.substring(0, 50) + '...' : '문제 #' + q.question_id) + '</span>';
                html += '<a href="' + quizUrl + '" class="ptg-btn ptg-btn-sm">복습하기</a>';
                html += '</li>';
            });

            html += '</ul></div>';
            this.container.html(html);
        }
    };

    $(document).ready(function() {
        Reviewer.init();
    });

})(jQuery);
