<?php
require_once '../config.php';
include '../includes/resource_header.php';
?>

<div class="container mt-5">
    <div class="card shadow-lg">
        <div class="card-body">
            <h2 class="mb-4 text-primary text-center"><i class="fas fa-calculator"></i> Loan Calculator</h2>
            <p class="lead text-center">Calculate your monthly loan payments easily.</p>
            
            <form method="POST">
                <div class="row">
                    <div class="col-md-4">
                        <label for="amount" class="form-label">Loan Amount (KSh)</label>
                        <input type="number" class="form-control" id="amount" name="amount" required>
                    </div>
                    <div class="col-md-4">
                        <label for="rate" class="form-label">Annual Interest Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" id="rate" name="rate" required>
                    </div>
                    <div class="col-md-4">
                        <label for="years" class="form-label">Loan Term (Years)</label>
                        <input type="number" class="form-control" id="years" name="years" required>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-calculator"></i> Calculate</button>
                </div>
            </form>

            <?php
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $amount = $_POST['amount'];
                $rate = $_POST['rate'] / 100 / 12;
                $months = $_POST['years'] * 12;

                if ($rate > 0) {
                    $monthly_payment = $amount * ($rate * pow(1 + $rate, $months)) / (pow(1 + $rate, $months) - 1);
                    echo "<div class='alert alert-info mt-4 text-center'><h4>Estimated Monthly Payment: <strong>KSh " . number_format($monthly_payment, 2) . "</strong></h4></div>";
                } else {
                    echo "<div class='alert alert-danger mt-4 text-center'><h4>Please enter a valid interest rate.</h4></div>";
                }
            }
            ?>
        </div>
    </div>
</div>

<?php include '../includes/resource_footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>