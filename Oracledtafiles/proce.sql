CREATE OR REPLACE PROCEDURE search_user_by_id IS
    v_id NUMBER;
    v_username VARCHAR2(100);
    v_email VARCHAR2(150);
    v_password VARCHAR2(255);
    v_created_at VARCHAR2(50);
BEGIN
    -- Get user input
    v_id := &Enter_User_ID;

    -- Fetch data
    SELECT username, email, password, created_at
    INTO v_username, v_email, v_password, v_created_at
    FROM users
    WHERE id = v_id;

    -- Display result
    DBMS_OUTPUT.PUT_LINE('Username: ' || v_username);
    DBMS_OUTPUT.PUT_LINE('Email: ' || v_email);
    DBMS_OUTPUT.PUT_LINE('Password: ' || v_password);
    DBMS_OUTPUT.PUT_LINE('Created At: ' || v_created_at);

EXCEPTION
    WHEN NO_DATA_FOUND THEN
        DBMS_OUTPUT.PUT_LINE('No user found with ID: ' || v_id);
END;
/
