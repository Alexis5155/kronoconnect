-- Migration: Renommage de la table kronoconnect_files en files
-- Permet la transition propre pour les installations existantes

DROP PROCEDURE IF EXISTS RenameTableIfExists;

DELIMITER //

CREATE PROCEDURE RenameTableIfExists()
BEGIN
    IF EXISTS (
        SELECT 1 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
          AND table_name = '{PREFIX}kronoconnect_files'
    ) THEN
        RENAME TABLE `{PREFIX}kronoconnect_files` TO `{PREFIX}files`;
    END IF;
END //

DELIMITER ;

CALL RenameTableIfExists();
DROP PROCEDURE IF EXISTS RenameTableIfExists;
