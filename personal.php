<?php
session_start();
require_once __DIR__ . '/database/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$message = null;

// Handle Add Transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'expense';
    $amount = $_POST['amount'] ?? 0;
    $category = $_POST['category'] ?? 'Other';
    $date = $_POST['date'] ?? date('Y-m-d');
    $note = $_POST['note'] ?? '';
    $paymentMethod = $_POST['paymentMethod'] ?? 'Cash';

    if ($amount > 0) {
        $stmt = $mysqli->prepare("INSERT INTO PersonalTransaction (userId, type, category, amount, transactionDate, note, paymentMethod) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdsss", $userId, $type, $category, $amount, $date, $note, $paymentMethod);
        if ($stmt->execute()) {
            // Redirect to avoid resubmission
            header("Location: /personal.php");
            exit;
        } else {
            $message = "Error adding transaction.";
        }
        $stmt->close();
    }
}

// Fetch Transactions
$transactions = [];
$stmt = $mysqli->prepare("SELECT * FROM PersonalTransaction WHERE userId = ? ORDER BY transactionDate DESC LIMIT 20");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

// Calculate Totals (fetching ALL for correct totals, not just limit 20)
// Optimization: separate query for totals
$stmt = $mysqli->prepare("SELECT type, SUM(amount) as total FROM PersonalTransaction WHERE userId = ? GROUP BY type");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$totalIncome = 0;
$totalExpenses = 0;
while ($row = $result->fetch_assoc()) {
    if ($row['type'] === 'income') $totalIncome = $row['total'];
    elseif ($row['type'] === 'expense') $totalExpenses = $row['total'];
}
$stmt->close();

$netSavings = $totalIncome - $totalExpenses;

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-background">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <main class="flex-1 overflow-y-auto w-full">
        <div class="p-8 space-y-8 animate-in">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-4xl font-bold text-slate-900 tracking-tight">Personal Finance</h1>
                    <p class="text-slate-500 mt-1">Track your daily income and personal spending.</p>
                </div>
            </div>

            <!-- Summary Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="modern-card p-6 border-l-4 border-l-green-500">
                    <div class="flex items-center gap-3 text-slate-500 mb-3">
                        <i data-lucide="trending-up" class="w-4 h-4"></i>
                        <span class="text-xs font-bold uppercase tracking-wider">Total Income</span>
                    </div>
                    <p class="text-3xl font-bold text-slate-900">$<?php echo number_format($totalIncome, 2); ?></p>
                </div>

                <div class="modern-card p-6 border-l-4 border-l-red-500">
                    <div class="flex items-center gap-3 text-slate-500 mb-3">
                        <i data-lucide="trending-down" class="w-4 h-4"></i>
                        <span class="text-xs font-bold uppercase tracking-wider">Total Expenses</span>
                    </div>
                    <p class="text-3xl font-bold text-slate-900">$<?php echo number_format($totalExpenses, 2); ?></p>
                </div>

                <div class="modern-card p-6 border-l-4 <?php echo $netSavings >= 0 ? 'border-l-blue-500' : 'border-l-orange-500'; ?>">
                    <div class="flex items-center gap-3 text-slate-500 mb-3">
                        <i data-lucide="dollar-sign" class="w-4 h-4"></i>
                        <span class="text-xs font-bold uppercase tracking-wider">Net Savings</span>
                    </div>
                    <p class="text-3xl font-bold <?php echo $netSavings >= 0 ? 'text-blue-600' : 'text-red-600'; ?>">
                        $<?php echo number_format($netSavings, 2); ?>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                <!-- Add Transaction Sidebar -->
                <div class="lg:col-span-1 print:hidden sticky top-8">
                    <div class="modern-card p-8 bg-gradient-to-br from-white to-slate-50/50">
                        <div class="flex items-center gap-3 mb-8">
                            <i data-lucide="plus-circle" class="w-6 h-6 text-primary"></i>
                            <h2 class="text-xl font-bold text-slate-900">Add Transaction</h2>
                        </div>
                        
                        <form method="POST" action="" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                                <div class="grid grid-cols-2 gap-4">
                                    <label class="cursor-pointer">
                                        <input type="radio" name="type" value="income" class="peer sr-only">
                                        <div class="p-2 text-center rounded-lg border border-slate-200 peer-checked:bg-green-50 peer-checked:border-green-500 peer-checked:text-green-700 transition-all">Income</div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="type" value="expense" class="peer sr-only" checked>
                                        <div class="p-2 text-center rounded-lg border border-slate-200 peer-checked:bg-red-50 peer-checked:border-red-500 peer-checked:text-red-700 transition-all">Expense</div>
                                    </label>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Amount</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-slate-400">$</span>
                                    <input type="number" name="amount" step="0.01" required class="pl-8 w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-primary">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                                <select name="category" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-primary">
                                    <option value="Salary">Salary</option>
                                    <option value="Food">Food</option>
                                    <option value="Transport">Transport</option>
                                    <option value="Utilities">Utilities</option>
                                    <option value="Shopping">Shopping</option>
                                    <option value="Other" selected>Other</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Date</label>
                                <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-primary">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Note</label>
                                <textarea name="note" rows="2" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-primary"></textarea>
                            </div>

                            <button type="submit" class="w-full bg-primary text-white py-2.5 rounded-xl font-medium hover:bg-blue-600 transition-colors shadow-lg shadow-blue-500/20">
                                Add Transaction
                            </button>
                        </form>
                    </div>
                </div>

                <!-- History Main -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="modern-card overflow-hidden">
                        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <i data-lucide="history" class="w-5 h-5 text-primary"></i>
                                <h2 class="font-bold text-lg text-slate-900">Recent Ledger</h2>
                            </div>
                            <button onclick="window.print()" class="p-2 text-slate-400 hover:text-primary transition-colors">
                                <i data-lucide="printer" class="w-5 h-5"></i>
                            </button>
                        </div>
                        <div class="table-container border-none rounded-none">
                            <table class="w-full">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Details</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php if (empty($transactions)): ?>
                                        <tr>
                                            <td colspan="3" class="px-8 py-12 text-center text-slate-400 italic">
                                                No transactions found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($transactions as $t): ?>
                                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600"><?php echo (new DateTime($t['transactionDate']))->format('m/d/Y'); ?></td>
                                                <td class="px-6 py-4">
                                                    <div class="flex flex-col">
                                                        <span class="inline-flex items-center w-fit px-2 py-0.5 rounded-md text-[10px] font-bold uppercase mb-1 <?php echo $t['type'] === 'income' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600'; ?>">
                                                            <?php echo htmlspecialchars($t['category']); ?>
                                                        </span>
                                                        <span class="font-medium text-slate-900"><?php echo htmlspecialchars($t['note'] ?: 'No note'); ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-right px-6 py-4">
                                                    <span class="font-bold <?php echo $t['type'] === 'income' ? 'text-green-600' : 'text-red-600'; ?>">
                                                        <?php echo $t['type'] === 'income' ? '+' : '-'; ?>$<?php echo number_format($t['amount'], 2); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
