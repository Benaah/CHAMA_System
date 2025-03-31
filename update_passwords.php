<?php
$pdo = new PDO('pgsql:host=db;dbname=agape_youth_group', 'postgres', '.PointBlank16328');

// Update admin password
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$stmt->execute([password_hash('admin123', PASSWORD_BCRYPT)]);

// Update manager password
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'manager'");
$stmt->execute([password_hash('manager123', PASSWORD_BCRYPT)]);

// Update member passwords
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_role = 'member'");
$stmt->execute([password_hash('member123', PASSWORD_BCRYPT)]);

echo "Passwords updated successfully!\n";
?>