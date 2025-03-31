<?php
require_once '../config.php';
include '../includes/resource_header.php';
?>

<div class="container mt-5">
    <h2 class="mb-4">Savings Calculator</h2>
    <p class="lead">Plan your savings and reach your financial goals faster.</p>

    <form method="POST">
        <div class="mb-3">
            <label for="goal_amount" class="form-label">Target Savings Amount (KSh)</label>
            <input type="number" class="form-control" id="goal_amount" name="goal_amount" required>
        </div>
        <div class="mb-3">
            <label for="months" class="form-label">Timeframe (Months)</label>
            <input type="number" class="form-control" id="months" name="months" required>
        </div>
        <div class="mb-3">
            <label for="initial_savings" class="form-label">Amount Already Saved (KSh)</label>
            <input type="number" class="form-control" id="initial_savings" name="initial_savings" required>
        </div>
        <button type="submit" class="btn btn-primary">Calculate Monthly Savings</button>
    </form>

    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $goal_amount = $_POST['goal_amount'];
        $months = $_POST['months'];
        $initial_savings = $_POST['initial_savings'];

        $remaining_amount = $goal_amount - $initial_savings;
        $monthly_savings = $remaining_amount / $months;

        echo "<h3 class='mt-4'>You Need to Save: KSh " . number_format($monthly_savings, 2) . " per Month</h3>";
    }
    ?>

</div>

<?php include '../includes/resource_footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>