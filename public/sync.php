<?php
require_once __DIR__ . '/../config/oracle.php';

class SyncManager {
    private $oracle;
    private $sqlite;

    public function __construct() {
        $this->oracle = (new OracleDB())->getConnection();
        $this->sqlite = new PDO('sqlite:../db/database.db');
        $this->sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function executeProcedure($sql, $bindings) {
        $stmt = oci_parse($this->oracle, $sql);
        foreach ($bindings as $key => $val) {
            oci_bind_by_name($stmt, $key, $bindings[$key]);
        }
        oci_execute($stmt);
    }

    public function syncUsers() {
        $rows = $this->sqlite->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $this->executeProcedure(
                "BEGIN sp_sync_user(:id,:username,:email,:password,TO_DATE(:created_at,'YYYY-MM-DD')); END;",
                [
                    ':id'=>$r['id'],
                    ':username'=>$r['username'],
                    ':email'=>$r['email'],
                    ':password'=>$r['password'],
                    ':created_at'=>date('Y-m-d', strtotime($r['created_at']))
                ]
            );
        }
    }

    public function syncBudgets() {
        $rows = $this->sqlite->query("SELECT * FROM budgets")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $this->executeProcedure(
                "BEGIN sp_sync_budget(:id,:name,:category,:budget_amount,TO_DATE(:created_date,'YYYY-MM-DD'),:user_id); END;",
                [
                    ':id'=>$r['id'],
                    ':name'=>$r['name'],
                    ':category'=>$r['category'],
                    ':budget_amount'=>$r['budget_amount'],
                    ':created_date'=>date('Y-m-d', strtotime($r['created_date'])),
                    ':user_id'=>$r['user_id']
                ]
            );
        }
    }

    public function syncExpenses() {
        $rows = $this->sqlite->query("SELECT * FROM expenses")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $this->executeProcedure(
                "BEGIN sp_sync_expense(:id,:budget_id,:amount,:description,TO_DATE(:expense_date,'YYYY-MM-DD')); END;",
                [
                    ':id'=>$r['id'],
                    ':budget_id'=>$r['budget_id'],
                    ':amount'=>$r['amount'],
                    ':description'=>$r['description'],
                    ':expense_date'=>date('Y-m-d', strtotime($r['date']))
                ]
            );
        }
    }

    public function syncSavingsGoals() {
        $rows = $this->sqlite->query("SELECT * FROM savings_goals")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $this->executeProcedure(
                "BEGIN sp_sync_savings_goal(:id,:user_id,:name,:target_amount,:current_amount,TO_DATE(:deadline,'YYYY-MM-DD'),:category,:description,TO_DATE(:created_date,'YYYY-MM-DD')); END;",
                [
                    ':id'=>$r['id'],
                    ':user_id'=>$r['user_id'],
                    ':name'=>$r['name'],
                    ':target_amount'=>$r['target_amount'],
                    ':current_amount'=>$r['current_amount'],
                    ':deadline'=>date('Y-m-d', strtotime($r['deadline'])),
                    ':category'=>$r['category'],
                    ':description'=>$r['description'],
                    ':created_date'=>date('Y-m-d', strtotime($r['created_date']))
                ]
            );
        }
    }

    public function syncSavingsTransactions() {
        $rows = $this->sqlite->query("SELECT * FROM savings_transactions")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $this->executeProcedure(
                "BEGIN sp_sync_savings_transaction(:id,:goal_id,:amount,:transaction_type,:description,TO_DATE(:transaction_date,'YYYY-MM-DD')); END;",
                [
                    ':id'=>$r['id'],
                    ':goal_id'=>$r['goal_id'],
                    ':amount'=>$r['amount'],
                    ':transaction_type'=>$r['transaction_type'],
                    ':description'=>$r['description'],
                    ':transaction_date'=>date('Y-m-d', strtotime($r['date']))
                ]
            );
        }
    }

    public function syncUserPreferences() {
        $rows = $this->sqlite->query("SELECT * FROM user_preferences")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $this->executeProcedure(
                "BEGIN sp_sync_user_preferences(:id,:user_id,:currency,:date_format,:notifications_enabled,:budget_alerts,:savings_reminders,:theme,:language); END;",
                [
                    ':id'=>$r['id'],
                    ':user_id'=>$r['user_id'],
                    ':currency'=>$r['currency'],
                    ':date_format'=>$r['date_format'],
                    ':notifications_enabled'=>$r['notifications_enabled'],
                    ':budget_alerts'=>$r['budget_alerts'],
                    ':savings_reminders'=>$r['savings_reminders'],
                    ':theme'=>$r['theme'],
                    ':language'=>$r['language']
                ]
            );
        }
    }

    public function syncAll() {
        $this->syncUsers();
        $this->syncBudgets();
        $this->syncExpenses();
        $this->syncSavingsGoals();
        $this->syncSavingsTransactions();
        $this->syncUserPreferences();
        echo "Sync completed successfully!";
    }
}

// Run the sync
$sync = new SyncManager();
$sync->syncAll();
?>
