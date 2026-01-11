<?php
session_start();
require_once __DIR__ . '/../database/db.php';
// Adjust include path since we are in business/ folder
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$message = null;

// Handle Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $description = $_POST['description'] ?? '';
    $expenseDate = $_POST['expenseDate'] ?? date('Y-m-d');
    $paymentMethod = $_POST['paymentMethod'] ?? 'Cash';
    $note = $_POST['note'] ?? '';

    if ($amount > 0 && !empty($category)) {
        $stmt = $mysqli->prepare("INSERT INTO BusinessExpense (userId, category, description, amount, expenseDate, paymentMethod, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
        // ssdsss -> integer, string, string, double, string, string, string ??
        // Schema: userId(i), category(s), description(s), amount(d), expenseDate(s), paymentMethod(s), note(s)
        $stmt->bind_param("issdsss", $userId, $category, $description, $amount, $expenseDate, $paymentMethod, $note);
        
        if ($stmt->execute()) {
            header("Location: /business/expenses.php");
            exit;
        } else {
            $message = "Error adding expense.";
        }
        $stmt->close();
    }
}

// Fetch Expenses
$expenses = [];
$stmt = $mysqli->prepare("SELECT * FROM BusinessExpense WHERE userId = ? ORDER BY expenseDate DESC LIMIT 50");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
}
$stmt->close();

