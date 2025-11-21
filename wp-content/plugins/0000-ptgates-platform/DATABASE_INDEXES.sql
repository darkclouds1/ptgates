-- PTGates 성능 최적화를 위한 데이터베이스 인덱스
-- 동접 100명+ 대비 성능 개선

-- 1. ptgates_categories 테이블 인덱스
-- 집계 모드 쿼리 최적화 (WHERE subject IN (...) AND exam_session >= 1000)
-- 주로 사용되는 쿼리 패턴: subject 필터링 + exam_session 필터링
CREATE INDEX IF NOT EXISTS `idx_subject_session` 
ON `ptgates_categories` (`subject`, `exam_session`);

-- 2. ptgates_categories 테이블 인덱스
-- JOIN 쿼리 최적화 (ptgates_questions와 JOIN 시 is_active 필터링)
-- 주로 사용되는 쿼리 패턴: q.is_active = 1 AND c.subject = ? AND c.exam_session >= 1000
CREATE INDEX IF NOT EXISTS `idx_subject_session_active` 
ON `ptgates_categories` (`subject`, `exam_session`, `question_id`);

-- 3. ptgates_categories 테이블 인덱스
-- 여러 과목 조회 최적화 (WHERE subject IN (...))
-- 집계 모드에서 사용: subjects 배열로 여러 과목 조회
CREATE INDEX IF NOT EXISTS `idx_subject_exam_session` 
ON `ptgates_categories` (`subject`, `exam_session`, `exam_year`);

-- 주의사항:
-- - 기존 인덱스(idx_subject, idx_year_subject 등)가 있으므로 중복되지 않도록 확인
-- - 인덱스가 많으면 INSERT/UPDATE 성능이 저하될 수 있으니, 실제 쿼리 패턴 분석 후 선택적으로 적용
-- - MySQL 5.7 이상에서만 IF NOT EXISTS 지원 (5.6 이하는 수동으로 확인 필요)

-- 인덱스 적용 확인 쿼리:
-- SHOW INDEX FROM ptgates_categories;

-- 인덱스 성능 확인 쿼리 예시:
-- EXPLAIN SELECT q.*, c.* 
-- FROM ptgates_questions q
-- INNER JOIN ptgates_categories c ON q.question_id = c.question_id
-- WHERE q.is_active = 1 
-- AND c.subject IN ('해부생리학', '운동학') 
-- AND c.exam_session >= 1000
-- ORDER BY q.question_id DESC
-- LIMIT 10;

