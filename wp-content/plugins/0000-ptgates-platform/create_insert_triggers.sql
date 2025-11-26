-- ptgates_user_states 테이블용 INSERT 트리거 생성
-- study_count와 quiz_count가 설정될 때 last_study_date와 last_quiz_date를 자동으로 설정

DELIMITER $$

-- 기존 트리거 삭제 (있을 경우)
DROP TRIGGER IF EXISTS `ptgates_insert_last_study_date`$$
DROP TRIGGER IF EXISTS `ptgates_insert_last_quiz_date`$$
DROP TRIGGER IF EXISTS `ptgates_insert_last_review_date`$$

-- study_count가 0보다 클 때 last_study_date 자동 설정 (INSERT)
CREATE TRIGGER `ptgates_insert_last_study_date`
BEFORE INSERT ON `ptgates_user_states`
FOR EACH ROW
BEGIN
    IF NEW.study_count > 0 THEN
        SET NEW.updated_at = NOW();
        SET NEW.last_study_date = NEW.updated_at;
    END IF;
END$$

-- quiz_count가 0보다 클 때 last_quiz_date 자동 설정 (INSERT)
CREATE TRIGGER `ptgates_insert_last_quiz_date`
BEFORE INSERT ON `ptgates_user_states`
FOR EACH ROW
BEGIN
    IF NEW.quiz_count > 0 THEN
        SET NEW.updated_at = NOW();
        SET NEW.last_quiz_date = NEW.updated_at;
    END IF;
END$$

-- review_count가 0보다 클 때 last_review_date 자동 설정 (INSERT)
CREATE TRIGGER `ptgates_insert_last_review_date`
BEFORE INSERT ON `ptgates_user_states`
FOR EACH ROW
BEGIN
    IF NEW.review_count > 0 THEN
        SET NEW.updated_at = NOW();
        SET NEW.last_review_date = NEW.updated_at;
    END IF;
END$$

DELIMITER ;

