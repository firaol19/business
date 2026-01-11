<?php
function formatCurrency($amount, $currencyCode = 'ETB') {
    return number_format($amount, 2) . ' ' . $currencyCode;
}

function isActivePath($path) {
    $currentPath = $_SERVER['REQUEST_URI'];
    // Simple check: if current path contains the checked path
    return strpos($currentPath, $path) !== false;
}
?>
