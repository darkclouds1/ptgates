/**
 * PTGates Platform - 공통 팝업(Tip) 내용 정의
 * 
 * 모든 팝업 창의 HTML 내용을 중앙에서 관리합니다.
 * - map-tip: 공통 MAP 구조 설명
 * - quiz-tip: 퀴즈 학습 Tip
 * - study-tip: 학습 Tip
 * - timer-tip: 시간관리 Tip
 */

(function() {
    'use strict';
    
    // 전역 네임스페이스
    window.PTGTips = window.PTGTips || {};
    
    /**
     * 팝업 내용 저장소
     */
    const TipContents = {
        /**
         * map-tip: 공통 MAP 구조 설명
         */
        'map-tip': {
            title: '공통 MAP 구조',
            content: `
                <div style="text-align: left; line-height: 1.8;">
                    <p style="margin: 0 0 16px 0; color: #4b5563;">물리치료사 국가고시 과목 체계를 정의한 표준 구조입니다.</p>
                    
                    <div style="margin-bottom: 20px;">
                        <h4 style="margin: 0 0 10px 0; color: #374151; font-size: 16px;">1교시 (총 105문항)</h4>
                        <div style="margin: 0; padding-left: 0; color: #4b5563;">
                            <p style="margin: 0 0 12px 0; line-height: 1.6;">
                                <strong>물리치료 기초</strong> (60문항) : 해부생리학 (22문항), 운동학 (12문항), 물리적 인자치료 (16문항), 공중보건학 (10문항)
                            </p>
                            <p style="margin: 0 0 0 0; line-height: 1.6;">
                                <strong>물리치료 진단평가</strong> (45문항) : 근골격계 물리치료 진단평가 (10문항), 신경계 물리치료 진단평가 (16문항), 진단평가 원리 (6문항), 심폐혈관계 검사 및 평가 (4문항), 기타 계통 검사 (2문항), 임상의사결정 (7문항)
                            </p>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <h4 style="margin: 0 0 10px 0; color: #374151; font-size: 16px;">2교시 (총 85문항)</h4>
                        <div style="margin: 0; padding-left: 0; color: #4b5563;">
                            <p style="margin: 0 0 12px 0; line-height: 1.6;">
                                <strong>물리치료 중재</strong> (65문항) : 근골격계 중재 (28문항), 신경계 중재 (25문항), 심폐혈관계 중재 (5문항), 림프, 피부계 중재 (2문항), 물리치료 문제해결 (5문항)
                            </p>
                            <p style="margin: 0 0 0 0; line-height: 1.6;">
                                <strong>의료관계법규</strong> (20문항) : 의료법 (5문항), 의료기사법 (5문항), 노인복지법 (4문항), 장애인복지법 (3문항), 국민건강보험법 (3문항)
                            </p>
                        </div>
                    </div>

                    <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                        <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 13px; line-height: 1.6;">
                            ※ 북마크 문제는 이 MAP 순서에 따라 과목별로 정렬되며, 동일 과목 내에서는 랜덤하게 섞여서 표시됩니다.
                        </p>
                        <p style="margin: 0 0 0 0; color: #6b7280; font-size: 13px; line-height: 1.6;">
                            제시된 MAP의 문항수는 물리치료사 국가고시의 표준 배점을 따르므로 고정된 것으로 정의되지만, <strong>국가시험관리기관의 결정(과목별 배점 비율 변경 등)</strong>에 따라 향후 변경될 개연성은 존재합니다. 또한, 학습 플랫폼 내에서는 사용자가 학습 목적에 맞게 문항수를 임의로 조정하여 학습 및 퀴즈 기능이 제공됩니다.
                        </p>
                    </div>
                </div>
            `,
            maxWidth: 600
        },
        
        /**
         * quiz-tip: 실전 모의 학습 가이드
         */
        'quiz-tip': {
            title: '실전 모의 학습 가이드',
            content: `
                <div style="text-align: left; line-height: 1.8;">
                    <!-- 출제 순서 경향 -->
                    <div style="margin-bottom: 30px;">
                        <h3 style="color: #4a90e2; border-bottom: 2px solid #f1f3f5; padding-bottom: 10px; margin-bottom: 15px;">📊 출제 순서 경향 (ptGates 적용)</h3>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <li style="margin-bottom: 10px; padding-left: 20px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>기본 흐름:</strong> 출제는 보통 <strong>기초 → 응용 → 임상</strong>의 큰 패턴을 따름. (예: 운동치료학에서 원리 → 기법 → 질환별 적용 순)</li>
                            <li style="margin-bottom: 10px; padding-left: 20px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>과목별 배치:</strong> 각 과목(예: 공중보건학) 내에서도 <strong>개론/역학</strong> 같은 범용 개념이 앞쪽에, <strong>환경/산업보건</strong> 같은 세부 응용 주제가 뒤쪽에 배치되는 경향이 명확함.</li>
                            <li style="margin-bottom: 10px; padding-left: 20px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>학습 활용:</strong> ptGates는 이 경향을 반영하여 '세부과목별 묶음 학습'과 '실제 기출 순서 학습' 모드를 모두 지원할 예정임.</li>
                        </ul>
                    </div>

                    <!-- 교시별 모의고사 구성 -->
                    <div style="margin-bottom: 30px;">
                        <h3 style="color: #4a90e2; border-bottom: 2px solid #f1f3f5; padding-bottom: 10px; margin-bottom: 15px;">🎯 교시별 모의고사 구성</h3>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <h4 style="margin: 0 0 10px 0; color: #333;">1교시 (105문항)</h4>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <li style="margin-bottom: 8px; padding-left: 20px; position: relative; font-size: 14px;"><span style="position: absolute; left: 0; color: #666;">•</span> <strong>물리치료 기초 (60문항):</strong> 해부생리학(22), 운동학(12), 물리적 인자치료(16), 공중보건학(10)</li>
                                <li style="margin-bottom: 8px; padding-left: 20px; position: relative; font-size: 14px;"><span style="position: absolute; left: 0; color: #666;">•</span> <strong>물리치료 진단평가 (45문항):</strong> 근골격계(10), 신경계(16), 진단평가 원리(6), 심폐혈관계(4), 기타(2), 임상의사결정(7)</li>
                            </ul>
                        </div>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                            <h4 style="margin: 0 0 10px 0; color: #333;">2교시 (85문항)</h4>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <li style="margin-bottom: 8px; padding-left: 20px; position: relative; font-size: 14px;"><span style="position: absolute; left: 0; color: #666;">•</span> <strong>물리치료 중재 (65문항):</strong> 근골격계(28), 신경계(25), 심폐혈관계(5), 림프/피부(2), 문제해결(5)</li>
                                <li style="margin-bottom: 8px; padding-left: 20px; position: relative; font-size: 14px;"><span style="position: absolute; left: 0; color: #666;">•</span> <strong>의료관계법규 (20문항):</strong> 의료법(5), 의료기사법(5), 노인복지법(4), 장애인복지법(3), 국민건강보험법(3)</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- 주요 기능 -->
                    <div style="margin-bottom: 30px;">
                        <h3 style="color: #4a90e2; border-bottom: 2px solid #f1f3f5; padding-bottom: 10px; margin-bottom: 15px;">🔍 주요 기능</h3>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <li style="margin-bottom: 10px; padding-left: 20px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>기본 퀴즈:</strong> 필터 없이 사용 시 5문제 랜덤 출제</li>
                            <li style="margin-bottom: 10px; padding-left: 20px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>교시/과목 선택:</strong> 특정 교시나 과목을 집중적으로 학습 가능</li>
                            <li style="margin-bottom: 10px; padding-left: 20px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>문항 수 지정:</strong> 학습 시간에 맞춰 문제 수 조절 가능</li>
                            <li style="margin-bottom: 10px; padding-left: 20px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>북마크/복습:</strong> 중요하거나 틀린 문제만 모아서 다시 풀기 (로그인 필요)</li>
                        </ul>
                    </div>
                    
                    <!-- 참고사항 -->
                    <div>
                        <h3 style="color: #4a90e2; border-bottom: 2px solid #f1f3f5; padding-bottom: 10px; margin-bottom: 15px;">📌 참고사항</h3>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <li style="margin-bottom: 10px; padding-left: 20px; position: relative;"><span style="position: absolute; left: 0; color: #666;">•</span> 기출문제는 자동으로 제외됩니다.</li>
                            <li style="margin-bottom: 10px; padding-left: 20px; position: relative;"><span style="position: absolute; left: 0; color: #666;">•</span> 전체 교시 모의고사는 국가시험 문항 구성 비율을 자동 적용합니다.</li>
                        </ul>
                    </div>
                </div>
            `,
            maxWidth: 900
        },
        
        /**
         * study-tip: 기출 학습 가이드
         */
        'study-tip': {
            title: '기출 학습 가이드',
            content: `
                <div style="text-align: left; line-height: 1.8;">
                    <!-- ptGates Study 프로그램 사용 팁 -->
                    <section style="margin-bottom: 24px;">
                        <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 16px;">💡 ptGates Study 프로그램 사용 팁</h4>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <li style="margin-bottom: 12px; padding-left: 20px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>암기카드 활용:</strong> 이해가 어렵거나 외울 부분이 많은 개념은 툴바의 암기카드 기능을 이용해 즉시 저장하고 <strong>간격 반복 학습(SRS)</strong>을 활용할 것.</li>
                            <li style="margin-bottom: 12px; padding-left: 20px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>취약점 분석:</strong> 학습 후에는 <strong>대시보드(ptgates-analytics)</strong>를 확인하여, 연관 개념 중 취약한 단원을 찾아 복습 우선순위를 정할 것.</li>
                            <li style="margin-bottom: 12px; padding-left: 20px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>연속 학습:</strong> 출제 순서 경향을 참조하여 <strong>기초 → 응용</strong> 흐름에 따라 세부 영역 묶음 단위로 끊임없이 학습하는 것을 추천함.</li>
                        </ul>
                    </section>

                    <!-- 출제 순서 경향 요약 -->
                    <section style="margin-bottom: 24px;">
                        <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 16px;">📌 출제 순서 경향 요약</h4>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <li style="margin-bottom: 10px; padding-left: 20px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>기본 흐름:</strong> 출제는 보통 <strong>기초 → 응용 → 임상</strong>의 큰 패턴을 따름.</li>
                            <li style="margin-bottom: 10px; padding-left: 20px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>과목별 배치:</strong> 각 과목 내에서 <strong>개론/역학</strong> 같은 범용 개념이 앞쪽에, 세부 응용/임상 사례가 뒤쪽에 배치되는 경향이 명확함.</li>
                        </ul>
                    </section>

                    <!-- 학습 구조 -->
                    <section style="margin-bottom: 24px;">
                        <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 16px;">🎯 학습 구조</h4>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 12px;">
                            <h5 style="margin: 0 0 8px 0; color: #333; font-size: 14px;">교시별 배열</h5>
                            <ul style="list-style: none; padding: 0; margin: 0; padding-left: 20px;">
                                <li style="margin-bottom: 6px; color: #4b5563;">• <strong>1교시:</strong> 기초(60) → 진단평가(45)</li>
                                <li style="margin-bottom: 6px; color: #4b5563;">• <strong>2교시:</strong> 중재(65) → 법규(20)</li>
                            </ul>
                        </div>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 12px;">
                            <h5 style="margin: 0 0 8px 0; color: #333; font-size: 14px;">세부 영역 순서</h5>
                            <ul style="list-style: none; padding: 0; margin: 0; padding-left: 20px;">
                                <li style="margin-bottom: 6px; color: #4b5563;">• <strong>기초:</strong> 해부생리 → 운동학 → 물리적 인자 → 공중보건</li>
                                <li style="margin-bottom: 6px; color: #4b5563;">• <strong>중재:</strong> 근골격 → 신경계 → 기타(심폐/피부/문제해결)</li>
                            </ul>
                        </div>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                            <h5 style="margin: 0 0 8px 0; color: #333; font-size: 14px;">학습 전략</h5>
                            <ul style="list-style: none; padding: 0; margin: 0; padding-left: 20px;">
                                <li style="margin-bottom: 6px; color: #4b5563;">• 교시·과목·세부영역 <strong>묶음</strong>으로 연속 학습</li>
                                <li style="margin-bottom: 6px; color: #4b5563;">• 정렬 모드로 <strong>흐름</strong> 익힌 뒤, 랜덤으로 <strong>복습</strong></li>
                            </ul>
                        </div>
                    </section>
                </div>
            `,
            maxWidth: 900
        },
        
        /**
         * timer-tip: 시간관리 Tip
         */
        'timer-tip': {
            title: '물리치료사 국가시험 시간관리 가이드',
            content: `
                <div style="text-align: left; line-height: 1.8;">
                    <p style="margin: 0 0 16px 0; color: #4b5563;">물리치료사 국가시험은 전체 260문항에 총 250분의 시험 시간이 주어지므로, 전체적으로 한 문제당 평균 약 57.7초를 배분하여 풀어야 합니다.</p>
                    
                    <p style="margin: 0 0 16px 0; color: #4b5563;">하지만 각 교시별로 문항 수와 시간이 다르기 때문에, 실제 시험에서는 각 교시의 할당 시간에 맞춰 문제를 풀어야 합니다.</p>
                    
                    <p style="margin: 0 0 16px 0; color: #4b5563;">다음은 제48회 국가시험부터 적용된 교시별 평균 소요 시간입니다:</p>
                    
                    <table style="width: 100%; border-collapse: collapse; margin: 20px 0; border: 1px solid #e5e7eb;">
                        <thead>
                            <tr style="background-color: #f3f4f6;">
                                <th style="padding: 12px; text-align: left; border: 1px solid #e5e7eb; font-weight: 600; color: #374151;">교시</th>
                                <th style="padding: 12px; text-align: left; border: 1px solid #e5e7eb; font-weight: 600; color: #374151;">시험 과목 (총 문항 수)</th>
                                <th style="padding: 12px; text-align: center; border: 1px solid #e5e7eb; font-weight: 600; color: #374151;">시험 시간 (분)</th>
                                <th style="padding: 12px; text-align: center; border: 1px solid #e5e7eb; font-weight: 600; color: #374151;">한 문제당 평균 시간 (초)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 12px; border: 1px solid #e5e7eb; color: #4b5563;">1교시</td>
                                <td style="padding: 12px; border: 1px solid #e5e7eb; color: #4b5563;">물리치료 기초 + 진단평가 (105문항)</td>
                                <td style="padding: 12px; text-align: center; border: 1px solid #e5e7eb; color: #4b5563;">90분</td>
                                <td style="padding: 12px; text-align: center; border: 1px solid #e5e7eb; color: #4b5563;">약 51.4초</td>
                            </tr>
                            <tr style="background-color: #f9fafb;">
                                <td style="padding: 12px; border: 1px solid #e5e7eb; color: #4b5563;">2교시</td>
                                <td style="padding: 12px; border: 1px solid #e5e7eb; color: #4b5563;">물리치료 중재 + 의료관계법규 (85문항)</td>
                                <td style="padding: 12px; text-align: center; border: 1px solid #e5e7eb; color: #4b5563;">75분</td>
                                <td style="padding: 12px; text-align: center; border: 1px solid #e5e7eb; color: #4b5563;">약 52.9초</td>
                            </tr>
                            <tr>
                                <td style="padding: 12px; border: 1px solid #e5e7eb; color: #4b5563;">3교시</td>
                                <td style="padding: 12px; border: 1px solid #e5e7eb; color: #4b5563;">실기시험 (70문항)</td>
                                <td style="padding: 12px; text-align: center; border: 1px solid #e5e7eb; color: #4b5563;">85분</td>
                                <td style="padding: 12px; text-align: center; border: 1px solid #e5e7eb; color: #4b5563;">약 72.8초</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 24px; padding: 16px; background-color: #f0f9ff; border-left: 4px solid #0ea5e9; border-radius: 4px;">
                        <h4 style="margin: 0 0 12px 0; color: #0c4a6e; font-size: 16px; font-weight: 700;">핵심 요약:</h4>
                        <ul style="margin: 0 0 12px 0; padding-left: 20px; color: #075985;">
                            <li style="margin-bottom: 8px;"><strong>필기(1/2교시):</strong> 문제당 약 51~53초로, 1분 이내에 문제를 해결하는 속도가 요구됩니다.</li>
                            <li style="margin-bottom: 8px;"><strong>실기(3교시):</strong> 문제당 약 73초로, 필기시험에 비해 상대적으로 시간이 더 많이 주어집니다.</li>
                        </ul>
                        <p style="margin: 0; color: #075985;">물리치료사 국시는 과목 수와 문제 수가 많으므로, 시간 관리가 합격을 좌우하는 중요한 요소입니다. 따라서 실제 시험 시간과 동일하게 모의고사를 치르면서 시간 배분을 철저히 훈련하는 것이 중요합니다.</p>
                    </div>
                </div>
            `,
            maxWidth: 900
        }
    };
    
    /**
     * 팝업 내용 가져오기
     * 
     * @param {string} tipName 팝업 이름
     * @returns {object|null} 팝업 옵션 또는 null
     */
    TipContents.get = function(tipName) {
        return this[tipName] || null;
    };
    
    // 전역으로 노출
    window.PTGTips.Contents = TipContents;
    
})();

