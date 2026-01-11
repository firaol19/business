<?php
session_start();
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$productId = $_GET['id'] ?? 0;

if (!$productId) {
    header("Location: /business.php");
    exit;
}

// Handle Form Submissions (Add Cost, Update Product, Finish, Sell)
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_product') {
        $name = $_POST['name'] ?? '';
        $category = $_POST['category'] ?? '';
        $description = $_POST['description'] ?? '';
        $quantity = $_POST['quantity'] ?? 1;
        
        $stmt = $mysqli->prepare("UPDATE Product SET name = ?, category = ?, description = ?, quantity = ? WHERE id = ? AND userId = ?");
        $stmt->bind_param("sssiii", $name, $category, $description, $quantity, $productId, $userId);
        if ($stmt->execute()) {
            $message = "Product updated.";
        }
        $stmt->close();
    } elseif ($action === 'add_cost') {
        $costType = $_POST['costType'];
        $amount = $_POST['amount'];
        $description = $_POST['description'];
        $incurredDate = $_POST['incurredDate'];
        
        $stmt = $mysqli->prepare("INSERT INTO ProductCost (productId, costType, description, amount, incurredDate) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issds", $productId, $costType, $description, $amount, $incurredDate);
        if ($stmt->execute()) $message = "Cost added.";
        $stmt->close();
    } elseif ($action === 'finish_product') {
        $qty = $_POST['quantityToFinish'];
        $stmt = $mysqli->prepare("UPDATE Product SET quantityFinished = quantityFinished + ? WHERE id = ? AND userId = ?");
        $stmt->bind_param("iii", $qty, $productId, $userId);
        if ($stmt->execute()) {
             // Check if all finished, update status
             $stmt = $mysqli->prepare("UPDATE Product SET status = CASE WHEN quantityFinished >= quantity THEN 'finished' ELSE status END WHERE id = ?");
             $stmt->bind_param("i", $productId);
             $stmt->execute();
             $message = "Production progress updated.";
        }
        $stmt->close();
    } elseif ($action === 'sell_product') {
        $qty = $_POST['quantityToSell'];
        $price = $_POST['sellingPrice'];
        $client = $_POST['clientName'];
        $desc = $_POST['description'];
        
        // Add Sale
        $stmt = $mysqli->prepare("INSERT INTO ProductSale (productId, quantity, sellingPrice, clientName, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iidss", $productId, $qty, $price, $client, $desc);
        if ($stmt->execute()) {
            // Update Quantity Sold and Status
            $stmt = $mysqli->prepare("UPDATE Product SET quantitySold = quantitySold + ?, status = CASE WHEN quantitySold >= quantity THEN 'sold' ELSE status END WHERE id = ?");
            $stmt->bind_param("ii", $qty, $productId);
            $stmt->execute();
            $message = "Sale recorded.";
        }
        $stmt->close();
    }
}


// Fetch Product Details
$stmt = $mysqli->prepare("SELECT * FROM Product WHERE id = ? AND userId = ?");
$stmt->bind_param("ii", $productId, $userId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    echo "Product not found.";
    exit;
}

