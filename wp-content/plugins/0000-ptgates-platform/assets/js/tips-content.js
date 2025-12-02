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
                <div style="text-align: left; line-height: 1.6; color: #374151;">
                    <!-- 1. 출제 경향 -->
                    <section style="margin-bottom: 20px;">
                        <h4 style="margin: 0 0 8px 0; font-size: 15px; color: #111;">📊 출제 경향 (ptGates 적용)</h4>
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; font-size: 13px;">
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <li style="margin-bottom: 6px; padding-left: 14px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>흐름:</strong> <span style="color:#e11d48;">기초</span> → <span style="color:#2563eb;">응용</span> → <span style="color:#059669;">임상</span> (원리에서 질환별 적용 순)</li>
                                <li style="margin-bottom: 0; padding-left: 14px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>배치:</strong> 과목 내에서도 <strong>개론</strong>이 앞쪽, <strong>세부 사례</strong>가 뒤쪽에 배치됨.</li>
                            </ul>
                        </div>
                    </section>

                    <!-- 2. 모의고사 구성 -->
                    <section style="margin-bottom: 20px;">
                        <h4 style="margin: 0 0 8px 0; font-size: 15px; color: #111;">🎯 교시별 구성</h4>
                        <div style="display: flex; gap: 10px; font-size: 13px;">
                            <div style="flex: 1; background: #fff; padding: 10px; border-radius: 6px; border: 1px solid #e5e7eb;">
                                <strong style="display:block; margin-bottom:6px; color:#333; border-bottom:1px solid #eee; padding-bottom:4px;">1교시 (105문항)</strong>
                                <ul style="margin:0; padding-left:14px; color:#555; list-style:none;">
                                    <li style="margin-bottom:4px; position:relative;"><span style="position:absolute; left:-12px; color:#9ca3af;">•</span>기초(60): 해부/운동/인자/공중</li>
                                    <li style="position:relative;"><span style="position:absolute; left:-12px; color:#9ca3af;">•</span>진단(45): 근골/신경/심폐 등</li>
                                </ul>
                            </div>
                            <div style="flex: 1; background: #fff; padding: 10px; border-radius: 6px; border: 1px solid #e5e7eb;">
                                <strong style="display:block; margin-bottom:6px; color:#333; border-bottom:1px solid #eee; padding-bottom:4px;">2교시 (85문항)</strong>
                                <ul style="margin:0; padding-left:14px; color:#555; list-style:none;">
                                    <li style="margin-bottom:4px; position:relative;"><span style="position:absolute; left:-12px; color:#9ca3af;">•</span>중재(65): 근골/신경/피부 등</li>
                                    <li style="position:relative;"><span style="position:absolute; left:-12px; color:#9ca3af;">•</span>법규(20): 의료/노인/장애인 등</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- 3. 주요 기능 -->
                    <section style="margin-bottom: 16px;">
                        <h4 style="margin: 0 0 8px 0; font-size: 15px; color: #111;">🔍 주요 기능</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 13px;">
                            <div style="background: #f0f9ff; padding: 8px; border-radius: 4px; color: #0c4a6e;">
                                <strong>🎲 기본 퀴즈</strong><br>필터 없이 5문제 랜덤
                            </div>
                            <div style="background: #f0f9ff; padding: 8px; border-radius: 4px; color: #0c4a6e;">
                                <strong>📚 과목 선택</strong><br>특정 교시/과목 집중
                            </div>
                            <div style="background: #f0f9ff; padding: 8px; border-radius: 4px; color: #0c4a6e;">
                                <strong>⏱️ 문항 조절</strong><br>시간에 맞춰 개수 설정
                            </div>
                            <div style="background: #f0f9ff; padding: 8px; border-radius: 4px; color: #0c4a6e;">
                                <strong>🔖 북마크/복습</strong><br>틀린 문제 다시 풀기
                            </div>
                        </div>
                    </section>

                    <!-- 4. 참고사항 -->
                    <section style="font-size: 12px; color: #6b7280; background: #f9fafb; padding: 8px; border-radius: 4px;">
                        <p style="margin: 0 0 4px 0;">※ 기출문제는 자동 제외됩니다. (생성 문항 중심)</p>
                        <p style="margin: 0;">※ 전체 모의고사는 국가시험 문항 비율을 따릅니다.</p>
                    </section>
                </div>
            `,
            maxWidth: 600
        },
        
        /**
         * study-tip: 기출 학습 가이드
         */
        'study-tip': {
            title: '기출 학습 가이드',
            content: `
                <div style="text-align: left; line-height: 1.6; color: #374151;">
                    <!-- 1. 학습 팁 -->
                    <section style="margin-bottom: 20px;">
                        <h4 style="margin: 0 0 8px 0; font-size: 15px; color: #111;">💡 효과적인 학습 팁</h4>
                        <ul style="list-style: none; padding: 0; margin: 0; font-size: 14px;">
                            <li style="margin-bottom: 6px; padding-left: 14px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>암기카드:</strong> 어려운 개념은 즉시 저장해 <strong>간격 반복(SRS)</strong>으로 암기하세요.</li>
                            <li style="margin-bottom: 6px; padding-left: 14px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>취약점 분석:</strong> 대시보드에서 부족한 단원을 파악해 집중 공략하세요.</li>
                            <li style="margin-bottom: 6px; padding-left: 14px; position: relative;"><span style="position: absolute; left: 0; color: #4a90e2;">•</span> <strong>연속 학습:</strong> <strong>기초→응용</strong> 흐름에 맞춰 세부 영역을 묶어서 학습하세요.</li>
                        </ul>
                    </section>

                    <!-- 2. 스마트 랜덤 -->
                    <section style="margin-bottom: 20px;">
                        <h4 style="margin: 0 0 8px 0; font-size: 15px; color: #111;">🧠 스마트 랜덤 추천 (로그인)</h4>
                        <div style="background: #f0f9ff; padding: 12px; border-radius: 6px; border-left: 3px solid #0ea5e9; font-size: 13px;">
                            <p style="margin: 0 0 8px 0; color: #0c4a6e;">'랜덤 섞기' 시 다음 우선순위로 문제가 노출됩니다:</p>
                            <ol style="margin: 0; padding-left: 20px; color: #075985;">
                                <li style="margin-bottom: 4px;"><strong>1순위 (최근 오답):</strong> 틀린 문제 집중 복습</li>
                                <li style="margin-bottom: 4px;"><strong>2순위 (미학습):</strong> 새로운 문제 도전</li>
                                <li style="margin-bottom: 0;"><strong>3순위 (최근 정답):</strong> 아는 문제 가볍게 확인</li>
                            </ol>
                        </div>
                    </section>

                    <!-- 3. 출제 경향 & 구조 -->
                    <section>
                        <h4 style="margin: 0 0 8px 0; font-size: 15px; color: #111;">📌 출제 경향 및 구조</h4>
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; font-size: 13px;">
                            <p style="margin: 0 0 8px 0;"><strong>출제 흐름:</strong> <span style="color:#e11d48;">기초</span> → <span style="color:#2563eb;">응용</span> → <span style="color:#059669;">임상</span> (개론에서 세부 사례로)</p>
                            
                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                <div style="flex: 1; background: #fff; padding: 8px; border-radius: 4px; border: 1px solid #e5e7eb;">
                                    <strong style="display:block; margin-bottom:4px; color:#333;">1교시 (기초/진단)</strong>
                                    <ul style="margin:0; padding-left:12px; color:#555;">
                                        <li>해부/운동/인자/공중</li>
                                        <li>진단평가(근골/신경 등)</li>
                                    </ul>
                                </div>
                                <div style="flex: 1; background: #fff; padding: 8px; border-radius: 4px; border: 1px solid #e5e7eb;">
                                    <strong style="display:block; margin-bottom:4px; color:#333;">2교시 (중재/법규)</strong>
                                    <ul style="margin:0; padding-left:12px; color:#555;">
                                        <li>중재(근골/신경 등)</li>
                                        <li>의료관계법규</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            `,
            maxWidth: 600
        },
        
        /**
         * timer-tip: 시간관리 Tip
         */
        'timer-tip': {
            title: '국가시험 시간관리 가이드',
            content: `
                <div style="text-align: left; line-height: 1.6; color: #374151;">
                    <!-- 1. 개요 -->
                    <section style="margin-bottom: 20px;">
                        <h4 style="margin: 0 0 8px 0; font-size: 15px; color: #111;">⏱️ 시험 시간 개요</h4>
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; font-size: 13px;">
                            <p style="margin: 0 0 4px 0;"><strong>전체:</strong> 260문항 / 250분 (평균 57.7초/문제)</p>
                            <p style="margin: 0; color: #e11d48;">※ 교시별로 문항 수와 시간이 다르므로 전략적 배분이 필수입니다.</p>
                        </div>
                    </section>

                    <!-- 2. 교시별 상세 -->
                    <section style="margin-bottom: 20px;">
                        <h4 style="margin: 0 0 8px 0; font-size: 15px; color: #111;">📊 교시별 평균 소요 시간</h4>
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px; border: 1px solid #e5e7eb;">
                            <thead style="background: #f3f4f6;">
                                <tr>
                                    <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">교시</th>
                                    <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: left;">과목 (문항수)</th>
                                    <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">시간</th>
                                    <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">평균</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">1교시</td>
                                    <td style="padding: 8px; border: 1px solid #e5e7eb;">기초+진단 (105)</td>
                                    <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">90분</td>
                                    <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: center; color:#e11d48; font-weight:bold;">51초</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">2교시</td>
                                    <td style="padding: 8px; border: 1px solid #e5e7eb;">중재+법규 (85)</td>
                                    <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">75분</td>
                                    <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: center; color:#e11d48; font-weight:bold;">53초</td>
                                </tr>
                                <tr style="background: #f9fafb;">
                                    <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">3교시</td>
                                    <td style="padding: 8px; border: 1px solid #e5e7eb;">실기 (70)</td>
                                    <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">85분</td>
                                    <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: center; color:#059669;">73초</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <!-- 3. 핵심 전략 -->
                    <section>
                        <h4 style="margin: 0 0 8px 0; font-size: 15px; color: #111;">💡 핵심 전략</h4>
                        <div style="background: #f0f9ff; padding: 12px; border-radius: 6px; border-left: 3px solid #0ea5e9; font-size: 13px;">
                            <ul style="margin: 0; padding-left: 14px; color: #0c4a6e;">
                                <li style="margin-bottom: 6px;"><strong>필기 (1,2교시):</strong> <span style="color:#e11d48;">속도전</span>입니다. 1분 안에 푸는 연습이 필요합니다.</li>
                                <li style="margin-bottom: 6px;"><strong>실기 (3교시):</strong> 상대적으로 여유가 있습니다. 지문 분석에 집중하세요.</li>
                                <li style="margin-bottom: 0;"><strong>실전 연습:</strong> 실제 시험 시간과 동일하게 타이머를 설정하고 연습하세요.</li>
                            </ul>
                        </div>
                    </section>
                </div>
            `,
            maxWidth: 600
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

