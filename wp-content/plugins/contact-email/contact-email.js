jQuery(document).ready(function ($) {
    'use strict';

    const form = $('#contact-email-form');
    const submitBtn = form.find('.submit-btn');
    const btnText = submitBtn.find('.btn-text');
    const btnLoading = submitBtn.find('.btn-loading');
    const messageDiv = form.find('.form-message');

    // 디버그: 폼 로드 확인
    console.log('Contact Email Form 로드됨');
    console.log('AJAX URL:', contactEmailAjax.ajaxurl);
    console.log('폼 발견:', form.length > 0);

    // 폼 제출 이벤트
    form.on('submit', function (e) {
        e.preventDefault();
        console.log('폼 제출 시작');

        // 이미 제출 중이면 리턴
        if (submitBtn.prop('disabled')) {
            return;
        }

        // 입력값 가져오기
        const company = $('#contact_company').val().trim();
        const name = $('#contact_name').val().trim();
        const phone = $('#contact_phone').val().trim();
        const email = $('#contact_email').val().trim();
        const message = $('#contact_message').val().trim();

        // 디버그: 입력값 확인
        console.log('입력값 확인:', {
            company: company,
            name: name,
            phone: phone,
            email: email,
            message: message,
            company_empty: !company,
            name_empty: !name,
            phone_empty: !phone,
            email_empty: !email,
            message_empty: !message
        });

        // 클라이언트 측 검증
        if (!company || !name || !phone || !email || !message) {
            console.log('❌ 필드 검증 실패 - 빈 필드가 있음');
            showMessage('모든 필드를 입력해주세요.', 'error');
            return;
        }
        console.log('✅ 필드 검증 통과');

        // 이메일 유효성 검증
        if (!isValidEmail(email)) {
            showMessage('유효한 이메일 주소를 입력해주세요.', 'error');
            return;
        }

        // 버튼 비활성화 및 로딩 표시
        submitBtn.prop('disabled', true);
        btnText.hide();
        btnLoading.show();

        console.log('AJAX 요청 전송 중...', {
            company: company,
            name: name,
            phone: phone,
            email: email,
            message: message.substring(0, 50) + '...'
        });

        // AJAX 요청
        $.ajax({
            url: contactEmailAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'submit_contact_email',
                nonce: contactEmailAjax.nonce,
                company: company,
                name: name,
                phone: phone,
                email: email,
                message: message
            },
            success: function (response) {
                console.log('AJAX 응답 받음:', response);
                if (response.success) {
                    console.log('✅ 이메일 발송 성공');
                    showMessage(response.data.message, 'success');
                    form[0].reset(); // 폼 초기화
                } else {
                    console.log('❌ 이메일 발송 실패:', response.data.message);
                    showMessage(response.data.message, 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('❌ AJAX Error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });

                // Try to recover if response contains success JSON despite noise
                try {
                    // Look for {"success":true
                    if (xhr.responseText && xhr.responseText.indexOf('{"success":true') !== -1) {
                        console.log('✅ 이메일 발송 성공 (복구됨)');
                        // Extract the message if possible, or use default
                        let msg = '정상적으로 제출되었습니다.';
                        try {
                            const jsonMatch = xhr.responseText.match(/\{"success":true.*?\}/);
                            if (jsonMatch) {
                                const response = JSON.parse(jsonMatch[0]);
                                if (response.data && response.data.message) {
                                    msg = response.data.message;
                                }
                            }
                        } catch (e) { }

                        showMessage(msg, 'success');
                        form[0].reset();
                        return;
                    }
                } catch (e) {
                    console.error('JSON parsing failed during recovery', e);
                }

                showMessage('서버 오류가 발생했습니다. 잠시 후 다시 시도해주세요.', 'error');
            },
            complete: function () {
                console.log('AJAX 요청 완료');
                // 버튼 다시 활성화
                submitBtn.prop('disabled', false);
                btnText.show();
                btnLoading.hide();
            }
        });
    });

    // 메시지 표시 함수
    function showMessage(text, type) {
        messageDiv
            .removeClass('success error')
            .addClass(type + ' show')
            .text(text);

        // 메시지를 부드럽게 스크롤
        $('html, body').animate({
            scrollTop: messageDiv.offset().top - 100
        }, 300);

        // 성공 메시지는 5초 후 자동 숨김
        if (type === 'success') {
            setTimeout(function () {
                messageDiv.removeClass('show');
            }, 5000);
        }
    }

    // 이메일 유효성 검증 함수
    function isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    // 입력 필드 포커스 시 에러 메시지 숨김
    form.find('.form-control').on('focus', function () {
        if (messageDiv.hasClass('error')) {
            messageDiv.removeClass('show');
        }
    });
});

