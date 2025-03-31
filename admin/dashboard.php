<?php
session_start();
include 'config.php';

// Ensure only admins can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch members, transactions, loans, welfare requests
$members = $pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
$transactions = $pdo->query("SELECT * FROM transactions WHERE status = 'pending'")->fetchAll(PDO::FETCH_ASSOC);
$loans = $pdo->query("SELECT * FROM loans WHERE status = 'pending'")->fetchAll(PDO::FETCH_ASSOC);
$welfare_requests = $pdo->query("SELECT * FROM welfare WHERE status = 'pending'")->fetchAll(PDO::FETCH_ASSOC);

// Handle member deletion
if (isset($_POST['delete_member'])) {
    $user_id = $_POST['user_id'];
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
    header("Location: admin_dashboard.php");
}

// Handle approval of transactions, loans, and welfare requests
if (isset($_POST['approve'])) {
    $id = $_POST['id'];
    $table = $_POST['table'];
    $pdo->prepare("UPDATE $table SET status = 'approved' WHERE id = ?")->execute([$id]);
    header("Location: admin_dashboard.php");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2>Admin Dashboard</h2>

    <h3>Manage Members</h3>
    <table class="table table-bordered">
        <tr><th>Name</th><th>Email</th><th>Phone</th><th>Action</th></tr>
        <?php foreach ($members as $member): ?>
        <tr>
            <td><?= $member['name'] ?></td>
            <td><?= $member['email'] ?></td>
            <td><?= $member['phone'] ?></td>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="user_id" value="<?= $member['id'] ?>">
                    <button type="submit" name="delete_member" class="btn btn-danger btn-sm">Remove</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h3>Pending Approvals</h3>
    <h4>Transactions</h4>
    <table class="table table-bordered">
        <tr><th>User</th><th>Amount</th><th>Action</th></tr>
        <?php foreach ($transactions as $trans): ?>
        <tr>
            <td><?= $trans['user_id'] ?></td>
            <td><?= $trans['amount'] ?></td>
            <td>
                <form method="post">
                    <input type="hidden" name="id" value="<?= $trans['id'] ?>">
                    <input type="hidden" name="table" value="transactions">
                    <button type="submit" name="approve" class="btn btn-success btn-sm">Approve</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h4>Loans</h4>
    <table class="table table-bordered">
        <tr><th>User</th><th>Amount</th><th>Action</th></tr>
        <?php foreach ($loans as $loan): ?>
        <tr>
            <td><?= $loan['user_id'] ?></td>
            <td><?= $loan['amount'] ?></td>
            <td>
                <form method="post">
                    <input type="hidden" name="id" value="<?= $loan['id'] ?>">
                    <input type="hidden" name="table" value="loans">
                    <button type="submit" name="approve" class="btn btn-success btn-sm">Approve</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h4>Welfare Requests</h4>
    <table class="table table-bordered">
        <tr><th>User</th><th>Reason</th><th>Amount</th><th>Action</th></tr>
        <?php foreach ($welfare_requests as $request): ?>
        <tr>
            <td><?= $request['user_id'] ?></td>
            <td><?= $request['reason'] ?></td>
            <td><?= $request['amount'] ?></td>
            <td>
                <form method="post">
                    <input type="hidden" name="id" value="<?= $request['id'] ?>">
                    <input type="hidden" name="table" value="welfare">
                    <button type="submit" name="approve" class="btn btn-success btn-sm">Approve</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
