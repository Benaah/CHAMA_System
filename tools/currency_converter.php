<?php
require_once '../config.php';
include '../includes/header.php';
?>

<div class="container mt-5">
    <h2 class="mb-4">Currency Converter</h2>
    <p class="lead">Convert between different currencies easily.</p>

    <form method="POST">
        <div class="mb-3">
            <label for="amount" class="form-label">Amount</label>
            <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
        </div>
        <div class="mb-3">
            <label for="currency" class="form-label">Convert To</label>
            <select class="form-control" id="currency" name="currency" required>
                <option value="USD">USD - US Dollar</option>
                <option value="EUR">EUR - Euro</option>
                <option value="GBP">GBP - British Pound</option>
                <option value="KES">KES - Kenyan Shilling</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Convert</button>
    </form>

    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $amount = $_POST['amount'];
        $currency = $_POST['currency'];

        // Simulated exchange rates (Ideally, fetch from an API)
        $rates = [
            "USD" => 0.0075, // 1 KES to USD
            "EUR" => 0.0069, // 1 KES to EUR
            "GBP" => 0.0059, // 1 KES to GBP
            "KES" => 1 // Kenyan Shilling stays the same
        ];

        if (isset($rates[$currency])) {
            $converted = $amount * $rates[$currency];
            echo "<h3 class='mt-4'>Converted Amount: " . number_format($converted, 2) . " $currency</h3>";
        } else {
            echo "<h3 class='mt-4 text-danger'>Invalid currency selected.</h3>";
        }
    }
    ?>

</div>

<?php include '../includes/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>