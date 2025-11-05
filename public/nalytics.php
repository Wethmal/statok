<?php
// analytics.php - Oracle Financial Analytics (Advanced UI)
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// =======================================================
// 🔹 Oracle Connection
// =======================================================
$username_db = "system";
$password_db = "admin123";
$connection_string = "localhost/XEPDB1";

$conn_oracle = oci_connect($username_db, $password_db, $connection_string);
if (!$conn_oracle) {
    $e = oci_error();
    die("Oracle connection failed: " . $e['message']);
}

// Get selected period (default: last 12 months)
$period = $_GET['period'] ?? '12';

// =======================================================
// 🔹 Helper Function — Call PL/SQL Functions returning REF CURSOR
// =======================================================
function fetch_cursor_data($conn, $sql, $user_id) {
    $stmt = oci_parse($conn, $sql);
    $cursor = oci_new_cursor($conn);

    oci_bind_by_name($stmt, ":uid", $user_id);
    oci_bind_by_name($stmt, ":cur", $cursor, -1, OCI_B_CURSOR);

    oci_execute($stmt);
    oci_execute($cursor);

    $data = [];
    while (($row = oci_fetch_assoc($cursor)) != false) {
        $data[] = $row;
    }

    oci_free_statement($stmt);
    oci_free_statement($cursor);
    return $data;
}

// =======================================================
// 🔹 Fetch All Reports from Package
// =======================================================
$monthlyData = fetch_cursor_data($conn_oracle, "BEGIN :cur := PKG_FINANCIAL_ANALYTICS.get_monthly_expenditure(:uid); END;", $user_id);
$adherenceData = fetch_cursor_data($conn_oracle, "BEGIN :cur := PKG_FINANCIAL_ANALYTICS.get_budget_adherence(:uid); END;", $user_id);
$savingsData = fetch_cursor_data($conn_oracle, "BEGIN :cur := PKG_FINANCIAL_ANALYTICS.get_savings_progress(:uid); END;", $user_id);
$categoryData = fetch_cursor_data($conn_oracle, "BEGIN :cur := PKG_FINANCIAL_ANALYTICS.get_category_distribution(:uid); END;", $user_id);
$forecastData = fetch_cursor_data($conn_oracle, "BEGIN :cur := PKG_FINANCIAL_ANALYTICS.get_forecasted_savings(:uid); END;", $user_id);

// =======================================================
// 🔹 Calculate Summaries & Insights
// =======================================================
$totalBudget = array_sum(array_column($adherenceData, 'BUDGET_AMOUNT'));
$totalSpent = array_sum(array_column($adherenceData, 'SPENT'));
$avgMonthlyExpense = count($monthlyData) ? array_sum(array_column($monthlyData, 'TOTAL_SPENT')) / count($monthlyData) : 0;
$totalCategorySpending = array_sum(array_column($categoryData, 'TOTAL_SPENT'));
$overallAdherence = $totalBudget > 0 ? ($totalSpent / $totalBudget) * 100 : 0;

// Calculate monthly growth
$monthlyGrowth = [];
for ($i = 0; $i < count($monthlyData) - 1; $i++) {
    $current = $monthlyData[$i]['TOTAL_SPENT'];
    $previous = $monthlyData[$i + 1]['TOTAL_SPENT'];
    $growth = $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;
    $monthlyGrowth[$monthlyData[$i]['MONTH']] = $growth;
}

// Generate AI-like insights
$insights = [];

if ($overallAdherence > 100) {
    $insights[] = [
        'type' => 'danger',
        'icon' => '⚠',
        'title' => 'Budget Overspending Alert',
        'message' => 'You are spending ' . round($overallAdherence - 100, 1) . '% over your total budget. Consider reducing expenses.'
    ];
} elseif ($overallAdherence > 90) {
    $insights[] = [
        'type' => 'warning',
        'icon' => '⚡',
        'title' => 'Budget Usage High',
        'message' => 'You have used ' . round($overallAdherence, 1) . '% of your budget. Monitor your spending closely.'
    ];
} else {
    $insights[] = [
        'type' => 'success',
        'icon' => '✅',
        'title' => 'Budget On Track',
        'message' => 'Great job! You are at ' . round($overallAdherence, 1) . '% of your budget allocation.'
    ];
}

