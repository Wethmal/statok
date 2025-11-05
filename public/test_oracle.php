<?php
/**
 * test_oracle.php - Test Oracle Connection
 * Run this file in your browser to check Oracle connectivity
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Oracle Connection Test</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;} .success{color:green;} .error{color:red;} pre{background:#f5f5f5;padding:10px;}</style>";

// Test 1: Check if OCI8 extension is loaded
echo "<h2>1. Checking OCI8 Extension</h2>";
if (extension_loaded('oci8')) {
    echo "<p class='success'>✓ OCI8 extension is loaded</p>";
} else {
    echo "<p class='error'>✗ OCI8 extension is NOT loaded</p>";
    echo "<p>To install OCI8:</p>";
    echo "<pre>sudo apt-get install php-oci8\nsudo systemctl restart apache2</pre>";
    exit;
}

// Test 2: Check if OracleDB.php exists
echo "<h2>2. Checking OracleDB.php File</h2>";
if (file_exists(__DIR__ . '/OracleDB.php')) {
    echo "<p class='success'>✓ OracleDB.php file found</p>";
} else {
    echo "<p class='error'>✗ OracleDB.php file NOT found</p>";
    echo "<p>Expected location: " . __DIR__ . '/OracleDB.php</p>';
    exit;
}

// Test 3: Try to connect to Oracle
echo "<h2>3. Testing Oracle Connection</h2>";
require_once 'OracleDB.php';

try {
    $oracleDB = new OracleDB();
    $conn = $oracleDB->getConnection();
    
    if ($conn) {
        echo "<p class='success'>✓ Successfully connected to Oracle Database!</p>";
        
        // Test 4: Try a simple query
        echo "<h2>4. Testing Query Execution</h2>";
        $sql = "SELECT SYSDATE FROM DUAL";
        $stmt = oci_parse($conn, $sql);
        
        if (oci_execute($stmt)) {
            $row = oci_fetch_array($stmt, OCI_ASSOC);
            echo "<p class='success'>✓ Query executed successfully</p>";
            echo "<p>Current Oracle Date/Time: " . $row['SYSDATE'] . "</p>";
        } else {
            $e = oci_error($stmt);
            echo "<p class='error'>✗ Query failed: " . $e['message'] . "</p>";
        }
        
        // Test 5: Check existing tables
        echo "<h2>5. Checking Existing Tables</h2>";
        $sql = "SELECT table_name FROM user_tables ORDER BY table_name";
        $stmt = oci_parse($conn, $sql);
        
        if (oci_execute($stmt)) {
            echo "<p class='success'>✓ Found the following tables:</p>";
            echo "<ul>";
            $hasTable = false;
            while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
                echo "<li>" . $row['TABLE_NAME'] . "</li>";
                $hasTable = true;
            }
            if (!$hasTable) {
                echo "<li><em>No tables found (this is OK for first run)</em></li>";
            }
            echo "</ul>";
        }
        
        echo "<h2>✅ All Tests Passed!</h2>";
        echo "<p>Your Oracle connection is working properly. You can now use the sync feature.</p>";
        
    } else {
        echo "<p class='error'>✗ Failed to connect to Oracle Database</p>";
        echo "<p>Please check your Oracle credentials in OracleDB.php:</p>";
        echo "<pre>";
        echo "Username: system\n";
        echo "Password: your_password\n";
        echo "Connection String: localhost/XEPDB1\n";
        echo "</pre>";
        echo "<p>Make sure Oracle is running:</p>";
        echo "<pre>sqlplus system/admin123@localhost/XEPDB1</pre>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Exception: " . $e->getMessage() . "</p>";
}
?>

<hr>
<h2>Quick Fixes</h2>
<h3>If OCI8 extension is missing:</h3>
<pre>
# Ubuntu/Debian
sudo apt-get install php-dev php-pear build-essential libaio1
sudo pecl install oci8
echo "extension=oci8.so" | sudo tee /etc/php/8.1/mods-available/oci8.ini
sudo phpenmod oci8
sudo systemctl restart apache2
</pre>

<h3>If Oracle is not running:</h3>
<pre>
# Check Oracle status
ps aux | grep oracle

# Start Oracle (if using XE)
sudo systemctl start oracle-xe
</pre>

<h3>If connection fails:</h3>
<pre>
# Test connection manually
sqlplus system/admin123@localhost/XEPDB1

# Check TNS
tnsping XEPDB1
</pre>