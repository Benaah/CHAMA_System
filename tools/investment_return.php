<?php
require_once '../config.php';
include '../includes/header.php';
?>

<div class="container mt-5">
    <h2 class="mb-4">Investment Return Calculator</h2>
    <p class="lead">Estimate your investment growth over time.</p>

    <form method="POST">
        <div class="mb-3">
            <label for="initial_investment" class="form-label">Initial Investment (KSh)</label>
            <input type="number" class="form-control" id="initial_investment" name="initial_investment" required>
        </div>
        <div class="mb-3">
            <label for="rate" class="form-label">Annual Interest Rate (%)</label>
            <input type="number" step="0.01" class="form-control" id="rate" name="rate" required>
        </div>
        <div class="mb-3">
            <label for="years" class="form-label">Investment Period (Years)</label>
            <input type="number" class="form-control" id="years" name="years" required>
        </div>
        <button type="submit" class="btn btn-primary">Calculate</button>
    </form>

    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $initial_investment = $_POST['initial_investment'];
        $rate = $_POST['rate'] / 100;
        $years = $_POST['years'];

        $final_amount = $initial_investment * pow(1 + $rate, $years);
        $interest_earned = $final_amount - $initial_investment;

        echo "<h3 class='mt-4'>Total Value After $years Years: KSh " . number_format($final_amount, 2) . "</h3>";
        echo "<p>Interest Earned: KSh " . number_format($interest_earned, 2) . "</p>";
    }
    ?>

</div>

<?php include '../includes/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>