// Savings insights
$completedGoals = 0;
$totalGoals = count($savingsData);
foreach ($savingsData as $goal) {
    if ($goal['STATUS'] === 'Completed') $completedGoals++;
}

if ($totalGoals > 0) {
    $completionRate = ($completedGoals / $totalGoals) * 100;
    if ($completionRate >= 50) {
        $insights[] = [
            'type' => 'success',
            'icon' => '🎯',
            'title' => 'Savings Champion',
            'message' => "You've completed $completedGoals out of $totalGoals savings goals (" . round($completionRate) . "%)!"
        ];
    }
}

// Top category insight
if (count($categoryData) > 0) {
    $topCategory = $categoryData[0];
    $topPercentage = $totalCategorySpending > 0 ? ($topCategory['TOTAL_SPENT'] / $totalCategorySpending) * 100 : 0;
    $insights[] = [
        'type' => 'info',
        'icon' => '📊',
        'title' => 'Top Spending Category',
        'message' => $topCategory['CATEGORY'] . ' accounts for ' . round($topPercentage, 1) . '% of your spending ($' . number_format($topCategory['TOTAL_SPENT'], 2) . ')'
    ];
}

// Forecast insight
if (count($forecastData) > 0) {
    $projectedSavings = $forecastData[0]['PROJECTED_NET_SAVINGS'];
    if ($projectedSavings > 0) {
        $insights[] = [
            'type' => 'success',
            'icon' => '📈',
            'title' => 'Positive Forecast',
            'message' => 'Based on trends, you could save $' . number_format($projectedSavings, 2) . ' next month!'
        ];
    } else {
        $insights[] = [
            'type' => 'warning',
            'icon' => '📉',
            'title' => 'Forecast Alert',
            'message' => 'Projected expenses may exceed savings by $' . number_format(abs($projectedSavings), 2) . ' next month.'
        ];
    }
}

