<?php
// messaging.php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
require 'config.php';

$userId = $_SESSION['user']['id'];

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO messages (user_id, message, message_date) VALUES (?, ?, NOW())");
        $stmt->execute([$userId, $message]);
    }
}

// Fetch all messages (latest first)
$stmt = $pdo->query("SELECT m.*, u.username FROM messages m JOIN users u ON m.user_id = u.id ORDER BY m.message_date DESC");
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Internal Messaging - Agape Youth Group</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    .message-box { border-bottom: 1px solid #ddd; padding: 10px 0; }
  </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
  <a class="navbar-brand" href="dashboard.php">Dashboard</a>
</nav>
<div class="container mt-4">
  <h2>Internal Messaging</h2>
  <form method="post" action="messaging.php">
    <div class="form-group">
      <textarea class="form-control" name="message" placeholder="Type your message here..." required></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Send Message</button>
  </form>
  <hr>
  <div class="messages">
    <?php foreach ($messages as $msg): ?>
      <div class="message-box">
        <strong><?= htmlspecialchars($msg['username']) ?></strong>
        <small class="text-muted"><?= htmlspecialchars($msg['message_date']) ?></small>
        <p><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
