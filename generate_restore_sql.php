<?php
// generate_restore_sql.php
// Reads financial-data-all-2026-01-02.csv and generates import_restored_data.sql

$csvFile = __DIR__ . '/financial-data-all-2026-01-02.csv';
$sqlFile = __DIR__ . '/import_restored_data.sql';

if (!file_exists($csvFile)) {
    die("CSV file not found.");
}

$lines = file($csvFile, FILE_IGNORE_NEW_LINES);
$sql = "-- Restored Data Import\nUSE business_php;\n\n";
$sql .= "INSERT IGNORE INTO User (id, name, email, password) VALUES (1, 'Firaol', 'firaol@example.com', '" . password_hash('12345678', PASSWORD_DEFAULT) . "');\n\n";

$section = '';
$headers = [];

$userId = 1; // Default user

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;

    // Detect Section Headers (Lines that are NOT comma-separated, or look like titles)
    // Actually, the titles in the file are like "Personal Transactions" (no commas)
    if (strpos($line, ',') === false) {
        $section = $line;
        continue;
    }

    // Detect Column Header Rows (contain "Date" or "Name" etc as first item)
    if ($line === 'Date,Type,Category,Amount,Payment Method,Note' || 
        $line === 'Date,Category,Description,Amount,Payment Method,Note' ||
        $line === 'Name,Category,Status,Started,Finished,Selling Price,Client,Payment Status' ||
        $line === 'Product Name,Cost Type,Description,Amount,Date' ||
        $line === 'Product Name,Amount,Date,Method') {
        continue; // Skip header row
    }

    $row = str_getcsv($line);

    if ($section === 'Personal Transactions') {
        // [Date, Type, Category, Amount, Payment Method, Note]
        $date = date('Y-m-d H:i:s', strtotime($row[0]));
        $type = strtolower($row[1]); // Ensure lowercase 'income'/'expense' if needed, CSV says 'Type'
        $category = escape($row[2]);
        $amount = floatval($row[3]);
        $payMethod = escape($row[4]);
        $note = escape($row[5]);

        $sql .= "INSERT INTO PersonalTransaction (userId, type, category, amount, paymentMethod, transactionDate, note) VALUES ($userId, '$type', '$category', $amount, '$payMethod', '$date', '$note');\n";
    }
    elseif ($section === 'Business Expenses') {
        // [Date, Category, Description, Amount, Payment Method, Note]
        $date = date('Y-m-d H:i:s', strtotime($row[0]));
        $category = escape($row[1]);
        $desc = escape($row[2]);
        $amount = floatval($row[3]);
        $payMethod = escape($row[4]);
        $note = escape($row[5]);

        $sql .= "INSERT INTO BusinessExpense (userId, category, description, amount, expenseDate, paymentMethod, note) VALUES ($userId, '$category', '$desc', $amount, '$date', '$payMethod', '$note');\n";
    }
    elseif ($section === 'Products') {
        // [Name, Category, Status, Started, Finished, Selling Price, Client, Payment Status]
        $name = escape($row[0]);
        $category = escape($row[1]);
        $status = strtolower($row[2]); // wip, finished, sold
        $started = date('Y-m-d H:i:s', strtotime($row[3]));
        $finished = !empty($row[4]) ? "'" . date('Y-m-d H:i:s', strtotime($row[4])) . "'" : "NULL";
        $price = floatval($row[5]);
        $client = escape($row[6]);
        $payStatus = escape($row[7]);

        $sql .= "INSERT INTO Product (userId, name, category, status, startedAt, finishedAt, finalSellingPrice, clientName, paymentStatus) VALUES ($userId, '$name', '$category', '$status', '$started', $finished, $price, '$client', '$payStatus');\n";
        
        // If Sold, create a generic sale record since we might not have 'Product Sales' section
        if ($status === 'sold' && $price > 0) {
           // We need the ID. Use Variable logic.
           $saleDate = $finished !== "NULL" ? $finished : "'$started'"; // Fallback
           $sql .= "SET @lastPid = (SELECT id FROM Product WHERE name = '$name' AND userId = $userId ORDER BY id DESC LIMIT 1);\n";
           $sql .= "INSERT INTO ProductSale (productId, quantity, sellingPrice, clientName, soldAt) VALUES (@lastPid, 1, $price, '$client', $saleDate);\n";
        }
    }
    elseif ($section === 'Product Costs') {
        // [Product Name, Cost Type, Description, Amount, Date]
        $pName = escape($row[0]);
        $costType = escape($row[1]);
        $desc = escape($row[2]);
        $amount = floatval($row[3]);
        $date = date('Y-m-d H:i:s', strtotime($row[4]));

        // Lookup ID
        $sql .= "SET @pId = (SELECT id FROM Product WHERE name = '$pName' AND userId = $userId ORDER BY id DESC LIMIT 1);\n";
        // Only insert if ID found? SQL will perform insert with NULL if not found, let's wrap or assume integrity.
        // Actually, if @pId is null, insert fails? No, foreign key check fails.
        // We assume product created above.
        $sql .= "INSERT INTO ProductCost (productId, costType, description, amount, incurredDate) VALUES (@pId, '$costType', '$desc', $amount, '$date');\n";
    }
     elseif ($section === 'Product Payments') {
        // [Product Name, Amount, Date, Method]
        $pName = escape($row[0]);
        $amount = floatval($row[1]);
        $date = date('Y-m-d H:i:s', strtotime($row[2]));
        $method = escape($row[3]);

        $sql .= "SET @pId = (SELECT id FROM Product WHERE name = '$pName' AND userId = $userId ORDER BY id DESC LIMIT 1);\n";
        $sql .= "INSERT INTO ProductPayment (productId, amount, paymentDate, method) VALUES (@pId, $amount, '$date', '$method');\n";
    }
}

file_put_contents($sqlFile, $sql);
echo "SQL file generated: $sqlFile";

function escape($str) {
    return str_replace("'", "''", trim($str));
}
?>
