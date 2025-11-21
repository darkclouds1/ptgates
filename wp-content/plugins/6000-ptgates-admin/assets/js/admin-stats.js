/**
 * PTGates Admin 통계 대시보드 JavaScript
 */

(function ($) {
    'use strict';

    // 초기화
    $(document).ready(function () {
        // 통계 컨테이너가 없으면 종료
        if ($('.ptg-admin-stats-container').length === 0) {
            return;
        }

        // 초기 데이터 로드
        loadOverallStatistics();
        loadExamYears();

        // 이벤트 리스너
        $('#ptg-stats-refresh').on('click', function () {
            loadSubjectStatistics();
        });
    });

    /**
     * 전체 통계 및 회차별 현황 로드
     */
    function loadOverallStatistics() {
        $.ajax({
            url: ptgAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'get_question_statistics'
            },
            success: function (response) {
                // JSON 파싱 (문자열로 오는 경우)
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch (e) {
                        console.error('JSON 파싱 오류:', e);
                        return;
                    }
                }

                if (response.success) {
                    // 총 문제 수 업데이트
                    $('#ptg-total-count').text(parseInt(response.total_count).toLocaleString() + '문제');

                    // 최근 업데이트 날짜 (첫 번째 항목의 날짜)
                    if (response.statistics && response.statistics.length > 0) {
                        $('#ptg-last-update').text('최근 업데이트: ' + response.statistics[0].formatted_date);
                    }

                    // 회차별 테이블 렌더링
                    renderExamStatsTable(response.statistics);
                } else {
                    console.error('통계 로드 실패:', response.message);
                }
            },
            error: function (xhr, status, error) {
                console.error('통계 로드 오류:', status, error);
            }
        });
    }

    /**
     * 회차별 통계 테이블 렌더링
     */
    function renderExamStatsTable(stats) {
        const $tbody = $('#ptg-exam-stats-table tbody');
        $tbody.empty();

        if (!stats || stats.length === 0) {
            $tbody.html('<tr><td colspan="5">데이터가 없습니다.</td></tr>');
            return;
        }

        stats.forEach(function (stat) {
            const row = `
                <tr>
                    <td>${stat.exam_year}년</td>
                    <td>${stat.exam_session ? stat.exam_session + '회' : '-'}</td>
                    <td>${stat.exam_course}</td>
                    <td><strong>${parseInt(stat.question_count).toLocaleString()}</strong></td>
                    <td>${stat.formatted_date || '-'}</td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    /**
     * 년도 목록 로드 (필터용)
     */
    function loadExamYears() {
        $.ajax({
            url: ptgAdmin.apiUrl + 'exam-years',
            method: 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', ptgAdmin.nonce);
            },
            success: function (response) {
                if (response.success && Array.isArray(response.data)) {
                    const $select = $('#ptg-stats-year');
                    $select.html('<option value="">년도 선택</option>');

                    response.data.forEach(function (year) {
                        $select.append($('<option>', {
                            value: year,
                            text: year + '년'
                        }));
                    });

                    // 가장 최근 년도 자동 선택 및 조회
                    if (response.data.length > 0) {
                        $select.val(response.data[0]);
                        loadSubjectStatistics();
                    }
                }
            }
        });
    }

    /**
     * 과목별 통계 로드
     */
    function loadSubjectStatistics() {
        const year = $('#ptg-stats-year').val();
        const course = $('#ptg-stats-course').val();

        if (!year) {
            $('#ptg-subject-chart').html('<p class="ptg-chart-placeholder">년도를 선택해주세요.</p>');
            return;
        }

        $('#ptg-subject-chart').html('<p class="ptg-loading">로딩 중...</p>');

        $.ajax({
            url: ptgAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'get_subject_statistics',
                exam_year: year,
                exam_course: course
            },
            success: function (response) {
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch (e) {
                        console.error('JSON 파싱 오류:', e);
                        return;
                    }
                }

                if (response.success) {
                    renderSubjectChart(response.subjects, response.total_count);
                } else {
                    $('#ptg-subject-chart').html('<p>데이터를 불러올 수 없습니다.</p>');
                }
            },
            error: function () {
                $('#ptg-subject-chart').html('<p>오류가 발생했습니다.</p>');
            }
        });
    }

    /**
     * 과목별 차트 렌더링 (CSS Bar Chart)
     */
    function renderSubjectChart(subjects, total) {
        const $container = $('#ptg-subject-chart');
        $container.empty();

        if (!subjects || subjects.length === 0) {
            $container.html('<p class="ptg-chart-placeholder">해당 조건의 데이터가 없습니다.</p>');
            return;
        }

        // 최대값 찾기 (그래프 비율 계산용)
        let maxCount = 0;
        subjects.forEach(function (s) {
            const count = parseInt(s.question_count);
            if (count > maxCount) maxCount = count;
        });

        const chartHtml = $('<div class="ptg-bar-chart"></div>');

        subjects.forEach(function (s) {
            const count = parseInt(s.question_count);
            const percentage = maxCount > 0 ? (count / maxCount) * 100 : 0;
            const label = s.subject;

            // 랜덤 파스텔 컬러
            const hue = Math.floor(Math.random() * 360);
            const color = `hsl(${hue}, 70%, 80%)`;
            const borderColor = `hsl(${hue}, 70%, 60%)`;

            const barItem = `
                <div class="ptg-chart-item">
                    <div class="ptg-chart-label" title="${label}">${label}</div>
                    <div class="ptg-chart-bar-wrap">
                        <div class="ptg-chart-bar" style="width: ${percentage}%; background-color: ${color}; border-color: ${borderColor};">
                            <span class="ptg-chart-value">${count}</span>
                        </div>
                    </div>
                </div>
            `;
            chartHtml.append(barItem);
        });

        $container.append(chartHtml);
        $container.append(`<div class="ptg-chart-total">총 ${total} 문항</div>`);
    }

})(jQuery);
