<?php
require_once '../config.php';
include '../includes/header.php';
?>

<div class="container mt-5">
    <h2 class="mb-4">Financial Goal Planner</h2>
    <p class="lead">Set your financial goals and track your progress.</p>

    <form method="POST">
        <div class="mb-3">
            <label for="goal_name" class="form-label">Goal Name</label>
            <input type="text" class="form-control" id="goal_name" name="goal_name" required>
        </div>
        <div class="mb-3">
            <label for="goal_amount" class="form-label">Target Amount (KSh)</label>
            <input type="number" class="form-control" id="goal_amount" name="goal_amount" required>
        </div>
        <div class="mb-3">
            <label for="saved_amount" class="form-label">Amount Already Saved (KSh)</label>
            <input type="number" class="form-control" id="saved_amount" name="saved_amount" required>
        </div>
        <button type="submit" class="btn btn-primary">Calculate Progress</button>
    </form>

    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $goal_name = $_POST['goal_name'];
        $goal_amount = $_POST['goal_amount'];
        $saved_amount = $_POST['saved_amount'];

        $progress = ($saved_amount / $goal_amount) * 100;
        $remaining = $goal_amount - $saved_amount;

        echo "<h3 class='mt-4'>Progress on '$goal_name': " . number_format($progress, 2) . "%</h3>";
        echo "<p>Remaining Amount to Reach Your Goal: KSh " . number_format($remaining, 2) . "</p>";
    }
    ?>

</div>

<?php include '../includes/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>