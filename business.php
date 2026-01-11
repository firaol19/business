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

// Handle Start Job
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $category = $_POST['category'] ?? 'General';
    $startedAt = $_POST['startedAt'] ?? date('Y-m-d H:i:s');
    
    if (!empty($name)) {
        $stmt = $mysqli->prepare("INSERT INTO Product (userId, name, category, startedAt, status) VALUES (?, ?, ?, ?, 'wip')");
        $stmt->bind_param("isss", $userId, $name, $category, $startedAt);
        if ($stmt->execute()) {
            header("Location: /business.php");
            exit;
        } else {
            $message = "Error starting job.";
        }
        $stmt->close();
    }
}

// Fetch Products
$products = [];
$stmt = $mysqli->prepare("SELECT * FROM Product WHERE userId = ? ORDER BY startedAt DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['costs'] = [];
    $row['sales'] = [];
    $products[$row['id']] = $row;
}
$stmt->close();

if (!empty($products)) {
    $productIds = implode(',', array_keys($products));
    
    // Fetch Costs
    $costResult = $mysqli->query("SELECT * FROM ProductCost WHERE productId IN ($productIds)");
    while ($row = $costResult->fetch_assoc()) {
        $products[$row['productId']]['costs'][] = $row;
    }
    
    // Fetch Sales
    $saleResult = $mysqli->query("SELECT * FROM ProductSale WHERE productId IN ($productIds)");
    while ($row = $saleResult->fetch_assoc()) {
        $products[$row['productId']]['sales'][] = $row;
    }
}

