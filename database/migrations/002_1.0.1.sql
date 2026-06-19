-- Migration: Renommage de la table kronoconnect_files en files
-- Permet la transition propre pour les installations existantes

DROP PROCEDURE IF EXISTS RenameTableIfExists;

DELIMITER //

CREATE PROCEDURE RenameTableIfExists()
BEGIN
    DECLARE old_exists INT DEFAULT 0;
    DECLARE new_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO old_exists FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'kronoconnect_files';
    IF old_exists = 0 THEN
        SELECT COUNT(*) INTO old_exists FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{PREFIX}kronoconnect_files';
        IF old_exists > 0 THEN
            SET old_exists = 2;
        END IF;
    ELSE
        SET old_exists = 1;
    END IF;

    SELECT COUNT(*) INTO new_exists FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{PREFIX}files';

    IF old_exists > 0 AND new_exists > 0 THEN
        -- Since the old table exists and has the data, we drop the empty new table that was just created.
        DROP TABLE `{PREFIX}files`;
        SET new_exists = 0;
    END IF;

    IF old_exists = 1 AND new_exists = 0 THEN
        RENAME TABLE `kronoconnect_files` TO `{PREFIX}files`;
    ELSEIF old_exists = 2 AND new_exists = 0 THEN
        RENAME TABLE `{PREFIX}kronoconnect_files` TO `{PREFIX}files`;
    END IF;
END //

DELIMITER ;

CALL RenameTableIfExists();
DROP PROCEDURE IF EXISTS RenameTableIfExists;
