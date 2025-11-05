<?php
/**
 * check_structure.php - Check your SQLite database structure
 * This will show us the actual column names in your tables
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>SQLite Database Structure Check</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;} table{border-collapse:collapse;margin:20px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#667eea;color:white;}</style>";

try {
    $dbPath = __DIR__ . '/../db/database.db';
    echo "<p><strong>Database Location:</strong> $dbPath</p>";
    
    if (!file_exists($dbPath)) {
        die("<p style='color:red;'>Database not found at: $dbPath</p>");
    }
    
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color:green;'>✓ Successfully connected to SQLite database</p>";
    
    // Get all tables
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Tables Found: " . count($tables) . "</h2>";
    
    foreach ($tables as $table) {
        echo "<h3>Table: $table</h3>";
        
        // Get column info
        $stmt = $db->query("PRAGMA table_info($table)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>Column Name</th><th>Type</th><th>Not Null</th><th>Default</th><th>Primary Key</th></tr>";
        
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td><strong>" . $col['name'] . "</strong></td>";
            echo "<td>" . $col['type'] . "</td>";
            echo "<td>" . ($col['notnull'] ? 'YES' : 'NO') . "</td>";
            echo "<td>" . ($col['dflt_value'] ?? 'NULL') . "</td>";
            echo "<td>" . ($col['pk'] ? 'YES' : 'NO') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Show sample data
        $stmt = $db->query("SELECT * FROM $table LIMIT 3");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            echo "<p><strong>Sample Data (first 3 rows):</strong></p>";
            echo "<table>";
            echo "<tr>";
            foreach (array_keys($rows[0]) as $col) {
                echo "<th>$col</th>";
            }
            echo "</tr>";
            
            foreach ($rows as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars(substr($value ?? '', 0, 50)) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p><em>No data in this table</em></p>";
        }
        
        echo "<hr>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<h2>What to do with this information:</h2>
<p>Look at the <strong>users</strong> table columns. The sync script needs to know the exact column names.</p>
<p>Common variations:</p>
<ul>
    <li>username vs user_name vs name</li>
    <li>email vs user_email</li>
    <li>password vs user_password vs pass</li>
    <li>created_at vs created_date vs registration_date</li>
</ul>
<p>Once you see your actual column names, I'll update the sync script to match them!</p>