<?php
session_start();
require_once __DIR__ . '/database/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$selectedYear = $_GET['year'] ?? date('Y');
$selectedMonth = $_GET['month'] ?? ''; // Empty means all year

// Build Query Conditions
$params = [$userId];
$types = "i";
$dateFilter = "YEAR(expenseDate) = ?";
$params[] = $selectedYear;
$types .= "i";

if ($selectedMonth) {
    $dateFilter .= " AND MONTH(expenseDate) = ?";
    $params[] = $selectedMonth;
    $types .= "i";
}

// 1. Fetch Summary Data
// Personal Income
$pIncomeSql = "SELECT SUM(amount) as total FROM PersonalTransaction WHERE userId = ? AND type = 'income' AND " . str_replace('expenseDate', 'transactionDate', $dateFilter);
$stmt = $mysqli->prepare($pIncomeSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$personalIncome = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Personal Expense
$pExpenseSql = "SELECT SUM(amount) as total FROM PersonalTransaction WHERE userId = ? AND type = 'expense' AND " . str_replace('expenseDate', 'transactionDate', $dateFilter);
$stmt = $mysqli->prepare($pExpenseSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$personalExpense = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Business Expense
$bExpenseSql = "SELECT SUM(amount) as total FROM BusinessExpense WHERE userId = ? AND $dateFilter";
$stmt = $mysqli->prepare($bExpenseSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$businessExpense = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Product Sales (Revenue)
$pSalesSql = "SELECT SUM(sellingPrice) as total, COUNT(*) as count FROM ProductSale ps JOIN Product p ON ps.productId = p.id WHERE p.userId = ? AND " . str_replace('expenseDate', 'soldAt', $dateFilter);
$stmt = $mysqli->prepare($pSalesSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$productRevenue = $res['total'] ?? 0;
$itemsSold = $res['count'] ?? 0;
$stmt->close();

// Product Costs
$pCostSql = "SELECT SUM(amount) as total FROM ProductCost pc JOIN Product p ON pc.productId = p.id WHERE p.userId = ? AND " . str_replace('expenseDate', 'incurredDate', $dateFilter);
$stmt = $mysqli->prepare($pCostSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$productCosts = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();


$combinedRevenue = $personalIncome + $productRevenue;
$totalExpenses = $personalExpense + $businessExpense + $productCosts;
$netProfit = $combinedRevenue - $totalExpenses;


// 2. Data for Charts (Monthly Breakdown)
// Initialize arrays for 12 months
$chartData = [];
for ($m = 1; $m <= 12; $m++) {
    $chartData[$m] = ['income' => 0, 'expense' => 0, 'profit' => 0];
}

// Helper to fill chart data
function fillChartData($mysqli, $sql, $params, $types, $type, &$chartData) {
    if (count($params) > 2) {
        // If filtering by specific month, only that month will get data
        // But for charts we usually want the whole year context if looking at a year.
        // If User selected a Month, maybe we show daily? 
        // For simplicity, let's just show the selected year's monthly trend regardless of month selection (or distinct by month if month selected is handled elsewhere).
        // Let's stick to Year filter for the chart data query.
        $params = array_slice($params, 0, 2); // Remove month param if present
        $types = substr($types, 0, 2); 
    }
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $m = $row['m'];
        if ($type === 'income') $chartData[$m]['income'] += $row['total'];
        if ($type === 'expense') $chartData[$m]['expense'] += $row['total'];
    }
    $stmt->close();
}

// Income by Month
$sql = "SELECT MONTH(transactionDate) as m, SUM(amount) as total FROM PersonalTransaction WHERE userId = ? AND type = 'income' AND YEAR(transactionDate) = ? GROUP BY m";
fillChartData($mysqli, $sql, $params, $types, 'income', $chartData);
$sql = "SELECT MONTH(soldAt) as m, SUM(sellingPrice) as total FROM ProductSale ps JOIN Product p ON ps.productId = p.id WHERE p.userId = ? AND YEAR(soldAt) = ? GROUP BY m";
fillChartData($mysqli, $sql, $params, $types, 'income', $chartData);

// Expense by Month
$sql = "SELECT MONTH(transactionDate) as m, SUM(amount) as total FROM PersonalTransaction WHERE userId = ? AND type = 'expense' AND YEAR(transactionDate) = ? GROUP BY m";
fillChartData($mysqli, $sql, $params, $types, 'expense', $chartData);
$sql = "SELECT MONTH(expenseDate) as m, SUM(amount) as total FROM BusinessExpense WHERE userId = ? AND YEAR(expenseDate) = ? GROUP BY m";
fillChartData($mysqli, $sql, $params, $types, 'expense', $chartData);
$sql = "SELECT MONTH(incurredDate) as m, SUM(amount) as total FROM ProductCost pc JOIN Product p ON pc.productId = p.id WHERE p.userId = ? AND YEAR(incurredDate) = ? GROUP BY m";
fillChartData($mysqli, $sql, $params, $types, 'expense', $chartData);

// Calculate Profit
foreach ($chartData as $m => $data) {
    $chartData[$m]['profit'] = $data['income'] - $data['expense'];
}

// 3. Category Breakdown (Expense Distribution)
$categories = [];
function fillCategoryData($mysqli, $sql, $params, $types, &$categories) {
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cat = $row['category'];
        if (!isset($categories[$cat])) $categories[$cat] = 0;
        $categories[$cat] += $row['total'];
    }
    $stmt->close();
}

// Use original filters (with month if selected)
$sql = "SELECT category, SUM(amount) as total FROM PersonalTransaction WHERE userId = ? AND type = 'expense' AND " . str_replace('expenseDate', 'transactionDate', $dateFilter) . " GROUP BY category";
fillCategoryData($mysqli, $sql, $params, $types, $categories);
$sql = "SELECT category, SUM(amount) as total FROM BusinessExpense WHERE userId = ? AND $dateFilter GROUP BY category";
fillCategoryData($mysqli, $sql, $params, $types, $categories);
// For Product Costs, we can group by 'Product Costs' or detailed
$categories['Product Costs'] = $productCosts;


// 4. Job Profitability
$jobAnalysis = [];
$sql = "SELECT p.id, p.name, 
        (SELECT COALESCE(SUM(sellingPrice), 0) FROM ProductSale WHERE productId = p.id) as revenue,
        (SELECT COALESCE(SUM(amount), 0) FROM ProductCost WHERE productId = p.id) as cost
        FROM Product p WHERE p.userId = ?";
// Filter job analysis by... well, usually it's all time or finished recently? Let's show all for now or top 5 by revenue
// The original code calculated this on the fly.
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId); // Show all products for simplified profitability view
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['profit'] = $row['revenue'] - $row['cost'];
    $jobAnalysis[] = $row;
}
$stmt->close();
// Sort by profit desc
usort($jobAnalysis, function ($a, $b) {
    return $b['profit'] <=> $a['profit'];
});
$jobAnalysis = array_slice($jobAnalysis, 0, 5);


// 5. Heuristic AI Analysis
$alerts = [];
$suggestions = [];
$summary = "";
$prediction = "";
$showAnalysis = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['analyze'])) {
    $showAnalysis = true;
    
    $summary = "Based on the selected period: Total income is $" . number_format($combinedRevenue, 2) . ", expenses are $" . number_format($totalExpenses, 2) . ", resulting in a " . ($netProfit >= 0 ? "positive" : "negative") . " net of $" . number_format($netProfit, 2) . ".";

    if ($netProfit < 0) $alerts[] = "⚠️ Expenses exceed income - immediate action needed";
    if ($totalExpenses > $combinedRevenue * 0.8 && $combinedRevenue > 0) $alerts[] = "⚠️ Expenses are high relative to income (" . number_format(($totalExpenses / $combinedRevenue) * 100, 1) . "%)";

    // Find top expense category
    $topCat = '';
    $topCatAmount = 0;
    foreach ($categories as $cat => $amt) {
        if ($amt > $topCatAmount) {
            $topCatAmount = $amt;
            $topCat = $cat;
        }
    }
    if ($topCat && $totalExpenses > 0 && ($topCatAmount / $totalExpenses) > 0.4) {
        $alerts[] = "⚠️ $topCat accounts for a large portion of expenses (" . number_format(($topCatAmount / $totalExpenses) * 100, 1) . "%)";
        $suggestions[] = "Consider reducing $topCat expenses";
    }

    $suggestions[] = "Review and categorize all expenses to identify savings opportunities";
    $suggestions[] = "Set a monthly budget for each expense category";
    if ($netProfit >= 0) {
        $prediction = "If current trends continue, expect positive cash flow next month.";
    } else {
        $prediction = "Cash flow may remain negative unless expenses are reduced or income increases.";
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="flex h-screen overflow-hidden bg-background">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto w-full">
        <div class="p-8 space-y-8 animate-in pb-12">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div>
                    <h1 class="text-4xl font-bold text-slate-900 tracking-tight">Financial Intelligence</h1>
                    <p class="text-slate-500 mt-1">Advanced analytics and business insights (Heuristic).</p>
                </div>

                <form method="GET" action="" class="flex items-center gap-3 bg-white p-1.5 rounded-2xl border border-slate-200 shadow-sm">
                    <select name="year" onchange="this.form.submit()" class="border-none bg-transparent focus:ring-0 text-sm font-bold text-slate-700 cursor-pointer outline-none">
                        <?php 
                        $currYear = date('Y');
                        for ($i = 0; $i < 5; $i++): 
                            $y = $currYear - $i;
                        ?>
                            <option value="<?php echo $y; ?>" <?php echo $selectedYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                    <div class="w-px h-4 bg-slate-200"></div>
                    <select name="month" onchange="this.form.submit()" class="border-none bg-transparent focus:ring-0 text-sm font-bold text-slate-700 cursor-pointer outline-none">
                        <option value="">All Year</option>
                        <?php 
                        $months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                        foreach ($months as $index => $m): 
                            $val = $index + 1;
                        ?>
                            <option value="<?php echo $val; ?>" <?php echo $selectedMonth == $val ? 'selected' : ''; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- Summary Matrix -->
			<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
				<div class="modern-card p-6 bg-slate-900 text-white border-none shadow-2xl shadow-slate-900/10">
					<div class="flex items-center gap-3 text-slate-400 mb-4">
						<i data-lucide="dollar-sign" class="w-4 h-4"></i>
						<span class="text-[10px] font-bold uppercase tracking-widest text-primary">Combined Revenue</span>
					</div>
					<p class="text-3xl font-bold text-green-500">
						$<?php echo number_format($combinedRevenue, 2); ?>
					</p>
					<div class="flex items-center gap-2 mt-4 text-xs font-medium text-green-400">
						<i data-lucide="trending-up" class="w-3 h-3"></i>
						<span>Organic Growth</span>
					</div>
				</div>

				<div class="modern-card p-6 border-l-4 border-l-red-500 bg-white">
					<div class="flex items-center gap-3 text-slate-500 mb-4">
						<i data-lucide="trending-down" class="w-4 h-4"></i>
						<span class="text-[10px] font-bold uppercase tracking-widest">Total Expenses</span>
					</div>
					<p class="text-3xl font-bold text-slate-900">
						$<?php echo number_format($totalExpenses, 2); ?>
					</p>
				</div>

				<div class="modern-card p-6 border-l-4 border-l-green-500">
					<div class="flex items-center gap-3 text-slate-500 mb-4">
						<i data-lucide="zap" class="w-4 h-4 text-green-500"></i>
						<span class="text-[10px] font-bold uppercase tracking-widest">Net Profit</span>
					</div>
					<p class="text-3xl font-bold <?php echo $netProfit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
						$<?php echo number_format($netProfit, 2); ?>
					</p>
				</div>

				<div class="modern-card p-6">
					<div class="flex items-center gap-3 text-slate-500 mb-4">
						<i data-lucide="layout-grid" class="w-4 h-4"></i>
						<span class="text-[10px] font-bold uppercase tracking-widest">Items Sold</span>
					</div>
					<p class="text-3xl font-bold text-slate-900"><?php echo $itemsSold; ?></p>
					<p class="text-xs text-slate-400 mt-2 font-medium uppercase tracking-tight">Total Volume</p>
				</div>
			</div>

            <!-- Analysis Section (Heuristic) -->
            <div class="modern-card p-0 overflow-hidden border-2 border-blue-500/10 shadow-xl shadow-blue-500/5">
                <div class="bg-gradient-to-r from-blue-500/5 to-transparent p-8 border-b border-blue-500/5">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-blue-600 text-white rounded-2xl shadow-lg shadow-blue-600/30">
                                <i data-lucide="brain" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-slate-900">Analysis & Insights</h2>
                                <p class="text-sm text-slate-500">Rule-based pattern recognition and strategy suggestions.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-8">
                    <?php if (!$showAnalysis): ?>
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <div class="bg-blue-50 p-4 rounded-full mb-4">
                                <i data-lucide="sparkles" class="w-8 h-8 text-blue-600"></i>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 mb-2">Generate Financial Insights</h3>
                            <p class="text-slate-500 max-w-md mb-8">
                                Our AI-powered analysis engine will review your financial data to identify trends, cost-saving opportunities, and growth predictions.
                            </p>
                            <form method="POST" action="">
                                <input type="hidden" name="analyze" value="1">
                                <button type="submit" class="flex items-center gap-2 bg-blue-600 text-white font-bold px-8 py-3 rounded-xl hover:bg-blue-700 transition-all shadow-lg shadow-blue-600/20 active:scale-95">
                                    <i data-lucide="play" class="w-4 h-4 fill-current"></i>
                                    Run Analysis
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 animate-in">
                            <div class="space-y-6">
                                <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100">
                                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-widest mb-4 flex items-center gap-2">
                                        <i data-lucide="history" class="w-4 h-4 text-blue-600"></i> Executive Summary
                                    </h3>
                                    <p class="text-slate-600 leading-relaxed italic">"<?php echo htmlspecialchars($summary); ?>"</p>
                                </div>

                                <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100">
                                    <h3 class="text-sm font-bold text-blue-900 uppercase tracking-widest mb-4 flex items-center gap-2">
                                        <i data-lucide="trending-up" class="w-4 h-4 text-blue-600"></i> Future Prediction
                                    </h3>
                                    <p class="text-blue-900/80 font-medium"><?php echo htmlspecialchars($prediction); ?></p>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <?php foreach ($alerts as $alert): ?>
                                    <div class="flex items-start gap-4 bg-red-50 p-4 rounded-xl border border-red-100">
                                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-600 shrink-0 mt-0.5"></i>
                                        <span class="text-sm font-bold text-red-900/80"><?php echo htmlspecialchars($alert); ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach ($suggestions as $suggestion): ?>
                                    <div class="flex items-start gap-4 bg-green-50 p-4 rounded-xl border border-green-100">
                                        <i data-lucide="check-circle-2" class="w-5 h-5 text-green-600 shrink-0 mt-0.5"></i>
                                        <span class="text-sm font-bold text-green-900/80"><?php echo htmlspecialchars($suggestion); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Visual Analytics Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="modern-card p-8">
                    <h2 class="text-xl font-bold text-slate-900 mb-6">Income vs Expense Trend</h2>
                    <div class="h-[300px]">
                        <canvas id="incomeExpenseChart"></canvas>
                    </div>
                </div>

                <div class="modern-card p-8">
                    <h2 class="text-xl font-bold text-slate-900 mb-6 font-sans">Expense Distribution</h2>
                    <div class="h-[300px] flex justify-center">
                         <canvas id="expensePieChart"></canvas>
                    </div>
                </div>

                <div class="modern-card p-0 overflow-hidden lg:col-span-2">
                    <div class="p-8 border-b border-slate-100">
                        <h2 class="text-xl font-bold text-slate-900">Job Profitability (Top 5)</h2>
                    </div>
                    <div class="table-container border-none rounded-none">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-500">
                                <tr>
                                    <th class="px-6 py-3 font-bold uppercase text-xs">Product</th>
                                    <th class="px-6 py-3 font-bold uppercase text-xs text-right">Revenue</th>
                                    <th class="px-6 py-3 font-bold uppercase text-xs text-right">Cost</th>
                                    <th class="px-6 py-3 font-bold uppercase text-xs text-right">Profit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php foreach ($jobAnalysis as $job): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4 font-medium text-slate-900"><?php echo htmlspecialchars($job['name']); ?></td>
                                        <td class="px-6 py-4 text-right text-slate-600">$<?php echo number_format($job['revenue'], 2); ?></td>
                                        <td class="px-6 py-4 text-right text-slate-600">$<?php echo number_format($job['cost'], 2); ?></td>
                                        <td class="px-6 py-4 text-right font-bold <?php echo $job['profit'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                            $<?php echo number_format($job['profit'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
// Prepare Data from PHP
const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
const chartData = <?php echo json_encode(array_values($chartData)); ?>;
const categoryLabels = <?php echo json_encode(array_keys($categories)); ?>;
const categoryData = <?php echo json_encode(array_values($categories)); ?>;

// Income vs Expense Chart
const ctxIE = document.getElementById('incomeExpenseChart');
new Chart(ctxIE, {
    type: 'bar',
    data: {
        labels: months,
        datasets: [{
            label: 'Income',
            data: chartData.map(d => d.income),
            backgroundColor: '#22c55e',
            borderRadius: 4
        }, {
            label: 'Expense',
            data: chartData.map(d => d.expense),
            backgroundColor: '#ef4444',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
            x: { grid: { display: false } }
        }
    }
});

// Expense Category Pie Chart
const ctxPie = document.getElementById('expensePieChart');
new Chart(ctxPie, {
    type: 'doughnut',
    data: {
        labels: categoryLabels,
        datasets: [{
            data: categoryData,
            backgroundColor: [
                '#3b82f6', '#ef4444', '#f59e0b', '#10b981', '#6366f1', '#ec4899', '#8b5cf6', '#64748b'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right' }
        },
        cutout: '70%'
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
