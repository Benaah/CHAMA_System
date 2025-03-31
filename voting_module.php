<?php
// voting_module.php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
require 'config.php';

$userId = $_SESSION['user']['id'];

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['poll_id'], $_POST['choice'])) {
    $pollId = $_POST['poll_id'];
    $choice = $_POST['choice'];
    
    // Check if user already voted
    $stmt = $pdo->prepare("SELECT id FROM votes WHERE poll_id = ? AND user_id = ?");
    $stmt->execute([$pollId, $userId]);
    if ($stmt->fetch()) {
        $error = "You have already voted in this poll.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO votes (poll_id, user_id, choice, vote_date) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$pollId, $userId, $choice]);
        $success = "Your vote has been recorded.";
    }
}

// Retrieve active polls and vote counts
$pollsStmt = $pdo->query("SELECT * FROM polls WHERE status = 'active'");
$polls = $pollsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Voting Module - Agape Youth Group</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
  <a class="navbar-brand" href="dashboard.php">Dashboard</a>
</nav>
<div class="container mt-4">
  <h2>Decision-Making and Voting</h2>
  <?php if(isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php elseif(isset($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  
  <?php foreach ($polls as $poll): ?>
    <div class="card mb-3">
      <div class="card-header">
        <?= htmlspecialchars($poll['question']) ?>
      </div>
      <div class="card-body">
        <form method="post" action="voting_module.php">
          <input type="hidden" name="poll_id" value="<?= htmlspecialchars($poll['id']) ?>">
          <?php
          // Retrieve poll choices for the poll (assuming a poll_choices table exists)
          $stmt = $pdo->prepare("SELECT * FROM poll_choices WHERE poll_id = ?");
          $stmt->execute([$poll['id']]);
          $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
          foreach ($choices as $choice): ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="choice" value="<?= htmlspecialchars($choice['choice_text']) ?>" id="choice<?= htmlspecialchars($choice['id']) ?>">
              <label class="form-check-label" for="choice<?= htmlspecialchars($choice['id']) ?>">
                <?= htmlspecialchars($choice['choice_text']) ?>
              </label>
            </div>
          <?php endforeach; ?>
          <button type="submit" class="btn btn-primary mt-2">Vote</button>
        </form>
        <hr>
        <?php
        // Display current vote counts
        $stmt = $pdo->prepare("SELECT choice, COUNT(*) as votes FROM votes WHERE poll_id = ? GROUP BY choice");
        $stmt->execute([$poll['id']]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <h6>Current Results:</h6>
        <ul class="list-group">
          <?php foreach ($results as $result): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <?= htmlspecialchars($result['choice']) ?>
              <span class="badge badge-primary badge-pill"><?= htmlspecialchars($result['votes']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php endforeach; ?>
</div>
</body>
</html>
