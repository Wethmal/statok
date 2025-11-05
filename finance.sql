CREATE OR REPLACE PROCEDURE sync_user(
    p_id IN NUMBER,
    p_username IN VARCHAR2,
    p_email IN VARCHAR2,
    p_password IN VARCHAR2,
    p_created_at IN DATE
) AS
BEGIN
    MERGE INTO users u
    USING (SELECT p_id AS id FROM dual) src
    ON (u.id = src.id)
    WHEN MATCHED THEN
        UPDATE SET username = p_username,
                   email = p_email,
                   password = p_password,
                   created_at = p_created_at
    WHEN NOT MATCHED THEN
        INSERT (id, username, email, password, created_at)
        VALUES (p_id, p_username, p_email, p_password, p_created_at);
END;
/