// Fetch Costs
$costs = [];
$stmt = $mysqli->prepare("SELECT * FROM ProductCost WHERE productId = ? ORDER BY incurredDate DESC");
$stmt->bind_param("i", $productId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $costs[] = $row;
$stmt->close();

// Fetch Sales
$sales = [];
$stmt = $mysqli->prepare("SELECT * FROM ProductSale WHERE productId = ? ORDER BY soldAt DESC");
$stmt->bind_param("i", $productId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $sales[] = $row;
$stmt->close();

// Calculations
$totalCost = array_reduce($costs, fn($sum, $c) => $sum + $c['amount'], 0);
$totalRevenue = array_reduce($sales, fn($sum, $s) => $sum + $s['sellingPrice'], 0);
$totalSoldQuantity = array_reduce($sales, fn($sum, $s) => $sum + $s['quantity'], 0);
$totalQuantity = $product['quantity'] ?: 1;
$unitCost = $totalQuantity > 0 ? $totalCost / $totalQuantity : 0;
$costOfGoodsSold = $unitCost * $totalSoldQuantity;
$profit = $totalRevenue - $costOfGoodsSold;
$margin = $totalRevenue > 0 ? ($profit / $totalRevenue) * 100 : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-background">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto w-full">
        <div class="p-8 space-y-8 animate-in pb-12">
            
            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-200 text-green-800 px-4 py-3 rounded relative">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start gap-6">
                <div class="flex-1">
                    <div class="flex items-center gap-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">
                        <i data-lucide="package" class="w-4 h-4"></i>
                        <?php echo htmlspecialchars($product['category'] ?: 'Woodworking Job'); ?>
                    </div>
                    <h1 class="text-4xl font-bold text-slate-900 tracking-tight"><?php echo htmlspecialchars($product['name']); ?></h1>
                    <p class="text-slate-500 mt-2 max-w-2xl"><?php echo htmlspecialchars($product['description'] ?: 'No description provided for this job.'); ?></p>

                    <div class="flex flex-wrap gap-4 mt-6">
                        <div class="flex items-center gap-2 text-sm font-medium text-slate-600 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                            <i data-lucide="clock" class="w-4 h-4"></i>
                            Started <?php echo (new DateTime($product['startedAt']))->format('m/d/Y'); ?>
                        </div>
                        <?php
                        $statusClass = 'bg-blue-100 text-blue-600 border-blue-200';
                        $dotClass = 'bg-blue-600';
                        if ($product['status'] === 'wip') {
                            $statusClass = 'bg-yellow-100 text-yellow-600 border-yellow-200';
                            $dotClass = 'bg-yellow-500 animate-pulse';
                        } elseif ($product['status'] === 'finished') {
                            $statusClass = 'bg-green-100 text-green-600 border-green-200';
                            $dotClass = 'bg-green-500';
                        }
                        ?>
                        <div class="flex items-center gap-2 text-sm font-medium px-3 py-1.5 rounded-lg border <?php echo $statusClass; ?>">
                            <div class="w-2 h-2 rounded-full <?php echo $dotClass; ?>"></div>
                            <?php echo strtoupper($product['status']); ?>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3 print:hidden">
                    <button onclick="window.print()" class="flex items-center gap-2 bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        <span class="font-medium text-sm">Print</span>
                    </button>
                    <a href="/business.php" class="flex items-center gap-2 bg-slate-900 text-white px-4 py-2 rounded-xl hover:bg-slate-800 transition-colors shadow-sm font-medium text-sm">
                        Back to List
                    </a>
                </div>
            </div>

            <!-- Financial Overview Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="modern-card p-6">
                    <div class="flex items-center gap-3 text-slate-500 mb-3">
                        <i data-lucide="dollar-sign" class="w-4 h-4"></i>
                        <span class="text-xs font-bold uppercase tracking-wider">Total Investment</span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900">$<?php echo number_format($totalCost, 2); ?></p>
                    <p class="text-xs text-slate-400 mt-1">$<?php echo number_format($unitCost, 2); ?> per unit</p>
                </div>

                <div class="modern-card p-6">
                    <div class="flex items-center gap-3 text-slate-500 mb-3">
                        <i data-lucide="trending-up" class="w-4 h-4"></i>
                        <span class="text-xs font-bold uppercase tracking-wider">Revenue</span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900">$<?php echo number_format($totalRevenue, 2); ?></p>
                    <p class="text-xs text-slate-400 mt-1"><?php echo $totalSoldQuantity; ?> of <?php echo $totalQuantity; ?> sold</p>
                </div>

                <div class="modern-card p-6 border-b-4 <?php echo $profit >= 0 ? 'border-green-500/50' : 'border-red-500/50'; ?>">
                    <div class="flex items-center gap-3 text-slate-500 mb-3">
                        <i data-lucide="trending-up" class="w-4 h-4"></i>
                        <span class="text-xs font-bold uppercase tracking-wider">Realized Profit</span>
                    </div>
                    <p class="text-2xl font-bold <?php echo $profit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        $<?php echo number_format($profit, 2); ?>
                    </p>
                    <p class="text-xs text-slate-400 mt-1"><?php echo number_format($margin, 1); ?>% net margin</p>
                </div>

                <div class="modern-card p-6">
                    <div class="flex items-center gap-3 text-slate-500 mb-3">
                        <i data-lucide="info" class="w-4 h-4"></i>
                        <span class="text-xs font-bold uppercase tracking-wider">Production</span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900"><?php echo $product['quantityFinished']; ?> / <?php echo $totalQuantity; ?></p>
                    <div class="w-full bg-slate-100 rounded-full h-1 mt-2 overflow-hidden">
                        <div class="bg-blue-600 h-full transition-all duration-500" style="width: <?php echo ($product['quantityFinished'] / $totalQuantity) * 100; ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions & Edit Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="space-y-6 lg:col-span-1 print:hidden">
                    <!-- Add Cost Card -->
                    <?php if ($product['status'] !== 'sold'): ?>
                        <div class="modern-card p-8 bg-gradient-to-br from-white to-slate-50/50">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="p-2 bg-blue-100 text-blue-600 rounded-lg">
                                    <i data-lucide="plus" class="w-5 h-5"></i>
                                </div>
                                <h3 class="text-lg font-bold text-slate-900">Add Material/Labor Cost</h3>
                            </div>
                            <form method="POST" action="" class="space-y-5">
                                <input type="hidden" name="action" value="add_cost">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Type</label>
                                    <select name="costType" required class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-blue-500">
                                        <option value="material">Material</option>
                                        <option value="labor">Labor</option>
                                        <option value="transport">Transport</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Amount</label>
                                    <div class="relative">
                                        <input type="number" step="0.01" name="amount" required class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-blue-500" placeholder="0.00" />
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Description</label>
                                    <input type="text" name="description" placeholder="e.g. Oak wood 5m" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-blue-500" />
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Date</label>
                                    <input type="date" name="incurredDate" required value="<?php echo date('Y-m-d'); ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-blue-500" />
                                </div>
                                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/10 transition-all">
                                    Add Cost
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Production Mark Finished -->
                    <?php if ($product['quantityFinished'] < $totalQuantity): ?>
                        <div class="modern-card p-8 border-l-4 border-l-green-500">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="p-2 bg-green-100 text-green-600 rounded-lg">
                                    <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                                </div>
                                <h3 class="text-lg font-bold text-slate-900">Production Progress</h3>
                            </div>
                            <form method="POST" action="" class="space-y-4">
                                <input type="hidden" name="action" value="finish_product">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Units Finished Now</label>
                                    <div class="flex gap-2">
                                        <input type="number" name="quantityToFinish" min="1" max="<?php echo $totalQuantity - $product['quantityFinished']; ?>" value="1" class="flex-1 border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-green-500" />
                                        <button type="submit" class="bg-green-600 text-white font-bold px-6 rounded-xl hover:bg-green-700 transition-all">
                                            Finish
                                        </button>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-2 italic text-center">
                                        <?php echo $totalQuantity - $product['quantityFinished']; ?> units remaining
                                    </p>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Sales Record Sale -->
                    <?php if ($product['quantityFinished'] > $product['quantitySold']): ?>
                        <div class="modern-card p-8 border-l-4 border-l-blue-600 shadow-xl shadow-blue-600/5">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                                    <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                                </div>
                                <h3 class="text-lg font-bold text-slate-900">Record a Sale</h3>
                            </div>
                            <form method="POST" action="" class="space-y-5">
                                <input type="hidden" name="action" value="sell_product">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Quantity to Sell</label>
                                    <input type="number" name="quantityToSell" min="1" max="<?php echo $product['quantityFinished'] - $product['quantitySold']; ?>" value="1" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-blue-500" />
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Sell Price (Total)</label>
                                    <div class="relative">
                                        <input type="number" step="0.01" name="sellingPrice" required class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-blue-500" />
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Client Name</label>
                                    <input type="text" name="clientName" placeholder="Customer Name" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-blue-500" />
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Description</label>
                                    <input type="text" name="description" placeholder="Notes about sale..." class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-blue-500" />
                                </div>
                                <button type="submit" class="w-full bg-slate-900 text-white font-bold py-3 rounded-xl hover:bg-slate-800 transition-all">
                                    Record Sale
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Edit Details Sidebar Toggle -->
                    <div class="modern-card p-6">
                        <details class="group">
                            <summary class="flex items-center justify-between cursor-pointer list-none">
                                <div class="flex items-center gap-3">
                                    <i data-lucide="edit-2" class="w-4 h-4 text-slate-400"></i>
                                    <span class="font-bold text-slate-700">Update Job Details</span>
                                </div>
                                <span class="group-open:rotate-180 transition-transform text-slate-400">▼</span>
                            </summary>
                            <form method="POST" action="" class="mt-6 space-y-4">
                                <input type="hidden" name="action" value="update_product">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Job Name</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-blue-500 text-sm" />
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Category</label>
                                    <input type="text" name="category" value="<?php echo htmlspecialchars($product['category']); ?>" placeholder="e.g. Kitchen, Office" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-blue-500 text-sm" />
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Description</label>
                                    <textarea name="description" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-blue-500 text-sm min-h-[80px]" placeholder="Job details..."><?php echo htmlspecialchars($product['description']); ?></textarea>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase">Total Target Quantity</label>
                                    <input type="number" name="quantity" min="1" value="<?php echo $product['quantity']; ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-blue-500 text-sm" />
                                </div>
                                <button type="submit" class="w-full bg-slate-100 text-slate-700 font-bold py-2.5 rounded-xl hover:bg-slate-200 transition-all text-sm">
                                    Save Changes
                                </button>
                            </form>
                        </details>
                    </div>
                </div>

                <!-- Main History Area -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Cost History -->
                    <div class="modern-card overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                            <h3 class="font-bold text-slate-900">Cost History</h3>
                        </div>
                        <div class="table-container border-none rounded-none">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th class="px-6 py-3 font-bold uppercase text-xs">Date</th>
                                        <th class="px-6 py-3 font-bold uppercase text-xs">Type</th>
                                        <th class="px-6 py-3 font-bold uppercase text-xs">Description</th>
                                        <th class="px-6 py-3 font-bold uppercase text-xs text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                     <?php if (empty($costs)): ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-8 text-center text-slate-500 italic">No costs recorded yet.</td>
                                        </tr>
                                     <?php else: ?>
                                        <?php foreach ($costs as $c): ?>
                                            <tr class="hover:bg-slate-50 transition-colors">
                                                <td class="px-6 py-4 text-slate-500 whitespace-nowrap">
                                                    <?php echo (new DateTime($c['incurredDate']))->format('M j, Y'); ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold uppercase bg-slate-100 text-slate-600 border border-slate-200">
                                                        <?php echo htmlspecialchars($c['costType']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-slate-900 font-medium">
                                                    <?php echo htmlspecialchars($c['description']); ?>
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    <span class="font-bold text-slate-900">-$<?php echo number_format($c['amount'], 2); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                     <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Sales History Table -->
                    <?php if (!empty($sales)): ?>
                        <div class="modern-card overflow-hidden">
                             <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                                <h3 class="font-bold text-slate-900">Sales History</h3>
                            </div>
                            <div class="table-container border-none rounded-none">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-slate-50 text-slate-500">
                                        <tr>
                                            <th class="px-6 py-3 font-bold uppercase text-xs">Date</th>
                                            <th class="px-6 py-3 font-bold uppercase text-xs">Client</th>
                                            <th class="px-6 py-3 font-bold uppercase text-xs">Description</th>
                                            <th class="px-6 py-3 font-bold uppercase text-xs text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <?php foreach ($sales as $s): ?>
                                            <tr class="hover:bg-slate-50 transition-colors">
                                                <td class="px-6 py-4 text-slate-500 whitespace-nowrap">
                                                    <?php echo (new DateTime($s['soldAt']))->format('M j, Y'); ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex flex-col">
                                                        <span class="font-bold text-slate-900"><?php echo htmlspecialchars($s['clientName']); ?></span>
                                                        <span class="text-xs text-slate-400"><?php echo $s['quantity']; ?> units</span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-slate-600">
                                                    <?php echo htmlspecialchars($s['description'] ?: '-'); ?>
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    <span class="font-bold text-green-600">+$<?php echo number_format($s['sellingPrice'], 2); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
