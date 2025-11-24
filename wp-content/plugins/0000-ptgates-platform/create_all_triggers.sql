-- ptgates_user_states 테이블용 모든 트리거 생성
-- INSERT와 UPDATE 모두 처리

DELIMITER $$

-- 기존 트리거 삭제 (있을 경우)
DROP TRIGGER IF EXISTS `ptgates_update_last_study_date`$$
DROP TRIGGER IF EXISTS `ptgates_insert_last_study_date`$$
DROP TRIGGER IF EXISTS `ptgates_update_last_quiz_date`$$
DROP TRIGGER IF EXISTS `ptgates_insert_last_quiz_date`$$

-- study_count 변경 시 last_study_date 자동 설정 (UPDATE)
CREATE TRIGGER `ptgates_update_last_study_date`
BEFORE UPDATE ON `ptgates_user_states`
FOR EACH ROW
BEGIN
    IF NEW.study_count != OLD.study_count THEN
        SET NEW.updated_at = NOW();
        SET NEW.last_study_date = NEW.updated_at;
    END IF;
END$$

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

-- quiz_count 변경 시 last_quiz_date 자동 설정 (UPDATE)
CREATE TRIGGER `ptgates_update_last_quiz_date`
BEFORE UPDATE ON `ptgates_user_states`
FOR EACH ROW
BEGIN
    IF NEW.quiz_count != OLD.quiz_count THEN
        SET NEW.updated_at = NOW();
        SET NEW.last_quiz_date = NEW.updated_at;
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

DELIMITER ;

