<?php
class OracleDB {
    private $conn;

    public function __construct() {
        $username = "system";      // your Oracle username
        $password = "admin123";    // your Oracle password
        $connection_string = "localhost/XEPDB1"; // default for Oracle XE

        
    }

    public function getConnection() {
        return $this->conn;
    }

    public function __destruct() {
        if ($this->conn) {
            oci_close($this->conn);
        }
    }
}
?>