// Calculate Totals (All Time)
$stmt = $mysqli->prepare("SELECT SUM(amount) as total FROM BusinessExpense WHERE userId = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalBurn = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Monthly Totals
$firstDayOfMonth = date('Y-m-01');
$stmt = $mysqli->prepare("SELECT SUM(amount) as total FROM BusinessExpense WHERE userId = ? AND expenseDate >= ?");
$stmt->bind_param("is", $userId, $firstDayOfMonth);
$stmt->execute();
$monthlyBurn = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Active Categories
$activeCategories = count(array_unique(array_column($expenses, 'category')));

// Include layout with adjusted paths for CSS/JS if they process root-relative checks
// Header usually uses /public/css/style.css which is absolute path, so it works.
// However `require_once '../../includes/header.php'` will output HTML.
// Inside header.php: `<link rel="stylesheet" href="/public/css/style.css">` -> Correct.
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-background">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="flex-1 overflow-y-auto w-full">
        <div class="p-8 space-y-8 animate-in">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-4xl font-bold text-slate-900 tracking-tight">Business Expenses</h1>
                    <p class="text-slate-500 mt-1">Manage and track your general operating costs.</p>
                </div>
                <button onclick="window.print()" class="flex items-center gap-2 bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span class="font-medium text-sm">Print</span>
                </button>
            </div>

            <!-- Summary Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 print:grid-cols-2">
                <div class="modern-card p-6 border-l-4 border-l-red-500">
                    <div class="flex items-center gap-3 text-slate-500 mb-3">
                        <i data-lucide="dollar-sign" class="w-4 h-4"></i>
                        <span class="text-xs font-bold uppercase tracking-wider">Total Burn (All Time)</span>
                    </div>
                    <p class="text-3xl font-bold text-slate-900">$<?php echo number_format($totalBurn, 2); ?></p>
                </div>

                <div class="modern-card p-6 border-l-4 border-l-blue-500">
                    <div class="flex items-center gap-3 text-slate-500 mb-3">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                        <span class="text-xs font-bold uppercase tracking-wider">Month to Date</span>
                    </div>
                    <p class="text-3xl font-bold text-slate-900">$<?php echo number_format($monthlyBurn, 2); ?></p>
                </div>

                <div class="modern-card p-6 bg-slate-900 text-white lg:block hidden">
                    <div class="flex items-center gap-3 text-slate-400 mb-3">
                        <i data-lucide="receipt" class="w-4 h-4"></i>
                        <span class="text-xs font-bold uppercase tracking-wider">Active Categories</span>
                    </div>
                    <p class="text-3xl font-bold"><?php echo $activeCategories; ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                <!-- Add Expense Sidebar -->
                <div class="lg:col-span-1 print:hidden lg:sticky lg:top-8">
                    <div class="modern-card p-8 bg-gradient-to-br from-white to-slate-50/50">
                        <div class="flex items-center gap-3 mb-8">
                            <div class="p-2 bg-red-100 text-red-600 rounded-lg">
                                <i data-lucide="plus" class="w-5 h-5"></i>
                            </div>
                            <h3 className="text-xl font-bold text-slate-900">Log Expense</h3>
                        </div>

                        <form method="POST" action="" class="space-y-5">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase flex items-center gap-2">
                                    <i data-lucide="tag" class="w-3 h-3"></i> Category
                                </label>
                                <select name="category" required class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-primary">
                                    <option value="">Select category...</option>
                                    <option value="Rent">Rent</option>
                                    <option value="Utilities">Utilities</option>
                                    <option value="Tools">Tools & Equipment</option>
                                    <option value="Marketing">Marketing</option>
                                    <option value="Insurance">Insurance</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Office Supplies">Office Supplies</option>
                                    <option value="Internet">Internet & Phone</option>
                                    <option value="Salaries">Salaries & Wages</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase flex items-center gap-2">
                                    <i data-lucide="dollar-sign" class="w-3 h-3"></i> Amount
                                </label>
                                <div class="relative">
                                    <input type="number" step="0.01" name="amount" required placeholder="0.00" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-primary">
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase flex items-center gap-2">
                                    <i data-lucide="history" class="w-3 h-3"></i> Description
                                </label>
                                <input type="text" name="description" placeholder="e.g. Monthly workshop rent" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-primary">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase flex items-center gap-2">
                                        <i data-lucide="calendar" class="w-3 h-3"></i> Date
                                    </label>
                                    <input type="date" name="expenseDate" required value="<?php echo date('Y-m-d'); ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-primary text-sm">
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase flex items-center gap-2">
                                        <i data-lucide="credit-card" class="w-3 h-3"></i> Method
                                    </label>
                                    <select name="paymentMethod" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-primary text-sm">
                                        <option value="Cash">Cash</option>
                                        <option value="Bank Transfer">Bank</option>
                                        <option value="Mobile Money">Mobile</option>
                                        <option value="Credit Card">Credit</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase">Internal Note</label>
                                <textarea name="note" rows="2" placeholder="Optional notes..." class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-primary text-sm"></textarea>
                            </div>

                            <button type="submit" class="w-full bg-red-500 text-white font-bold py-3.5 rounded-xl hover:bg-red-700 shadow-xl shadow-red-500/20 transition-all transform active:scale-[0.98]">
                                Add Expense Record
                            </button>
                        </form>
                    </div>
                </div>

                <!-- History Main -->
                <div class="lg:col-span-2">
                    <div class="modern-card overflow-hidden">
                        <div class="table-container border-none rounded-none">
                            <table class="w-full">
                                <thead class="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-left border-b border-slate-100">Date/Desc</th>
                                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-left border-b border-slate-100">Category</th>
                                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-right border-b border-slate-100">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 bg-white">
                                    <?php if (empty($expenses)): ?>
                                        <tr>
                                            <td colspan="3" class="px-8 py-12 text-center text-slate-400 italic">
                                                No expenses found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($expenses as $e): ?>
                                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                                <td class="px-6 py-4">
                                                    <div class="flex flex-col">
                                                        <span class="font-bold text-slate-900"><?php echo htmlspecialchars($e['description'] ?: 'Expense'); ?></span>
                                                        <span class="text-xs text-slate-400"><?php echo (new DateTime($e['expenseDate']))->format('M j, Y'); ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800 border border-slate-200">
                                                        <?php echo htmlspecialchars($e['category']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-right px-6 py-4">
                                                    <span class="font-bold text-red-600 block">
                                                        -$<?php echo number_format($e['amount'], 2); ?>
                                                    </span>
                                                    <span class="text-xs text-slate-400"><?php echo htmlspecialchars($e['paymentMethod']); ?></span>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
