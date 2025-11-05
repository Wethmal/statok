[<?php
require_once "../db/database.php"; // SQLite
require_once "oracledb.php";       // Oracle

// SQLite connection
$db = new Database();
$db_sqlite = $db->getConnection();

// --- USERS ---
$users = $db_sqlite->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    $stmt = oci_parse($conn_oracle, "BEGIN sync_user(:id, :username, :email, :password, :created_at); END;");
    oci_bind_by_name($stmt, ":id", $u['id']);
    oci_bind_by_name($stmt, ":username", $u['username']);
    oci_bind_by_name($stmt, ":email", $u['email']);
    oci_bind_by_name($stmt, ":password", $u['password']);
    oci_bind_by_name($stmt, ":created_at", $u['created_at']);
    oci_execute($stmt);
}

// --- BUDGETS ---
$budgets = $db_sqlite->query("SELECT * FROM budgets")->fetchAll(PDO::FETCH_ASSOC);
foreach ($budgets as $b) {
    $stmt = oci_parse($conn_oracle, "BEGIN sync_budget(:id, :user_id, :name, :category, :budget_amount, :created_date); END;");
    oci_bind_by_name($stmt, ":id", $b['id']);
    oci_bind_by_name($stmt, ":user_id", $b['user_id']);
    oci_bind_by_name($stmt, ":name", $b['name']);
    oci_bind_by_name($stmt, ":category", $b['category']);
    oci_bind_by_name($stmt, ":budget_amount", $b['budget_amount']);
    oci_bind_by_name($stmt, ":created_date", $b['created_date']);
    oci_execute($stmt);
}

// --- EXPENSES ---
$expenses = $db_sqlite->query("SELECT * FROM expenses")->fetchAll(PDO::FETCH_ASSOC);
foreach ($expenses as $e) {
    $stmt = oci_parse($conn_oracle, "BEGIN sync_expense(:id, :budget_id, :amount, :description, :Edate); END;");
    oci_bind_by_name($stmt, ":id", $e['id']);
    oci_bind_by_name($stmt, ":budget_id", $e['budget_id']);
    oci_bind_by_name($stmt, ":amount", $e['amount']);
    oci_bind_by_name($stmt, ":description", $e['description']);
    oci_bind_by_name($stmt, ":Edate", $e['Edate']);
    oci_execute($stmt);
}

// --- SAVINGS GOALS ---
$goals = $db_sqlite->query("SELECT * FROM savings_goals")->fetchAll(PDO::FETCH_ASSOC);
foreach ($goals as $g) {
    $stmt = oci_parse($conn_oracle, "BEGIN sync_savings_goal(:id, :user_id, :name, :target_amount, :current_amount, :deadline, :category, :description, :created_date); END;");
    oci_bind_by_name($stmt, ":id", $g['id']);
    oci_bind_by_name($stmt, ":user_id", $g['user_id']);
    oci_bind_by_name($stmt, ":name", $g['name']);
    oci_bind_by_name($stmt, ":target_amount", $g['target_amount']);
    oci_bind_by_name($stmt, ":current_amount", $g['current_amount']);
    oci_bind_by_name($stmt, ":deadline", $g['deadline']);
    oci_bind_by_name($stmt, ":category", $g['category']);
    oci_bind_by_name($stmt, ":description", $g['description']);
    oci_bind_by_name($stmt, ":created_date", $g['created_date']);
    oci_execute($stmt);
}

// --- SAVINGS TRANSACTIONS ---
$transactions = $db_sqlite->query("SELECT * FROM savings_transactions")->fetchAll(PDO::FETCH_ASSOC);
foreach ($transactions as $t) {
    $stmt = oci_parse($conn_oracle, "BEGIN sync_savings_transaction(:id, :goal_id, :amount, :transaction_type, :description, :Sdate); END;");
    oci_bind_by_name($stmt, ":id", $t['id']);
    oci_bind_by_name($stmt, ":goal_id", $t['goal_id']);
    oci_bind_by_name($stmt, ":amount", $t['amount']);
    oci_bind_by_name($stmt, ":transaction_type", $t['transaction_type']);
    oci_bind_by_name($stmt, ":description", $t['description']);
    oci_bind_by_name($stmt, ":Sdate", $t['Sdate']);
    oci_execute($stmt);
}

echo "✅ All tables synced successfully!";
?>
]