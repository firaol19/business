<?php
session_start();
require_once __DIR__ . '/database/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Get Filter Parameters
$filterType = $_GET['type'] ?? 'daily'; // daily, monthly, yearly
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedYear = $_GET['year'] ?? date('Y');
$selectedMonth = $_GET['month'] ?? date('n');

// Build Date Range Conditions
$dateCondition = "";
$params = [$userId];
$types = "i";

if ($filterType === 'daily') {
    $dateCondition = "DATE(created_at_col) = ?";
    $params[] = $selectedDate;
    $types .= "s";
} elseif ($filterType === 'monthly') {
    $dateCondition = "YEAR(created_at_col) = ? AND MONTH(created_at_col) = ?";
    $params[] = $selectedYear;
    $params[] = $selectedMonth;
    $types .= "ii";
} elseif ($filterType === 'yearly') {
    $dateCondition = "YEAR(created_at_col) = ?";
    $params[] = $selectedYear;
    $types .= "i";
}

// Helper to fetch data
function fetchData($mysqli, $table, $dateCol, $dateCondition, $params, $types, $extraSelect = "*") {
    // Replace placeholder with actual column name
    $condition = str_replace('created_at_col', $dateCol, $dateCondition);
    
    // Start with WHERE userId = ?
    $sql = "SELECT $extraSelect FROM $table WHERE userId = ? AND $condition ORDER BY $dateCol DESC";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
    return $data;
}

// Fetch Personal Transactions
$personalTransactions = fetchData($mysqli, "PersonalTransaction", "transactionDate", $dateCondition, $params, $types);

// Fetch Business Expenses
$businessExpenses = fetchData($mysqli, "BusinessExpense", "expenseDate", $dateCondition, $params, $types);

// Fetch Product Costs
// Need to join with Product to get product name
// Condition based on ProductCost.incurredDate
$costCondition = str_replace('created_at_col', 'pc.incurredDate', $dateCondition);
$costSql = "SELECT pc.*, p.name as productName FROM ProductCost pc 
            JOIN Product p ON pc.productId = p.id 
            WHERE p.userId = ? AND $costCondition 
            ORDER BY pc.incurredDate DESC";
