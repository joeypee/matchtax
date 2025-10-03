<?php
// Start the session to remember who's logged in
session_start();

// SumUp API Configuration (TEST MODE)
define('SUMUP_CLIENT_ID', 'sup_pk_2ptqVboltsLGKZQBjj5Yq2kbxUe4i0q2a');
define('SUMUP_CLIENT_SECRET', 'sup_sk_D8S6DOqnbgGDvBpyLVulq7OH5CceG67mg');
define('SUMUP_ACCESS_TOKEN', 'sup_sk_D8S6DOqnbgGDvBpyLVulq7OH5CceG67mg');
define('SUMUP_BASE_URL', 'https://api.sumup.com/v0.1/');

// SQLite database file
$db_file = 'data/cricket_subs.db';

// Make sure the data directory exists
if (!file_exists('data')) {
    mkdir('data', 0755, true);
}

// Connect to SQLite database
try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create tables if they don't exist
function createTables($pdo) {
    // Players table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS players (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            phone TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Matches table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS matches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            match_date DATE NOT NULL,
            player_list TEXT NOT NULL,
            sumup_link TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            status TEXT DEFAULT 'active'
        )
    ");
    
    // Simple payment tracking - just who has paid for which match
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            match_id INTEGER,
            player_id INTEGER,
            sumup_transaction_id TEXT,
            amount DECIMAL(10,2) DEFAULT 5.00,
            status TEXT DEFAULT 'pending',
            paid_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (match_id) REFERENCES matches(id),
            FOREIGN KEY (player_id) REFERENCES players(id),
            UNIQUE(match_id, player_id)
        )
    ");
}

// Initialize the database
createTables($pdo);

// Add missing column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE payments ADD COLUMN sumup_transaction_id TEXT");
} catch (PDOException $e) {
    // Column might already exist, ignore error
}

// SumUp API Functions
function createSumUpPayment($amount, $currency = 'GBP', $description = 'Cricket Subscription') {
    $url = SUMUP_BASE_URL . 'checkouts';
    
    $is_local = strpos($_SERVER['HTTP_HOST'], 'localhost') !== false;
    $base = $is_local ? 'http://localhost:8000' : 'https://yourdomain.com';

    $returnUrl = $base . '/cricket-subs.php?payment_success=1';
    $cancelUrl = $base . '/cricket-subs.php?payment_cancelled=1';
    
    $data = [
        'checkout_reference' => uniqid('cricket_'),
        'amount' => $amount,
        'currency' => $currency,
        'description' => $description,
        'merchant_code' => 'M9XXUU3U',
        'return_url' => $returnUrl,
        'redirect_url' => $cancelUrl
    ];
    
    $headers = [
        'Authorization: Bearer ' . SUMUP_ACCESS_TOKEN,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201) {
        $result = json_decode($response, true);
        
        // Now process the checkout
        if (isset($result['id'])) {
            $processUrl = SUMUP_BASE_URL . 'checkouts/' . $result['id'] . '/process';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $processUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            $processResponse = curl_exec($ch);
            $processHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            error_log("Process Checkout Response Code: " . $processHttpCode);
            error_log("Process Checkout Response: " . $processResponse);
            
            if ($processHttpCode === 200) {
                $processResult = json_decode($processResponse, true);
                return $processResult;
            }
        }
        
        return $result;
    } else {
        error_log("SumUp API Error - HTTP Code: " . $httpCode);
        error_log("SumUp API Response: " . $response);
        return false;
    }
}

