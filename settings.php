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
$msgType = 'success';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = $_POST['name'] ?? '';
        $currency = $_POST['currencyCode'] ?? 'ETB';
        
        $stmt = $mysqli->prepare("UPDATE User SET name = ?, currencyCode = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $currency, $userId);
        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
            $_SESSION['user_name'] = $name; // Update session
        } else {
            $message = "Failed to update profile.";
            $msgType = 'error';
        }
        $stmt->close();
    } elseif ($action === 'change_password') {
        $current = $_POST['currentPassword'];
        $new = $_POST['newPassword'];
        $confirm = $_POST['confirmPassword'];
        
        if ($new !== $confirm) {
            $message = "New passwords do not match.";
            $msgType = 'error';
        } else {
            $stmt = $mysqli->prepare("SELECT password FROM User WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (password_verify($current, $res['password'] ?? '')) {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $mysqli->prepare("UPDATE User SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hash, $userId);
                if ($stmt->execute()) {
                    $message = "Password changed successfully.";
                } else {
                    $message = "Error updating password.";
                    $msgType = 'error';
                }
                $stmt->close();
            } else {
                $message = "Incorrect current password.";
                $msgType = 'error';
            }
        }
    }
}

// Fetch User Profile
$stmt = $mysqli->prepare("SELECT name, email, currencyCode, createdAt FROM User WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch Counts
$stats = [];
function getCount($mysqli, $table, $userId) {
    if ($table === 'Product') {
        $sql = "SELECT COUNT(*) as c FROM $table WHERE userId = ?";
    } else {
        $sql = "SELECT COUNT(*) as c FROM $table WHERE userId = ?";
    }
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $res;
}

$stats['transactions'] = getCount($mysqli, 'PersonalTransaction', $userId);
$stats['products'] = getCount($mysqli, 'Product', $userId);
$stats['businessExpenses'] = getCount($mysqli, 'BusinessExpense', $userId);
$stats['savingGoals'] = getCount($mysqli, 'SavingGoal', $userId);

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-background">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto w-full">
        <div class="p-8 space-y-8 animate-in pb-12">
            <div class="flex items-center gap-3">
                <i data-lucide="settings" class="w-8 h-8 text-slate-900"></i>
                <h1 class="text-3xl font-bold text-slate-900">Settings</h1>
            </div>

            <?php if ($message): ?>
                <div class="p-4 rounded-lg <?php echo $msgType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Account Statistics -->
            <div class="modern-card p-6 bg-white">
                <div class="flex items-center gap-3 mb-4">
                    <i data-lucide="database" class="w-6 h-6 text-blue-600"></i>
                    <h2 class="text-xl font-bold text-slate-900">Account Statistics</h2>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm text-blue-600 font-medium">Personal Transactions</p>
                        <p class="text-2xl font-bold text-blue-900 mt-1"><?php echo $stats['transactions']; ?></p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <p class="text-sm text-green-600 font-medium">Products/Jobs</p>
                        <p class="text-2xl font-bold text-green-900 mt-1"><?php echo $stats['products']; ?></p>
                    </div>
                    <div class="bg-orange-50 p-4 rounded-lg">
                        <p class="text-sm text-orange-600 font-medium">Business Expenses</p>
                        <p class="text-2xl font-bold text-orange-900 mt-1"><?php echo $stats['businessExpenses']; ?></p>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <p class="text-sm text-purple-600 font-medium">Saving Goals</p>
                        <p class="text-2xl font-bold text-purple-900 mt-1"><?php echo $stats['savingGoals']; ?></p>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <p class="text-sm text-slate-500">
                        Member since: <?php echo (new DateTime($user['createdAt']))->format('F j, Y'); ?>
                    </p>
                </div>
            </div>

            <!-- Profile Settings -->
            <div class="modern-card p-6 bg-white">
                <div class="flex items-center gap-3 mb-6">
                    <i data-lucide="user" class="w-6 h-6 text-blue-600"></i>
                    <h2 class="text-xl font-bold text-slate-900">Profile Information</h2>
                </div>

                <form method="POST" action="" class="space-y-6">
                    <input type="hidden" name="action" value="update_profile">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Full Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required class="w-full border border-slate-300 rounded-lg px-4 py-2 text-slate-900 focus:ring-2 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Email Address</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled class="w-full border border-slate-300 rounded-lg px-4 py-2 text-slate-500 bg-slate-50 cursor-not-allowed" />
                        <p class="text-xs text-slate-500 mt-1">Email cannot be changed</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Currency Preference</label>
                        <select name="currencyCode" class="w-full border border-slate-300 rounded-lg px-4 py-2 text-slate-900 focus:ring-2 focus:ring-blue-500">
                            <?php 
                            $currencies = [
                                'ETB' => 'Ethiopian Birr (ETB)',
                                'USD' => 'US Dollar (USD)',
                                'EUR' => 'Euro (EUR)',
                                'GBP' => 'British Pound (GBP)',
                                'KES' => 'Kenyan Shilling (KES)',
                                'TZS' => 'Tanzanian Shilling (TZS)'
                            ];
                            foreach ($currencies as $code => $name):
                            ?>
                                <option value="<?php echo $code; ?>" <?php echo $user['currencyCode'] === $code ? 'selected' : ''; ?>><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                        Save Changes
                    </button>
                </form>
            </div>

            <!-- Security Settings -->
            <div class="modern-card p-6 bg-white">
                <div class="flex items-center gap-3 mb-6">
                    <i data-lucide="lock" class="w-6 h-6 text-blue-600"></i>
                    <h2 class="text-xl font-bold text-slate-900">Security Settings</h2>
                </div>

                <form method="POST" action="" class="space-y-6">
                    <input type="hidden" name="action" value="change_password">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Current Password</label>
                        <input type="password" name="currentPassword" required class="w-full border border-slate-300 rounded-lg px-4 py-2 text-slate-900 focus:ring-2 focus:ring-blue-500" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">New Password</label>
                            <input type="password" name="newPassword" required class="w-full border border-slate-300 rounded-lg px-4 py-2 text-slate-900 focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Confirm New Password</label>
                            <input type="password" name="confirmPassword" required class="w-full border border-slate-300 rounded-lg px-4 py-2 text-slate-900 focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>

                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                        Update Password
                    </button>
                </form>
            </div>

            <!-- Data Export -->
            <div class="modern-card p-6 bg-white">
                <div class="flex items-center gap-3 mb-6">
                    <i data-lucide="download" class="w-6 h-6 text-blue-600"></i>
                    <h2 class="text-xl font-bold text-slate-900">Export Data</h2>
                </div>

                <p class="text-slate-600 mb-6">
                    Download your financial data in CSV format for backup or analysis in external tools.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <a href="export.php?type=transactions" class="flex items-center justify-center gap-2 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors font-medium">
                        <i data-lucide="download" class="w-5 h-5"></i>
                        Export Personal Transactions
                    </a>

                    <a href="export.php?type=business" class="flex items-center justify-center gap-2 bg-orange-600 text-white px-6 py-3 rounded-lg hover:bg-orange-700 transition-colors font-medium">
                        <i data-lucide="download" class="w-5 h-5"></i>
                        Export Business Expenses
                    </a>

                    <a href="export.php?type=products" class="flex items-center justify-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                        <i data-lucide="download" class="w-5 h-5"></i>
                        Export Products & Costs
                    </a>

                    <a href="export.php?type=all" class="flex items-center justify-center gap-2 bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition-colors font-medium">
                        <i data-lucide="download" class="w-5 h-5"></i>
                        Export All Data
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
