<?php
session_start();
require_once __DIR__ . '/includes/header.php';

$isLoggedIn = isset($_SESSION['user_id']);
?>

<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
    <!-- Header -->
    <header class="border-b border-gray-200 bg-white/80 backdrop-blur-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                    <span class="text-white font-bold text-xl">F</span>
                </div>
                <h1 class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                    FinAI
                </h1>
            </div>
            <div class="flex items-center gap-4">
                <?php if ($isLoggedIn): ?>
                    <a href="/dashboard.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Go to Dashboard
                    </a>
                <?php else: ?>
                    <a href="/login.php" class="text-gray-600 hover:text-gray-900 font-medium">
                        Sign In
                    </a>
                    <a href="/register.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Get Started
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-20">
        <div class="text-center space-y-6 sm:space-y-8">
            <div class="space-y-4">
                <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold text-gray-900 leading-tight">
                    AI-Powered Financial
                    <br />
                    <span class="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                        Management System
                    </span>
                </h1>
                <p class="text-lg sm:text-xl text-gray-600 max-w-2xl mx-auto px-4">
                    Track your personal finances and woodworking business with intelligent insights,
                    automated calculations, and AI-driven recommendations.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 justify-center px-4">
                <?php if ($isLoggedIn): ?>
                    <a href="/dashboard.php" class="bg-blue-600 text-white px-8 py-3 sm:py-4 rounded-lg hover:bg-blue-700 transition-colors text-base sm:text-lg font-medium w-full sm:w-auto">
                        Go to Dashboard
                    </a>
                    <a href="/analysis.php" class="bg-white text-blue-600 px-8 py-3 sm:py-4 rounded-lg border-2 border-blue-600 hover:bg-blue-50 transition-colors text-base sm:text-lg font-medium w-full sm:w-auto">
                        View AI Analysis
                    </a>
                <?php else: ?>
                    <a href="/register.php" class="bg-blue-600 text-white px-8 py-3 sm:py-4 rounded-lg hover:bg-blue-700 transition-colors text-base sm:text-lg font-medium w-full sm:w-auto">
                        Start Tracking Free
                    </a>
                    <a href="/login.php" class="bg-white text-blue-600 px-8 py-3 sm:py-4 rounded-lg border-2 border-blue-600 hover:bg-blue-50 transition-colors text-base sm:text-lg font-medium w-full sm:w-auto">
                        Sign In
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Features Grid -->
        <div class="mt-16 sm:mt-24 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
            <!-- Feature 1 -->
            <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Personal Finance</h3>
                <p class="text-gray-600">
                    Track daily income and expenses with categories, payment methods, and detailed notes.
                </p>
            </div>

            <!-- Feature 2 -->
            <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Business Management</h3>
                <p class="text-gray-600">
                    Manage woodworking projects with WIP tracking, job costing, and profit calculation.
                </p>
            </div>

            <!-- Feature 3 -->
            <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">AI Insights</h3>
                <p class="text-gray-600">
                    Get intelligent recommendations, spending analysis, and cash flow predictions.
                </p>
            </div>

            <!-- Feature 4 -->
            <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Profit Tracking</h3>
                <p class="text-gray-600">
                    Automatic profit and margin calculations for each product with detailed cost breakdown.
                </p>
            </div>

            <!-- Feature 5 -->
            <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Business Expenses</h3>
                <p class="text-gray-600">
                    Track general business expenses like rent, utilities, and tools separately from product costs.
                </p>
            </div>

            <!-- Feature 6 -->
            <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Job Costing</h3>
                <p class="text-gray-600">
                    Track daily costs for each woodworking project with material, labor, and transport breakdown.
                </p>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="mt-16 sm:mt-24 bg-gradient-to-r from-blue-600 to-purple-600 rounded-3xl p-8 sm:p-12 text-center text-white">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-4">
                Ready to Take Control of Your Finances?
            </h2>
            <p class="text-lg sm:text-xl text-blue-100 mb-8 max-w-2xl mx-auto">
                Start tracking your personal and business finances today with AI-powered insights.
            </p>
            <a href="/register.php" class="inline-block bg-white text-blue-600 px-8 py-3 sm:py-4 rounded-lg hover:bg-gray-100 transition-colors text-base sm:text-lg font-medium w-full sm:w-auto">
                Get Started Now
            </a>
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-200 mt-24 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-600">
            <p>© 2025 FinAI. AI-Powered Financial Management System.</p>
        </div>
    </footer>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