function getSumUpPaymentStatus($checkoutId) {
    $url = SUMUP_BASE_URL . 'checkouts/' . $checkoutId;
    
    $headers = [
        'Authorization: Bearer ' . SUMUP_ACCESS_TOKEN,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return false;
}

// Add this function to test SumUp API
function testSumUpAPI() {
    $url = SUMUP_BASE_URL . 'me';
    $headers = [
        'Authorization: Bearer ' . SUMUP_ACCESS_TOKEN,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => $response
    ];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'register') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        if ($name && $email) {
            try {
                // Insert new player
                $stmt = $pdo->prepare("INSERT INTO players (name, email, phone) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $phone]);
                
                // Auto-login the new player
                $_SESSION['player_id'] = $pdo->lastInsertId();
                $_SESSION['player_name'] = $name;
                $_SESSION['player_email'] = $email;
                
                $success_message = "Account created successfully! You're now logged in.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Unique constraint violation
                    $error_message = "An account with this email already exists. Please try logging in instead.";
                } else {
                    $error_message = "Error creating account: " . $e->getMessage();
                }
            }
        } else {
            $error_message = "Please fill in all required fields.";
        }
    }
    
    if ($action === 'create_match') {
        $match_name = trim($_POST['match_name']);
        $match_date = $_POST['match_date'];
        $player_list = trim($_POST['player_list']);
        
        if ($match_name && $match_date && $player_list) {
            try {
                // Insert new match
                $stmt = $pdo->prepare("INSERT INTO matches (name, match_date, player_list) VALUES (?, ?, ?)");
                $stmt->execute([$match_name, $match_date, $player_list]);
                
                $match_id = $pdo->lastInsertId();
                $success_message = "Match created successfully! Match ID: " . $match_id;
            } catch (PDOException $e) {
                $error_message = "Error creating match: " . $e->getMessage();
            }
        } else {
            $error_message = "Please fill in all required fields.";
        }
    }
    
    if ($action === 'process_payment') {
        if (isset($_SESSION['player_id'])) {
            $match_id = $_POST['match_id'];
            $player_id = $_SESSION['player_id'];
            
            try {
                // Check if already paid
                $stmt = $pdo->prepare("SELECT id FROM payments WHERE match_id = ? AND player_id = ? AND status = 'completed'");
                $stmt->execute([$match_id, $player_id]);
                
                if (!$stmt->fetch()) {
                    // Create SumUp payment
                    $payment = createSumUpPayment(5.00, 'GBP', 'Cricket Subscription - Match ' . $match_id);
                    
                    if ($payment && isset($payment['id'])) {
                        // Use INSERT OR REPLACE to handle duplicates
                        $stmt = $pdo->prepare("INSERT OR REPLACE INTO payments (match_id, player_id, sumup_transaction_id, amount, status) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$match_id, $player_id, $payment['id'], 5.00, 'pending']);
                        
                        // Redirect to SumUp payment page
                        header('Location: ' . $payment['redirect_url']);
                        exit;
                    } else {
                        $error_message = "Error creating payment. Please try again.";
                    }
                } else {
                    $error_message = "You have already paid for this match.";
                }
            } catch (PDOException $e) {
                $error_message = "Error processing payment: " . $e->getMessage();
            }
        }
    }
}

// Handle SumUp callbacks
if (isset($_GET['payment_success'])) {
    $success_message = "Payment successful! Thank you for paying your £5 subscription.";
}

if (isset($_GET['payment_cancelled'])) {
    $error_message = "Payment was cancelled. You can try again anytime.";
}

// Handle auto-login via email parameter
if (isset($_GET['email']) && !isset($_SESSION['player_id'])) {
    $email = trim($_GET['email']);
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, email FROM players WHERE email = ?");
        $stmt->execute([$email]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($player) {
            // Auto-login the player
            $_SESSION['player_id'] = $player['id'];
            $_SESSION['player_name'] = $player['name'];
            $_SESSION['player_email'] = $player['email'];
            $success_message = "Welcome back, " . $player['name'] . "!";
        }
    } catch (PDOException $e) {
        $error_message = "Error logging in: " . $e->getMessage();
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ?');
    exit;
}

// Get current match if specified
$current_match = null;
if (isset($_GET['match'])) {
    $match_id = $_GET['match'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
        $stmt->execute([$match_id]);
        $current_match = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Error loading match: " . $e->getMessage();
    }
}

// Check if player is logged in
$is_logged_in = isset($_SESSION['player_id']);

// Load recent matches for display
$recent_matches = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM matches ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $recent_matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error silently for now
}

