<?php
echo password_hash('admin123', PASSWORD_BCRYPT) . "\n";
echo password_hash('manager123', PASSWORD_BCRYPT) . "\n";
echo password_hash('member123', PASSWORD_BCRYPT) . "\n";
?>