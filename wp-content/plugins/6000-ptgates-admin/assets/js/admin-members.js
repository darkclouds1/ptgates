jQuery(document).ready(function ($) {
    // 등급 수정 모달 열기
    $('.ptg-edit-grade-btn').on('click', function (e) {
        e.preventDefault();
        var userId = $(this).data('user-id');

        // 데이터 로드
        $.post(ajaxurl, {
            action: 'ptg_admin_get_member',
            user_id: userId
        }, function (response) {
            if (response.success) {
                var data = response.data;
                $('#ptg-edit-user-id').val(data.user_id);
                $('#ptg-edit-grade').val(data.member_grade);
                $('#ptg-edit-status').val(data.billing_status);

                if (data.billing_expiry_date) {
                    // datetime에서 date 부분만 추출 (YYYY-MM-DD)
                    var datePart = data.billing_expiry_date.split(' ')[0];
                    $('#ptg-edit-expiry').val(datePart);
                } else {
                    $('#ptg-edit-expiry').val('');
                }

                $('#ptg-grade-modal').show();
            } else {
                alert(response.data);
            }
        });
    });

    // 등급 수정 폼 제출
    $('#ptg-grade-form').on('submit', function (e) {
        e.preventDefault();
        var formData = $(this).serialize();

        $.post(ajaxurl, {
            action: 'ptg_admin_update_member',
            user_id: $('#ptg-edit-user-id').val(),
            member_grade: $('#ptg-edit-grade').val(),
            billing_status: $('#ptg-edit-status').val(),
            billing_expiry_date: $('#ptg-edit-expiry').val()
        }, function (response) {
            if (response.success) {
                alert('저장되었습니다.');
                $('#ptg-grade-modal').hide();
                location.reload(); // 목록 갱신
            } else {
                alert(response.data);
            }
        });
    });

    // 결제 이력 모달 열기
    $('.ptg-view-history-btn').on('click', function (e) {
        e.preventDefault();
        var userId = $(this).data('user-id');

        $('#ptg-history-content').html('<p>로딩 중...</p>');
        $('#ptg-history-modal').show();

        $.post(ajaxurl, {
            action: 'ptg_admin_get_history',
            user_id: userId
        }, function (response) {
            if (response.success) {
                $('#ptg-history-content').html(response.data);
            } else {
                $('#ptg-history-content').html('<p>오류: ' + response.data + '</p>');
            }
        });
    });

    // 모달 닫기
    $('.ptg-modal-close').on('click', function () {
        $('.ptg-modal').hide();
    });

    // 모달 외부 클릭 시 닫기
    $(window).on('click', function (e) {
        if ($(e.target).hasClass('ptg-modal')) {
            $('.ptg-modal').hide();
        }
    });
});
