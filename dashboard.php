<?php
session_start();
require_once __DIR__ . '/database/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$now = new DateTime();
$firstDayOfMonth = new DateTime('first day of this month');

// 1. Fetch all financial data
// Personal Transactions
$personalTransactions = [];
$stmt = $mysqli->prepare("SELECT * FROM PersonalTransaction WHERE userId = ? ORDER BY transactionDate DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $personalTransactions[] = $row;
$stmt->close();

// Product Sales
$productSales = [];
$stmt = $mysqli->prepare("SELECT ps.*, p.name as productName FROM ProductSale ps JOIN Product p ON ps.productId = p.id WHERE p.userId = ? ORDER BY ps.soldAt DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $productSales[] = $row;
$stmt->close();

// Product Costs
$productCosts = [];
$stmt = $mysqli->prepare("SELECT pc.* FROM ProductCost pc JOIN Product p ON pc.productId = p.id WHERE p.userId = ? ORDER BY pc.incurredDate DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $productCosts[] = $row;
$stmt->close();

// Business Expenses
$businessExpenses = [];
$stmt = $mysqli->prepare("SELECT * FROM BusinessExpense WHERE userId = ? ORDER BY expenseDate DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $businessExpenses[] = $row;
$stmt->close();

// Active Jobs Count
$stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM Product WHERE userId = ? AND status = 'wip'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$activeJobsCount = $result->fetch_assoc()['count'];
$stmt->close();

// 2. Aggregate Calculations
$totalIncome = 0;
$totalExpenses = 0;

foreach ($personalTransactions as $t) {
    if ($t['type'] === 'income') $totalIncome += $t['amount'];
    else $totalExpenses += $t['amount'];
}
foreach ($productSales as $s) $totalIncome += $s['sellingPrice'];
foreach ($productCosts as $c) $totalExpenses += $c['amount'];
foreach ($businessExpenses as $b) $totalExpenses += $b['amount'];

$totalBalance = $totalIncome - $totalExpenses;

// Monthly Stats
$monthlyIncome = 0;
$monthlyExpenses = 0;

foreach ($personalTransactions as $t) {
    $date = new DateTime($t['transactionDate']);
    if ($date >= $firstDayOfMonth) {
        if ($t['type'] === 'income') $monthlyIncome += $t['amount'];
        else $monthlyExpenses += $t['amount'];
    }
}
foreach ($productSales as $s) {
    $date = new DateTime($s['soldAt']);
    if ($date >= $firstDayOfMonth) $monthlyIncome += $s['sellingPrice'];
}
foreach ($productCosts as $c) {
    $date = new DateTime($c['incurredDate']);
    if ($date >= $firstDayOfMonth) $monthlyExpenses += $c['amount'];
}
foreach ($businessExpenses as $b) {
    $date = new DateTime($b['expenseDate']);
    if ($date >= $firstDayOfMonth) $monthlyExpenses += $b['amount'];
}

// Chart Data (Last 6 Months)
$chartData = [];
for ($i = 5; $i >= 0; $i--) {
    $d = new DateTime();
    $d->modify("first day of -$i month");
    $d->setTime(0, 0, 0);
    
    $nextMonth = clone $d;
    $nextMonth->modify('+1 month');
    
    $monthName = $d->format('M');
    $mIncome = 0;
    $mExpense = 0;

    // Filter loops (naive but identical logic to JS)
    foreach ($personalTransactions as $t) {
        $date = new DateTime($t['transactionDate']);
        if ($date >= $d && $date < $nextMonth) {
            if ($t['type'] === 'income') $mIncome += $t['amount'];
            else $mExpense += $t['amount'];
        }
    }
    foreach ($productSales as $s) {
        $date = new DateTime($s['soldAt']);
        if ($date >= $d && $date < $nextMonth) $mIncome += $s['sellingPrice'];
    }
    foreach ($productCosts as $c) {
        $date = new DateTime($c['incurredDate']);
        if ($date >= $d && $date < $nextMonth) $mExpense += $c['amount'];
    }
    foreach ($businessExpenses as $b) {
        $date = new DateTime($b['expenseDate']);
        if ($date >= $d && $date < $nextMonth) $mExpense += $b['amount'];
    }

    $chartData[] = ['name' => $monthName, 'income' => $mIncome, 'expense' => $mExpense];
}

// Recent Transactions (Combined)
$recentItems = [];
foreach ($personalTransactions as $t) {
    $recentItems[] = [
        'id' => 'p-'.$t['id'], 
        'type' => $t['type'], 
        'amount' => $t['amount'], 
        'date' => $t['transactionDate'], 
        'cat' => $t['category'], 
        'note' => $t['note'] ?? 'Personal'
    ];
}
foreach ($productSales as $s) {
    $recentItems[] = [
        'id' => 's-'.$s['id'], 
        'type' => 'income', 
        'amount' => $s['sellingPrice'], 
        'date' => $s['soldAt'], 
        'cat' => 'Sale', 
        'note' => 'Sold ' . $s['quantity'] . ' items'
    ];
}
foreach ($productCosts as $c) {
    $recentItems[] = [
        'id' => 'c-'.$c['id'], 
        'type' => 'expense', 
        'amount' => $c['amount'], 
        'date' => $c['incurredDate'], 
        'cat' => $c['costType'], 
        'note' => $c['description'] ?? 'Cost'
    ];
}
foreach ($businessExpenses as $b) {
    $recentItems[] = [
        'id' => 'b-'.$b['id'], 
        'type' => 'expense', 
        'amount' => $b['amount'], 
        'date' => $b['expenseDate'], 
        'cat' => $b['category'], 
        'note' => $b['description'] ?? 'Business'
    ];
}

usort($recentItems, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$recentItems = array_slice($recentItems, 0, 5);

// Stats Array
$stats = [
    ['label' => 'Total Balance', 'value' => '$'.number_format($totalBalance, 2), 'icon' => 'dollar-sign', 'color' => 'text-blue-600', 'bg' => 'bg-blue-100'],
    ['label' => 'Monthly Income', 'value' => '$'.number_format($monthlyIncome, 2), 'icon' => 'trending-up', 'color' => 'text-green-600', 'bg' => 'bg-green-100'],
    ['label' => 'Monthly Expenses', 'value' => '$'.number_format($monthlyExpenses, 2), 'icon' => 'trending-down', 'color' => 'text-red-600', 'bg' => 'bg-red-100'],
    ['label' => 'Active Jobs', 'value' => $activeJobsCount, 'icon' => 'briefcase', 'color' => 'text-purple-600', 'bg' => 'bg-purple-100'],
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-background">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <main class="flex-1 overflow-y-auto w-full">
        <div class="p-8 space-y-8 animate-in">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Overview</h1>
                    <p class="text-slate-500 mt-1">Track your personal and woodworking business finances.</p>
                </div>
                <div class="flex items-center gap-2 text-sm font-medium text-slate-500 bg-white px-4 py-2 rounded-xl border border-slate-200 shadow-sm">
                    <i data-lucide="clock" class="w-4 h-4"></i>
                    <?php echo $now->format('F j, Y'); ?>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($stats as $stat): ?>
                    <div class="modern-card p-6">
                        <div class="flex justify-between items-start">
                            <div class="<?php echo $stat['bg'] . ' ' . $stat['color']; ?> p-3 rounded-2xl">
                                <i data-lucide="<?php echo $stat['icon']; ?>" class="w-6 h-6"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-sm font-medium text-slate-500"><?php echo $stat['label']; ?></p>
                            <p class="text-2xl font-bold text-slate-900 mt-1"><?php echo $stat['value']; ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Chart Section -->
                <div class="lg:col-span-2 modern-card p-8">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">Financial Growth</h2>
                            <p class="text-sm text-slate-500">Visualization of your income vs expenses.</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-blue-600"></div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Income</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-red-500"></div> <!-- Tailwind danger usually red-500/600 -->
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Expense</span>
                            </div>
                        </div>
                    </div>

                    <!-- Simple CSS Chart (replacing Recharts) -->
                    <div class="h-64 flex items-end gap-2 justify-between">
                         <?php 
                         // Find max value for scaling
                         $maxVal = 1;
                         foreach ($chartData as $d) {
                             $maxVal = max($maxVal, $d['income'], $d['expense']);
                         }
                         ?>
                         <?php foreach ($chartData as $d): ?>
                            <div class="flex flex-col items-center w-full gap-2 group">
                                <div class="relative w-full flex justify-center h-full items-end gap-1">
                                    <!-- Income Bar -->
                                    <div class="w-3 md:w-6 bg-blue-600 rounded-t-sm transition-all duration-500 ease-out group-hover:bg-blue-500" 
                                         style="height: <?php echo ($d['income'] / $maxVal) * 100; ?>%; min-height: 4px;"
                                         title="Income: $<?php echo number_format($d['income']); ?>">
                                    </div>
                                    <!-- Expense Bar -->
                                    <div class="w-3 md:w-6 bg-red-500 rounded-t-sm transition-all duration-500 ease-out group-hover:bg-red-400" 
                                         style="height: <?php echo ($d['expense'] / $maxVal) * 100; ?>%; min-height: 4px;"
                                         title="Review: $<?php echo number_format($d['expense']); ?>">
                                    </div>
                                </div>
                                <span class="text-xs text-slate-400"><?php echo $d['name']; ?></span>
                            </div>
                         <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="modern-card overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-100 mb-0">
                        <h2 class="text-xl font-bold text-slate-900 font-sans">Recent Activities</h2>
                    </div>
                    <div class="table-container border-none rounded-none">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-500">
                                <tr>
                                    <th class="px-6 py-3 font-bold uppercase text-xs">Date</th>
                                    <th class="px-6 py-3 font-bold uppercase text-xs">Activity</th>
                                    <th class="px-6 py-3 font-bold uppercase text-xs text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php foreach ($recentItems as $item): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4 text-slate-500 whitespace-nowrap">
                                            <?php echo (new DateTime($item['date']))->format('M j, Y'); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="p-1.5 rounded-lg <?php echo $item['type'] === 'income' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                                                    <i data-lucide="<?php echo $item['type'] === 'income' ? 'arrow-up-right' : 'arrow-down-right'; ?>" class="w-4 h-4"></i>
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-slate-900"><?php echo htmlspecialchars($item['cat']); ?></span>
                                                    <span class="text-xs text-slate-400 max-w-[200px] truncate">
                                                        <?php echo htmlspecialchars($item['note'] ?? ''); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="font-bold <?php echo $item['type'] === 'income' ? 'text-green-600' : 'text-red-600'; ?>">
                                                <?php echo $item['type'] === 'income' ? '+' : '-'; ?>$<?php echo number_format($item['amount'], 2); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentItems)): ?>
                                    <tr>
                                        <td colspan="3" class="px-6 py-8 text-center text-slate-400 italic">No recent activities found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
