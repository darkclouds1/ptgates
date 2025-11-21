(function ($) {
    'use strict';

    const SelfTest = {
        init: function () {
            this.container = $('#ptg-selftest-app');
            if (!this.container.length) return;

            this.config = window.ptgSelfTest || {};
            this.bindEvents();
        },

        bindEvents: function () {
            const self = this;

            this.container.on('submit', '#ptg-selftest-form', function (e) {
                e.preventDefault();
                self.generateTest();
            });
        },

        generateTest: function () {
            const self = this;
            const subject = $('#ptg-st-subject').val();
            const count = $('#ptg-st-count').val();
            const btn = this.container.find('button[type="submit"]');

            btn.prop('disabled', true).text('생성 중...');

            $.ajax({
                url: self.config.restUrl + 'generate',
                method: 'POST',
                data: { subject: subject, count: count },
                beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
                success: function (response) {
                    if (response.success && response.ids) {
                        // Redirect to Quiz Page
                        // IMPORTANT: You must create a page with slug 'quiz' and content [ptg_quiz]
                        window.location.href = '/quiz?ids=' + response.ids + '&timer=' + (count * 1.5); // Approx 1.5 min per question
                    } else {
                        alert('문제를 생성하지 못했습니다.');
                        btn.prop('disabled', false).text('모의고사 시작');
                    }
                },
                error: function (err) {
                    alert('오류가 발생했습니다: ' + (err.responseJSON ? err.responseJSON.message : err.statusText));
                    btn.prop('disabled', false).text('모의고사 시작');
                }
            });
        }
    };

    $(document).ready(function () {
        SelfTest.init();
    });

})(jQuery);