$wipProducts = array_filter($products, fn($p) => $p['status'] === 'wip');
$finishedProducts = array_filter($products, fn($p) => $p['status'] === 'finished');
$soldProducts = array_filter($products, fn($p) => $p['status'] === 'sold');

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-background">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <main class="flex-1 overflow-y-auto w-full">
        <div class="p-8 space-y-10 animate-in">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Woodworking Business</h1>
                    <p class="text-slate-500 mt-1">Manage your active jobs, inventory, and sales.</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="window.print()" class="flex items-center gap-2 bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        <span class="font-medium text-sm">Print</span>
                    </button>
                </div>
            </div>

            <!-- Start Job Form (Simplified inline) -->
            <div class="modern-card p-6 border border-blue-100 bg-blue-50/30">
                <form method="POST" action="" class="flex flex-col md:flex-row gap-4 items-end">
                    <div class="flex-1 w-full">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Job Name</label>
                        <input type="text" name="name" required placeholder="Ex: Walnut Coffee Table" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-primary bg-white">
                    </div>
                    <div class="w-full md:w-48">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                        <select name="category" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 outline-none focus:border-primary bg-white">
                            <option value="Furniture">Furniture</option>
                            <option value="Decor">Decor</option>
                            <option value="Kitchen">Kitchen</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-primary text-white px-6 py-2.5 rounded-xl font-medium hover:bg-blue-600 transition-colors shadow-lg shadow-blue-500/20 whitespace-nowrap">
                        Start Job
                    </button>
                </form>
            </div>

            <!-- WIP Section -->
            <section class="space-y-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center">
                        <div class="w-2 h-8 bg-yellow-500 rounded-full mr-3"></div>
                        Work In Progress
                        <span class="ml-3 text-sm font-medium text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full"><?php echo count($wipProducts); ?></span>
                    </h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($wipProducts)): ?>
                        <div class="col-span-full py-12 text-center modern-card border-dashed">
                            <i data-lucide="briefcase" class="w-12 h-12 text-slate-200 mx-auto mb-3"></i>
                            <p class="text-slate-400 font-medium italic">No active jobs at the moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($wipProducts as $product): ?>
                            <?php 
                                $totalCost = array_reduce($product['costs'], fn($sum, $c) => $sum + $c['amount'], 0);
                                $finished = $product['quantityFinished'] ?? 0;
                                $qty = $product['quantity'] ?: 1;
                                $progress = ($finished / $qty) * 100;
                            ?>
                            <a href="/business/detail.php?id=<?php echo $product['id']; ?>" class="group block h-full">
                                <div class="modern-card p-6 h-full flex flex-col hover:border-yellow-500/50 transition-all border-l-4 border-l-yellow-500">
                                    <div class="flex justify-between items-start mb-4">
                                        <div>
                                            <h3 class="font-bold text-lg text-slate-900 group-hover:text-primary transition-colors"><?php echo htmlspecialchars($product['name']); ?></h3>
                                            <div class="flex items-center gap-1.5 text-xs text-slate-500 mt-1">
                                                <i data-lucide="package" class="w-3 h-3"></i>
                                                <?php echo htmlspecialchars($product['category'] ?: 'General'); ?>
                                            </div>
                                        </div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider bg-yellow-100 text-yellow-600 px-2 py-1 rounded-md">WIP</span>
                                    </div>

                                    <div class="space-y-4 flex-1">
                                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                            <div class="flex justify-between text-xs font-bold text-slate-500 mb-2">
                                                <span>PROGRESS</span>
                                                <span><?php echo $finished; ?>/<?php echo $qty; ?></span>
                                            </div>
                                            <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                                                <div class="bg-yellow-500 h-full transition-all duration-500" style="width: <?php echo $progress; ?>%"></div>
                                            </div>
                                        </div>

                                        <div class="flex justify-between items-end">
                                            <div class="space-y-1">
                                                <p class="text-[10px] font-bold text-slate-400 uppercase">Investment</p>
                                                <p class="text-lg font-bold text-slate-900">$<?php echo number_format($totalCost, 2); ?></p>
                                            </div>
                                            <div class="flex items-center gap-1 text-[10px] text-slate-400 font-medium">
                                                <i data-lucide="calendar" class="w-3 h-3"></i>
                                                <?php echo (new DateTime($product['startedAt']))->format('m/d/Y'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                <!-- Inventory -->
                <section class="space-y-6">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center">
                        <div class="w-2 h-8 bg-green-500 rounded-full mr-3"></div>
                        Inventory
                        <span class="ml-3 text-sm font-medium text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full"><?php echo count($finishedProducts); ?></span>
                    </h2>
                    <div class="space-y-4">
                        <?php foreach ($finishedProducts as $product): ?>
                            <?php $totalCost = array_reduce($product['costs'], fn($sum, $c) => $sum + $c['amount'], 0); ?>
                            <a href="/business/detail.php?id=<?php echo $product['id']; ?>" class="block group">
                                <div class="modern-card p-4 flex items-center justify-between hover:bg-green-50/50 transition-all">
                                    <div class="flex items-center gap-4">
                                        <div class="p-2 bg-green-100 text-green-600 rounded-lg">
                                            <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-slate-900 line-clamp-1"><?php echo htmlspecialchars($product['name']); ?></h3>
                                            <p class="text-xs text-slate-500">Ready to sell</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-slate-900">$<?php echo number_format($totalCost, 2); ?></p>
                                        <p class="text-[10px] text-slate-400 uppercase font-bold">Total Cost</p>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Sold -->
                <section class="space-y-6">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center">
                        <div class="w-2 h-8 bg-blue-500 rounded-full mr-3"></div>
                        Recent Sales
                        <span class="ml-3 text-sm font-medium text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full"><?php echo count($soldProducts); ?></span>
                    </h2>
                    <div class="modern-card overflow-hidden">
                        <div class="table-container border-none rounded-none">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th class="px-6 py-3 font-bold uppercase text-xs">Product</th>
                                        <th class="px-6 py-3 font-bold uppercase text-xs">Sold Date</th>
                                        <th class="px-6 py-3 font-bold uppercase text-xs text-right">Net Profit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php if (empty($soldProducts)): ?>
                                        <tr>
                                            <td colspan="3" class="px-6 py-8 text-center text-slate-400 italic">No sales yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($soldProducts as $product): ?>
                                            <?php 
                                                $totalRev = array_reduce($product['sales'], fn($sum, $s) => $sum + $s['sellingPrice'], 0);
                                                $totalCost = array_reduce($product['costs'], fn($sum, $c) => $sum + $c['amount'], 0);
                                                $profit = $totalRev - $totalCost;
                                                
                                                // Determine Sold Date from sales records
                                                $soldDate = '-';
                                                if (!empty($product['sales'])) {
                                                    $dates = array_map(fn($s) => $s['soldAt'], $product['sales']);
                                                    rsort($dates); // Sort descending
                                                    $soldDate = (new DateTime($dates[0]))->format('m/d/Y');
                                                } elseif ($product['status'] === 'sold' && $product['updatedAt']) {
                                                     // Fallback if no sales record but marked sold (legacy/edge case)
                                                     $soldDate = (new DateTime($product['updatedAt']))->format('m/d/Y');
                                                }
                                            ?>
                                            <tr class="hover:bg-slate-50 transition-colors">
                                                <td class="px-6 py-4">
                                                    <a href="/business/detail.php?id=<?php echo $product['id']; ?>" class="flex items-center gap-3 group">
                                                        <div class="p-2 bg-blue-100 text-blue-600 rounded-lg group-hover:bg-blue-200 transition-colors">
                                                            <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                                                        </div>
                                                        <span class="font-medium text-slate-900 group-hover:text-blue-600 transition-colors"><?php echo htmlspecialchars($product['name']); ?></span>
                                                    </a>
                                                </td>
                                                <td class="px-6 py-4 text-slate-500">
                                                    <div class="flex items-center gap-2">
                                                        <i data-lucide="calendar" class="w-3 h-3 text-slate-400"></i>
                                                        <?php echo $soldDate; ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    <span class="font-bold <?php echo $profit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                        <?php echo $profit >= 0 ? '+' : ''; ?>$<?php echo number_format($profit, 2); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