// Calculate category percentages
foreach ($categoryData as &$cat) {
    $cat['PERCENTAGE'] = $totalCategorySpending > 0 ? ($cat['TOTAL_SPENT'] / $totalCategorySpending) * 100 : 0;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Statok</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', Roboto, sans-serif; 
            background: #f8f9fc; 
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid #e8eaf0;
            padding: 24px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 24px;
            margin-bottom: 32px;
        }
        
        .logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .logo-text {
            font-size: 20px;
            font-weight: 700;
            color: #1a202c;
        }
        
        .nav-menu {
            list-style: none;
            padding: 0 12px;
        }
        
        .nav-item {
            margin-bottom: 4px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #64748b;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 14px;
            font-weight: 500;
        }
        
        .nav-link:hover {
            background: #f8f9fc;
            color: #667eea;
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            color: #667eea;
        }
        
        .nav-icon {
            width: 20px;
            font-size: 18px;
        }
        
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 24px 32px;
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a202c;
        }
        
        .top-bar-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .btn-print {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .period-selector {
            display: flex;
            gap: 8px;
            background: white;
            padding: 4px;
            border-radius: 8px;
            border: 1px solid #e8eaf0;
        }
        
        .period-btn {
            padding: 8px 16px;
            border: none;
            background: transparent;
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .period-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .insight-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .insight-card.success { border-color: #10b981; }
        .insight-card.warning { border-color: #f59e0b; }
        .insight-card.danger { border-color: #ef4444; }
        .insight-card.info { border-color: #3b82f6; }
        
        .insight-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        
        .insight-icon {
            font-size: 24px;
        }
        
        .insight-title {
            font-size: 15px;
            font-weight: 700;
            color: #1a202c;
        }
        
        .insight-message {
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
        }
        
        .report-section {
            background: white;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 24px;
            border: 1px solid #e8eaf0;
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .report-title {
            font-size: 20px;
            font-weight: 700;
            color: #1a202c;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .report-icon {
            font-size: 24px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            padding: 16px;
            border-radius: 12px;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .stat-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #1a202c;
        }
        
        .stat-change {
            font-size: 12px;
            margin-top: 4px;
            font-weight: 600;
        }
        
        .stat-change.positive { color: #10b981; }
        .stat-change.negative { color: #ef4444; }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid #e8eaf0;
            font-size: 13px;
        }
        
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        
        tr:hover {
            background: #f8f9fc;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-healthy { background: #d1fae5; color: #065f46; }
        .status-warning { background: #fef3c7; color: #92400e; }
        .status-critical { background: #fee2e2; color: #991b1b; }
        .status-over { background: #fce7f3; color: #9f1239; }
        .status-completed { background: #ddd6fe; color: #5b21b6; }
        .status-ontrack { background: #dbeafe; color: #1e40af; }
        .status-moderate { background: #fef3c7; color: #92400e; }
        .status-slowprogress { background: #fee2e2; color: #991b1b; }
        .status-urgent { background: #fed7aa; color: #9a3412; }
        .status-overdue { background: #fecaca; color: #991b1b; }
        
        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: #e8eaf0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 4px;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        .progress-bar-fill.over {
            background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
        }
        
        .progress-bar-fill.complete {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }
        
        .chart-container {
            margin-top: 20px;
            padding: 16px;
            background: #f8f9fc;
            border-radius: 12px;
        }
        
        .category-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .category-label {
            min-width: 140px;
            font-size: 13px;
            font-weight: 600;
            color: #1a202c;
        }
        
        .category-progress {
            flex: 1;
            height: 32px;
            background: #e8eaf0;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }
        
        .category-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 12px;
            color: white;
            font-size: 12px;
            font-weight: 700;
            transition: width 0.5s ease;
        }
        
        .category-amount {
            min-width: 100px;
            text-align: right;
            font-size: 14px;
            font-weight: 700;
            color: #1a202c;
        }
        
        .forecast-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        
        .forecast-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .forecast-month {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .forecast-amount {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .forecast-label {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: 1fr; }
            .forecast-grid { grid-template-columns: 1fr; }
        }
        
        @media print {
            .sidebar, .btn-print, .period-selector { display: none !important; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo">
            <div class="logo-icon">💰</div>
            <div class="logo-text">Statok</div>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <span class="nav-icon">📊</span>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="budgets.php" class="nav-link">
                    <span class="nav-icon">💰</span>
                    Budgets
                </a>
            </li>
            <li class="nav-item">
                <a href="savings.php" class="nav-link">
                    <span class="nav-icon">🎯</span>
                    Savings Goals
                </a>
            </li>
            <li class="nav-item">
                <a href="analytics.php" class="nav-link active">
                    <span class="nav-icon">📈</span>
                    Analytics
                </a>
            </li>
            <li class="nav-item">
                <a href="setting.php" class="nav-link">
                    <span class="nav-icon">⚙</span>
                    Settings
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link">
                    <span class="nav-icon">🚪</span>
                    Logout
                </a>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">📈 Financial Analytics</div>
            <div class="top-bar-actions">
                <button class="btn-print" onclick="window.print()">
                    🖨 Print Report
                </button>
                <div class="period-selector">
                    <button class="period-btn <?php echo $period == '3' ? 'active' : ''; ?>" onclick="window.location.href='?period=3'">3 Months</button>
                    <button class="period-btn <?php echo $period == '6' ? 'active' : ''; ?>" onclick="window.location.href='?period=6'">6 Months</button>
                    <button class="period-btn <?php echo $period == '12' ? 'active' : ''; ?>" onclick="window.location.href='?period=12'">12 Months</button>
                </div>
            </div>
        </div>

        <!-- AI-Generated Insights -->
        <?php if (count($insights) > 0): ?>
        <div class="insights-grid">
            <?php foreach ($insights as $insight): ?>
            <div class="insight-card <?php echo $insight['type']; ?>">
                <div class="insight-header">
                    <span class="insight-icon"><?php echo $insight['icon']; ?></span>
                    <span class="insight-title"><?php echo $insight['title']; ?></span>
                </div>
                <div class="insight-message"><?php echo $insight['message']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- REPORT 1: Monthly Expenditure Analysis -->
        <div class="report-section">
            <div class="report-header">
                <div class="report-title">
                    <span class="report-icon">📊</span>
                    Monthly Expenditure Analysis
                </div>
            </div>

            <?php if (count($monthlyData) > 0): ?>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">Average Monthly</div>
                    <div class="stat-value">$<?php echo number_format($avgMonthlyExpense, 2); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Highest Month</div>
                    <div class="stat-value">$<?php echo number_format(max(array_column($monthlyData, 'TOTAL_SPENT')), 2); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Lowest Month</div>
                    <div class="stat-value">$<?php echo number_format(min(array_column($monthlyData, 'TOTAL_SPENT')), 2); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Total Transactions</div>
                    <div class="stat-value"><?php echo array_sum(array_column($monthlyData, 'TRANSACTION_COUNT')); ?></div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Transactions</th>
                        <th>Total Spent</th>
                        <th>Average</th>
                        <th>Min/Max</th>
                        <th>Change</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyData as $month): 
                        $growth = $monthlyGrowth[$month['MONTH']] ?? 0;
                    ?>
                    <tr>
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($month['MONTH']); ?></td>
                        <td><?php echo $month['TRANSACTION_COUNT']; ?></td>
                        <td style="font-weight: 700; color: #1a202c;">$<?php echo number_format($month['TOTAL_SPENT'], 2); ?></td>
                        <td>$<?php echo number_format($month['AVG_TRANSACTION'], 2); ?></td>
                        <td style="font-size: 12px; color: #64748b;">
                            $<?php echo number_format($month['MIN_TRANSACTION'], 2); ?> / 
                            $<?php echo number_format($month['MAX_TRANSACTION'], 2); ?>
                        </td>
                        <td>
                            <span class="stat-change <?php echo $growth >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo $growth >= 0 ? '▲' : '▼'; ?> <?php echo number_format(abs($growth), 1); ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📊</div>
                <h3>No expenditure data available</h3>
            </div>
            <?php endif; ?>
        </div>

        <!-- REPORT 2: Budget Adherence Tracking -->
        <div class="report-section">
            <div class="report-header">
                <div class="report-title">
                    <span class="report-icon">🎯</span>
                    Budget Adherence Tracking
                </div>
            </div>

            <?php if (count($adherenceData) > 0): ?>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">Total Budget</div>
                    <div class="stat-value">$<?php echo number_format($totalBudget, 2); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Total Spent</div>
                    <div class="stat-value">$<?php echo number_format($totalSpent, 2); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Overall Adherence</div>
                    <div class="stat-value"><?php echo round($overallAdherence); ?>%</div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill <?php echo $overallAdherence > 100 ? 'over' : ''; ?>" 
                             style="width: <?php echo min($overallAdherence, 100); ?>%"></div>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Remaining Budget</div>
                    <div class="stat-value">$<?php echo number_format($totalBudget - $totalSpent, 2); ?></div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Budget Name</th>
                        <th>Category</th>
                        <th>Budget Amount</th>
                        <th>Spent</th>
                        <th>Remaining</th>
                        <th>Usage</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($adherenceData as $budget): 
                        $usagePercent = $budget['USAGE_PERCENTAGE'];
                    ?>
                    <tr>
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($budget['NAME']); ?></td>
                        <td><?php echo htmlspecialchars($budget['CATEGORY']); ?></td>
                        <td>$<?php echo number_format($budget['BUDGET_AMOUNT'], 2); ?></td>
                        <td style="font-weight: 700;">$<?php echo number_format($budget['SPENT'], 2); ?></td>
                        <td style="color: <?php echo $budget['REMAINING'] < 0 ? '#ef4444' : '#10b981'; ?>;">
                            $<?php echo number_format($budget['REMAINING'], 2); ?>
                        </td>
                        <td>
                            <?php echo round($usagePercent); ?>%
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill <?php echo $usagePercent > 100 ? 'over' : ''; ?>" 
                                     style="width: <?php echo min($usagePercent, 100); ?>%"></div>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower(str_replace([' ', 'Budget'], '', $budget['STATUS'])); ?>">
                                <?php echo $budget['STATUS']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🎯</div>
                <h3>No budget data available</h3>
            </div>
            <?php endif; ?>
        </div>

        <!-- REPORT 3: Savings Goal Progress -->
        <div class="report-section">
            <div class="report-header">
                <div class="report-title">
                    <span class="report-icon">💎</span>
                    Savings Goal Progress
                </div>
            </div>

            <?php if (count($savingsData) > 0): 
                $totalTargetSavings = array_sum(array_column($savingsData, 'TARGET_AMOUNT'));
                $totalCurrentSavings = array_sum(array_column($savingsData, 'CURRENT_AMOUNT'));
                $overallSavingsProgress = $totalTargetSavings > 0 ? ($totalCurrentSavings / $totalTargetSavings) * 100 : 0;
            ?>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">Total Target</div>
                    <div class="stat-value">$<?php echo number_format($totalTargetSavings, 2); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Total Saved</div>
                    <div class="stat-value">$<?php echo number_format($totalCurrentSavings, 2); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Overall Progress</div>
                    <div class="stat-value"><?php echo round($overallSavingsProgress); ?>%</div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill <?php echo $overallSavingsProgress >= 100 ? 'complete' : ''; ?>" 
                             style="width: <?php echo min($overallSavingsProgress, 100); ?>%"></div>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Active Goals</div>
                    <div class="stat-value"><?php echo count($savingsData); ?></div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Goal Name</th>
                        <th>Category</th>
                        <th>Current / Target</th>
                        <th>Progress</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($savingsData as $goal): 
                        $progressPercent = $goal['PROGRESS_PERCENTAGE'];
                    ?>
                    <tr>
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($goal['NAME']); ?></td>
                        <td><?php echo htmlspecialchars($goal['CATEGORY']); ?></td>
                        <td>
                            <div style="font-weight: 700;">$<?php echo number_format($goal['CURRENT_AMOUNT'], 2); ?></div>
                            <div style="font-size: 12px; color: #64748b;">of $<?php echo number_format($goal['TARGET_AMOUNT'], 2); ?></div>
                        </td>
                        <td>
                            <?php echo round($progressPercent); ?>%
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill <?php echo $progressPercent >= 100 ? 'complete' : ''; ?>" 
                                     style="width: <?php echo min($progressPercent, 100); ?>%"></div>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $goal['STATUS'])); ?>">
                                <?php echo $goal['STATUS']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">💎</div>
                <h3>No savings goals available</h3>
            </div>
            <?php endif; ?>
        </div>

        <!-- REPORT 4: Category-wise Expense Distribution -->
        <div class="report-section">
            <div class="report-header">
                <div class="report-title">
                    <span class="report-icon">🎨</span>
                    Category-wise Expense Distribution
                </div>
            </div>

            <?php if (count($categoryData) > 0): ?>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">Total Categories</div>
                    <div class="stat-value"><?php echo count($categoryData); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Total Spending</div>
                    <div class="stat-value">$<?php echo number_format($totalCategorySpending, 2); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Top Category</div>
                    <div class="stat-value" style="font-size: 16px;">
                        <?php echo $categoryData[0]['CATEGORY']; ?>
                        <div style="font-size: 20px; margin-top: 4px;">$<?php echo number_format($categoryData[0]['TOTAL_SPENT'], 2); ?></div>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Avg per Category</div>
                    <div class="stat-value">$<?php echo number_format($totalCategorySpending / count($categoryData), 2); ?></div>
                </div>
            </div>

            <div class="chart-container">
                <?php foreach ($categoryData as $cat): ?>
                <div class="category-bar">
                    <div class="category-label"><?php echo htmlspecialchars($cat['CATEGORY']); ?></div>
                    <div class="category-progress">
                        <div class="category-progress-fill" style="width: <?php echo $cat['PERCENTAGE']; ?>%">
                            <?php if ($cat['PERCENTAGE'] > 15): ?>
                                <?php echo round($cat['PERCENTAGE'], 1); ?>%
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="category-amount">$<?php echo number_format($cat['TOTAL_SPENT'], 2); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <table style="margin-top: 24px;">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Transactions</th>
                        <th>Total Spent</th>
                        <th>Average</th>
                        <th>Budget Used</th>
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoryData as $cat): ?>
                    <tr>
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($cat['CATEGORY']); ?></td>
                        <td><?php echo $cat['TRANSACTION_COUNT']; ?></td>
                        <td style="font-weight: 700; color: #1a202c;">$<?php echo number_format($cat['TOTAL_SPENT'], 2); ?></td>
                        <td>$<?php echo number_format($cat['AVG_EXPENSE'], 2); ?></td>
                        <td><?php echo round($cat['BUDGET_UTILIZATION']); ?>%</td>
                        <td style="font-weight: 600; color: #667eea;"><?php echo round($cat['PERCENTAGE'], 1); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🎨</div>
                <h3>No category data available</h3>
            </div>
            <?php endif; ?>
        </div>

        <!-- REPORT 5: Forecasted Savings Trends -->
        <div class="report-section">
            <div class="report-header">
                <div class="report-title">
                    <span class="report-icon">🔮</span>
                    Forecasted Savings Trends
                </div>
            </div>

            <?php if (count($forecastData) > 0): ?>
            <div class="forecast-grid">
                <?php foreach ($forecastData as $forecast): ?>
                <div class="forecast-card">
                    <div class="forecast-month"><?php echo htmlspecialchars($forecast['FORECAST_MONTH']); ?></div>
                    <div class="forecast-amount">$<?php echo number_format($forecast['PROJECTED_NET_SAVINGS'], 2); ?></div>
                    <div class="forecast-label">Projected Net Savings</div>
                </div>
                <?php endforeach; ?>
            </div>

            <table style="margin-top: 24px;">
                <thead>
                    <tr>
                        <th>Forecast Month</th>
                        <th>Projected Net Savings</th>
                        <th>Trend</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forecastData as $i => $forecast): 
                        $trend = 'stable';
                        if ($i > 0) {
                            $diff = $forecast['PROJECTED_NET_SAVINGS'] - $forecastData[$i-1]['PROJECTED_NET_SAVINGS'];
                            $trend = $diff > 0 ? 'positive' : ($diff < 0 ? 'negative' : 'stable');
                        }
                    ?>
                    <tr>
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($forecast['FORECAST_MONTH']); ?></td>
                        <td style="font-weight: 700; color: <?php echo $forecast['PROJECTED_NET_SAVINGS'] >= 0 ? '#10b981' : '#ef4444'; ?>">
                            $<?php echo number_format($forecast['PROJECTED_NET_SAVINGS'], 2); ?>
                        </td>
                        <td>
                            <span class="stat-change <?php echo $trend; ?>">
                                <?php 
                                    if ($trend === 'positive') echo '▲ Increasing';
                                    elseif ($trend === 'negative') echo '▼ Decreasing';
                                    else echo '● Stable';
                                ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🔮</div>
                <h3>Insufficient data for forecasting</h3>
                <p style="margin-top: 8px;">Add more expenses and savings transactions to generate accurate forecasts</p>
            </div>
            <?php endif; ?>
        </div>

    </main>

    <script>
        // Animate progress bars on load
        window.addEventListener('load', function() {
            const progressBars = document.querySelectorAll('.category-progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });
    </script>
</body>
</html>