<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Fetch savings history
$stmt = $pdo->prepare("SELECT * FROM savings WHERE user_id = ? ORDER BY savings_date DESC");
$stmt->execute([$user_id]);
$savings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle new savings deposit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $amount = floatval($_POST['amount']);
    if ($amount > 0) {
        $savings_stmt = $pdo->prepare("INSERT INTO savings (user_id, amount) VALUES (?, ?)");
        if ($savings_stmt->execute([$user_id, $amount])) {
            $message = "<div class='alert alert-success'>Savings added successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Failed to add savings.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Invalid amount.</div>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Savings</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2>Savings</h2>
    <?= $message ?>

    <form method="post">
        <div class="form-group">
            <label>Deposit Amount (KES)</label>
            <input type="number" name="amount" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-success">Deposit Savings</button>
    </form>

    <h3 class="mt-4">Savings History</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Amount (KES)</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($savings as $save): ?>
            <tr>
                <td><?= $save['amount'] ?></td>
                <td><?= $save['savings_date'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
