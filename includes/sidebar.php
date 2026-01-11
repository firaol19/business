<?php
$current_page = $_SERVER['REQUEST_URI'];

$navItems = [
    ['name' => 'Dashboard', 'href' => '/dashboard.php', 'icon' => 'home'],
    ['name' => 'Personal Finance', 'href' => '/personal.php', 'icon' => 'dollar-sign'],
    ['name' => 'Business / Jobs', 'href' => '/business.php', 'icon' => 'briefcase'],
    ['name' => 'Business Expenses', 'href' => '/business/expenses.php', 'icon' => 'receipt'],
    ['name' => 'History', 'href' => '/history.php', 'icon' => 'history'],
    ['name' => 'AI Analysis', 'href' => '/analysis.php', 'icon' => 'pie-chart'],
    ['name' => 'Settings', 'href' => '/settings.php', 'icon' => 'settings'],
];

function isNavActive($href, $current) {
    if ($href === '/dashboard.php' && $current === '/dashboard.php') return true;
    if ($href !== '/dashboard.php' && strpos($current, $href) === 0) return true;
    return false;
}
?>

<!-- Mobile Menu Button -->
<button
    onclick="toggleSidebar()"
    class="lg:hidden fixed top-4 left-4 z-50 p-2.5 bg-slate-900 text-white rounded-xl shadow-xl transition-transform active:scale-95 print:hidden"
    aria-label="Toggle menu"
>
    <i data-lucide="menu" id="menuIcon" class="w-6 h-6"></i>
</button>

<!-- Overlay -->
<div
    id="sidebarOverlay"
    class="hidden lg:hidden fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-30 transition-opacity print:hidden"
    onclick="closeSidebar()"
></div>

<!-- Sidebar -->
<aside
    id="sidebar"
    class="fixed lg:static inset-y-0 left-0 z-40 flex flex-col w-72 bg-slate-900 text-slate-300 h-screen border-r border-slate-800 transition-transform duration-300 ease-in-out print:hidden -translate-x-full lg:translate-x-0"
>
    <div class="p-8">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center shadow-lg shadow-primary/20">
                <i data-lucide="briefcase" class="w-6 h-6 text-white"></i>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">FinAI</h1>
        </div>
    </div>

    <nav class="flex-1 px-4 space-y-1 overflow-y-auto">
        <?php foreach ($navItems as $item): ?>
            <?php $active = isNavActive($item['href'], $current_page); ?>
            <a
                href="<?php echo $item['href']; ?>"
                onclick="closeSidebar()"
                class="group flex items-center justify-between px-4 py-3.5 rounded-xl transition-all duration-200 <?php echo $active ? 'bg-primary text-white shadow-lg shadow-primary/20' : 'hover:bg-slate-800 hover:text-white'; ?>"
            >
                <div class="flex items-center">
                    <i data-lucide="<?php echo $item['icon']; ?>" class="w-5 h-5 mr-3.5 transition-colors <?php echo $active ? 'text-white' : 'text-slate-500 group-hover:text-primary'; ?>"></i>
                    <span class="font-medium"><?php echo $item['name']; ?></span>
                </div>
                <?php if ($active): ?>
                    <i data-lucide="chevron-right" class="w-4 h-4 opacity-70"></i>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="p-4 border-t border-slate-800/50">
        <div class="bg-slate-800/40 p-4 rounded-2xl border border-slate-800/50 flex items-center hover:bg-slate-800/60 transition-colors cursor-pointer group">
            <div class="relative">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-primary to-accent flex items-center justify-center text-white font-bold">
                    U
                </div>
                <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 border-2 border-slate-900 rounded-full"></div>
            </div>
            <div class="ml-3 flex-1">
                <p class="text-sm font-bold text-white group-hover:text-primary transition-colors">Admin User</p>
                <p class="text-xs text-slate-500">Free Account</p>
            </div>
            <i data-lucide="settings" class="w-4 h-4 text-slate-600 group-hover:text-slate-400"></i>
        </div>
    </div>
</aside>

<script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const menuIcon = document.getElementById('menuIcon');

    function toggleSidebar() {
        const isClosed = sidebar.classList.contains('-translate-x-full');
        if (isClosed) {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            // Change icon to X (handled by Lucide, but we need to re-render or toggle class if raw SVG)
            // Since we use data-lucide, we can't easily swap icon content without re-running createIcons or swapping DOM.
            // Simplified: just toggle visibility.
        } else {
            closeSidebar();
        }
    }

    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    }
</script>