// Function to get payment status for a match
function getPaymentStatus($pdo, $match_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.name, p.email, 
                   CASE WHEN pay.status = 'completed' THEN 'paid' ELSE 'pending' END as status,
                   pay.paid_at
            FROM players p
            LEFT JOIN payments pay ON p.id = pay.player_id AND pay.match_id = ?
            ORDER BY p.name
        ");
        $stmt->execute([$match_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Cricket Subs</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.5;
            color: #333;
            background: #f8fafc;
            font-size: 16px;
        }
        
        .container {
            max-width: 100%;
            margin: 0;
            padding: 16px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 24px;
            padding: 24px 16px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 8px;
        }
        
        .header p {
            color: #718096;
            font-size: 1rem;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
        }
        
        .card h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 16px;
        }
        
        .card h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 12px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 16px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: border-color 0.2s;
            -webkit-appearance: none;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3182ce;
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 16px 24px;
            background: #3182ce;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            transition: all 0.2s;
            text-align: center;
            margin-bottom: 12px;
        }
        
        .btn:hover {
            background: #2c5aa0;
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: #38a169;
        }
        
        .btn-success:hover {
            background: #2f855a;
        }
        
        .btn-danger {
            background: #e53e3e;
        }
        
        .btn-danger:hover {
            background: #c53030;
        }
        
        .btn-sm {
            padding: 12px 20px;
            font-size: 16px;
        }
        
        .alert {
            padding: 16px;
            margin-bottom: 20px;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #f0fff4;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .alert-danger {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #feb2b2;
        }
        
        .logged-in {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
        }
        
        .logged-in h2 {
            color: white;
            margin-bottom: 8px;
            font-size: 1.5rem;
        }
        
        .logged-in p {
            opacity: 0.9;
            margin-bottom: 16px;
        }
        
        .admin-section {
            background: #f7fafc;
            padding: 24px;
            border-radius: 16px;
            margin: 24px 0;
            border-left: 4px solid #3182ce;
        }
        
        .match-item {
            background: white;
            padding: 20px;
            margin: 16px 0;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .match-item h4 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 12px;
        }
        
        .match-link {
            background: #f7fafc;
            padding: 16px;
            border-radius: 12px;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 14px;
            word-break: break-all;
            margin: 12px 0;
            border: 1px solid #e2e8f0;
            overflow-x: auto;
        }
        
        .payment-summary {
            background: #edf2f7;
            padding: 16px;
            border-radius: 12px;
            margin: 16px 0;
            font-weight: 600;
            color: #4a5568;
            text-align: center;
        }
        
        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .payment-table th {
            background: #f7fafc;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
            font-size: 14px;
        }
        
        .payment-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        
        .payment-table tr:hover {
            background: #f7fafc;
        }
        
        .payment-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .payment-status.paid {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .payment-status.pending {
            background: #fef5e7;
            color: #744210;
        }
        
        .match-view {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            padding: 24px;
            border-radius: 16px;
            margin: 20px 0;
        }
        
        .match-view h2 {
            color: #744210;
            margin-bottom: 12px;
            font-size: 1.5rem;
        }
        
        .match-view p {
            color: #744210;
            margin-bottom: 16px;
        }
        
        .copy-link {
            background: #3182ce;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            margin-top: 8px;
        }
        
        .copy-link:hover {
            background: #2c5aa0;
        }
        
        .player-list {
            margin: 12px 0;
        }
        
        .player-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        .player-list li:last-child {
            border-bottom: none;
        }
        
        .sumup-info {
            background: #e6f3ff;
            padding: 16px;
            border-radius: 12px;
            margin: 16px 0;
            border-left: 4px solid #3182ce;
        }
        
        /* Mobile-specific optimizations */
        @media (max-width: 480px) {
            .container {
                padding: 12px;
            }
            
            .header {
                padding: 20px 12px;
            }
            
            .header h1 {
                font-size: 1.75rem;
            }
            
            .card {
                padding: 16px;
            }
            
            .admin-section {
                padding: 16px;
            }
            
            .payment-table {
                font-size: 12px;
            }
            
            .payment-table th,
            .payment-table td {
                padding: 8px 4px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏏 Cricket Subs</h1>
            <p>Simple subscription management for your cricket team</p>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if ($is_logged_in): ?>
            <div class="logged-in">
                <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['player_name']); ?>!</h2>
                <p>You're logged in and ready to make payments.</p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['player_email']); ?></p>
                <a href="?logout=1" class="btn btn-danger">Logout</a>
            </div>
        <?php endif; ?>
        
        <?php if ($current_match): ?>
            <div class="match-view">
                <h2>Match: <?php echo htmlspecialchars($current_match['name']); ?></h2>
                <p><strong>Date:</strong> <?php echo $current_match['match_date']; ?></p>
                
                <?php if ($is_logged_in): ?>
                    <?php
                    // Check if player has paid for this match
                    $has_paid = false;
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM payments WHERE match_id = ? AND player_id = ? AND status = 'completed'");
                        $stmt->execute([$current_match['id'], $_SESSION['player_id']]);
                        $has_paid = $stmt->fetch() !== false;
                    } catch (PDOException $e) {
                        // Handle error silently
                    }
                    ?>
                    
                    <p>You're logged in as <strong><?php echo htmlspecialchars($_SESSION['player_name']); ?></strong></p>
                    
                    <?php if ($has_paid): ?>
                        <div class="alert alert-success">
                            <strong>Payment Complete!</strong> You've already paid your £5 subscription for this match.
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="process_payment">
                            <input type="hidden" name="match_id" value="<?php echo $current_match['id']; ?>">
                            <p>Click below to pay your £5 subscription fee via SumUp:</p>
                            <button type="submit" class="btn btn-success">Pay £5 via SumUp</button>
                        </form>
                        
                        <div class="sumup-info">
                            <p><strong>🔒 Secure Payment:</strong> Your payment will be processed securely by SumUp. You'll be redirected to their secure payment page.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Please log in or create an account to make a payment.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Admin Section -->
        <div class="admin-section">
            <h2>Admin Dashboard</h2>
            
            <div class="card">
                <h3>Create New Match</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_match">
                    
                    <div class="form-group">
                        <label for="match_name">Match Name *</label>
                        <input type="text" id="match_name" name="match_name" required placeholder="e.g., vs Local Rivals">
                    </div>
                    
                    <div class="form-group">
                        <label for="match_date">Match Date *</label>
                        <input type="date" id="match_date" name="match_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="player_list">Player List * (one per line)</label>
                        <textarea id="player_list" name="player_list" required placeholder="John Smith&#10;Mike Johnson&#10;Sarah Wilson"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-success">Create Match</button>
                </form>
            </div>
            
            <h3>Recent Matches & Payment Status</h3>
            <?php if (empty($recent_matches)): ?>
                <div class="card">
                    <p>No matches created yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_matches as $match): ?>
                    <div class="match-item">
                        <h4><?php echo htmlspecialchars($match['name']); ?> - <?php echo $match['match_date']; ?></h4>
                        
                        <p><strong>Match Link:</strong></p>
                        <div class="match-link" id="link-<?php echo $match['id']; ?>"><?php echo "http://localhost:8000/cricket-subs.php?match=" . $match['id']; ?></div>
                        <button class="copy-link" onclick="copyToClipboard('link-<?php echo $match['id']; ?>')">Copy Link</button>
                        
                        <?php
                        $payment_status = getPaymentStatus($pdo, $match['id']);
                        $paid_count = count(array_filter($payment_status, function($p) { return $p['status'] === 'paid'; }));
                        $total_count = count($payment_status);
                        ?>
                        
                        <div class="payment-summary">
                            <strong>Payment Status:</strong> <?php echo $paid_count; ?> of <?php echo $total_count; ?> players paid
                        </div>
                        
                        <table class="payment-table">
                            <thead>
                                <tr>
                                    <th>Player</th>
                                    <th>Status</th>
                                    <th>Paid At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payment_status as $player): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($player['name']); ?></td>
                                        <td>
                                            <span class="payment-status <?php echo $player['status']; ?>">
                                                <?php echo strtoupper($player['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $player['paid_at'] ? date('M j, g:i A', strtotime($player['paid_at'])) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if (!$is_logged_in && !$current_match): ?>
            <div class="card">
                <h2>Player Registration</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number (optional)</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                    
                    <button type="submit" class="btn">Create Account</button>
                </form>
                
                <hr style="margin: 30px 0; border: none; border-top: 1px solid #e2e8f0;">
                
                <h3>Already have an account?</h3>
                <p>If you're already registered, you can auto-login by visiting a match link with your email:</p>
                <p><code>yoursite.com/cricket-subs.php?email=your@email.com</code></p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;
            
            navigator.clipboard.writeText(text).then(function() {
                // Change button text temporarily
                const button = event.target;
                const originalText = button.textContent;
                button.textContent = 'Copied!';
                button.style.background = '#38a169';
                
                setTimeout(function() {
                    button.textContent = originalText;
                    button.style.background = '#3182ce';
                }, 2000);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
            });
        }
    </script>
</body>
</html>
