<?php
try {
    $db_sqlite = new PDO("sqlite:" . __DIR__ . "/../users.db");
    $db_sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("SQLite connection failed: " . $e->getMessage());
}
?>
