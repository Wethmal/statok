<?php
$username = "system";             // your Oracle username
$password = "admin123";           // your Oracle password
$connection_string = "localhost/XEPDB1"; // your Oracle DB

$conn_oracle = oci_connect($username, $password, $connection_string);

if (!$conn_oracle) {
    $e = oci_error();
    die("Oracle connection failed: " . $e['message']);
}
?>