$stmt = $mysqli->prepare($costSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$productCosts = [];
$costResult = $stmt->get_result();
while ($row = $costResult->fetch_assoc()) {
    $productCosts[] = $row;
}
$stmt->close();


// Calculate Totals
$totalPersonalIncome = 0;
$totalPersonalExpense = 0;
foreach ($personalTransactions as $t) {
    if ($t['type'] === 'income') {
        $totalPersonalIncome += $t['amount'];
    } else {
        $totalPersonalExpense += $t['amount'];
    }
}

$totalBusinessExpense = 0;
foreach ($businessExpenses as $e) {
    $totalBusinessExpense += $e['amount'];
}

$totalProductCosts = 0;
foreach ($productCosts as $c) {
    $totalProductCosts += $c['amount'];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-background">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto w-full">
        <div class="p-8 space-y-8 animate-in">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold text-slate-900">Transaction History</h1>
                <button onclick="window.print()" class="flex items-center gap-2 bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span class="font-medium text-sm">Print</span>
                </button>
            </div>

            <!-- Date Filters -->
            <div class="modern-card p-6 bg-white print:hidden">
                <form method="GET" action="" class="flex flex-wrap gap-4 items-end">
                    <!-- View Type Selector -->
                    <div class="w-full sm:w-auto">
                        <label class="block text-sm font-medium text-slate-700 mb-2">View Type</label>
                        <div class="inline-flex rounded-lg border border-slate-200 p-1 bg-slate-50">
                            <?php foreach (['daily', 'monthly', 'yearly'] as $type): ?>
                                <button type="submit" name="type" value="<?php echo $type; ?>" 
                                    class="px-4 py-1.5 rounded-md text-sm font-medium transition-all <?php echo $filterType === $type ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'; ?>">
                                    <?php echo ucfirst($type); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Conditional Selectors -->
                    <?php if ($filterType === 'daily'): ?>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Select Date</label>
                            <input type="date" name="date" value="<?php echo $selectedDate; ?>" onchange="this.form.submit()"
                                class="border border-slate-300 rounded-lg px-4 py-2 text-sm text-slate-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    <?php endif; ?>

                    <?php if ($filterType === 'monthly' || $filterType === 'yearly'): ?>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Year</label>
                            <select name="year" onchange="this.form.submit()"
                                class="border border-slate-300 rounded-lg px-4 py-2 text-sm text-slate-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <?php 
                                $currentYear = date('Y');
                                for ($i = 0; $i < 5; $i++): 
                                    $y = $currentYear - $i;
                                ?>
                                    <option value="<?php echo $y; ?>" <?php echo $selectedYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($filterType === 'monthly'): ?>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Month</label>
                            <select name="month" onchange="this.form.submit()"
                                class="border border-slate-300 rounded-lg px-4 py-2 text-sm text-slate-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <?php 
                                $months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                                foreach ($months as $index => $m): 
                                    $val = $index + 1;
                                ?>
                                    <option value="<?php echo $val; ?>" <?php echo $selectedMonth == $val ? 'selected' : ''; ?>><?php echo $m; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </form>

                <p class="text-sm text-slate-500 mt-4">
                    Showing transactions for: 
                    <span class="font-semibold text-slate-900">
                        <?php 
                        if ($filterType === 'daily') echo date('l, F j, Y', strtotime($selectedDate));
                        elseif ($filterType === 'monthly') echo $months[$selectedMonth - 1] . " " . $selectedYear;
                        elseif ($filterType === 'yearly') echo "Year " . $selectedYear;
                        ?>
                    </span>
                </p>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6 print:hidden">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                    <p class="text-sm text-slate-500 font-medium">Personal Income</p>
                    <p class="text-2xl font-bold text-green-600 mt-2">$<?php echo number_format($totalPersonalIncome, 2); ?></p>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                    <p class="text-sm text-slate-500 font-medium">Personal Expenses</p>
                    <p class="text-2xl font-bold text-red-600 mt-2">$<?php echo number_format($totalPersonalExpense, 2); ?></p>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                    <p class="text-sm text-slate-500 font-medium">Business Expenses</p>
                    <p class="text-2xl font-bold text-orange-600 mt-2">$<?php echo number_format($totalBusinessExpense, 2); ?></p>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                    <p class="text-sm text-slate-500 font-medium">Product Costs</p>
                    <p class="text-2xl font-bold text-blue-600 mt-2">$<?php echo number_format($totalProductCosts, 2); ?></p>
                </div>
            </div>

            <!-- Personal Transactions Table -->
            <section class="modern-card overflow-hidden print:w-full">
                <div class="p-4 sm:p-6 border-b border-slate-100">
                    <h2 class="font-bold text-base sm:text-lg">Personal Transactions</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm min-w-[600px]">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 font-medium text-slate-500">Type</th>
                                <th class="px-6 py-3 font-medium text-slate-500">Category</th>
                                <th class="px-6 py-3 font-medium text-slate-500">Note</th>
                                <th class="px-6 py-3 font-medium text-slate-500">Payment Method</th>
                                <th class="px-6 py-3 font-medium text-slate-500 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($personalTransactions)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                                        No personal transactions found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($personalTransactions as $t): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-6 py-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $t['type'] === 'income' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo htmlspecialchars(ucfirst($t['type'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-slate-900"><?php echo htmlspecialchars($t['category']); ?></td>
                                        <td class="px-6 py-3 text-slate-500 truncate max-w-xs"><?php echo htmlspecialchars($t['note'] ?? '-'); ?></td>
                                        <td class="px-6 py-3 text-slate-500"><?php echo htmlspecialchars($t['paymentMethod'] ?? '-'); ?></td>
                                        <td class="px-6 py-3 font-medium text-right <?php echo $t['type'] === 'income' ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $t['type'] === 'income' ? '+' : '-'; ?>$<?php echo number_format($t['amount'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Business Expenses Table -->
            <section class="modern-card overflow-hidden print:w-full">
                <div class="p-4 sm:p-6 border-b border-slate-100">
                    <h2 class="font-bold text-base sm:text-lg">Business Expenses (General)</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm min-w-[600px]">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 font-medium text-slate-500">Category</th>
                                <th class="px-6 py-3 font-medium text-slate-500">Description</th>
                                <th class="px-6 py-3 font-medium text-slate-500">Payment Method</th>
                                <th class="px-6 py-3 font-medium text-slate-500">Note</th>
                                <th class="px-6 py-3 font-medium text-slate-500 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($businessExpenses)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                                        No business expenses found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($businessExpenses as $e): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-6 py-3 text-slate-900"><?php echo htmlspecialchars($e['category']); ?></td>
                                        <td class="px-6 py-3 text-slate-500"><?php echo htmlspecialchars($e['description'] ?? '-'); ?></td>
                                        <td class="px-6 py-3 text-slate-500"><?php echo htmlspecialchars($e['paymentMethod'] ?? '-'); ?></td>
                                        <td class="px-6 py-3 text-slate-500 truncate max-w-xs"><?php echo htmlspecialchars($e['note'] ?? '-'); ?></td>
                                        <td class="px-6 py-3 font-medium text-right text-orange-600">
                                            $<?php echo number_format($e['amount'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Product Costs Table -->
            <section class="modern-card overflow-hidden print:w-full">
                <div class="p-4 sm:p-6 border-b border-slate-100">
                    <h2 class="font-bold text-base sm:text-lg">Product/Job Costs</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm min-w-[500px]">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 font-medium text-slate-500">Product Name</th>
                                <th class="px-6 py-3 font-medium text-slate-500">Cost Type</th>
                                <th class="px-6 py-3 font-medium text-slate-500">Description</th>
                                <th class="px-6 py-3 font-medium text-slate-500 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($productCosts)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-slate-500">
                                        No product costs found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($productCosts as $c): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-6 py-3 text-slate-900 font-medium"><?php echo htmlspecialchars($c['productName']); ?></td>
                                        <td class="px-6 py-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <?php echo htmlspecialchars($c['costType']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-slate-500"><?php echo htmlspecialchars($c['description'] ?? '-'); ?></td>
                                        <td class="px-6 py-3 font-medium text-right text-blue-600">
                                            $<?php echo number_format($c['amount'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
