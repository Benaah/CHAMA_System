<?php
// WalletManager.php
class WalletManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Update the wallet balance for a user (this would be triggered after a successful mobile money transaction)
    public function updateWallet($userId, $amount, $transactionType = 'credit') {
        // Fetch current wallet balance
        $stmt = $this->pdo->prepare("SELECT balance FROM wallets WHERE user_id = ?");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentBalance = $wallet ? $wallet['balance'] : 0;
        
        if ($transactionType == 'credit') {
            $newBalance = $currentBalance + $amount;
        } else {
            $newBalance = $currentBalance - $amount;
        }
        
        // Update the wallet record
        if ($wallet) {
            $stmt = $this->pdo->prepare("UPDATE wallets SET balance = ? WHERE user_id = ?");
            $stmt->execute([$newBalance, $userId]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?)");
            $stmt->execute([$userId, $newBalance]);
        }
        return $newBalance;
    }
    
    // Record an investment transaction
    public function recordInvestment($userId, $investmentType, $amount, $details = '') {
        $stmt = $this->pdo->prepare("INSERT INTO investments (user_id, investment_type, amount, details, investment_date) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $investmentType, $amount, $details]);
        return $this->pdo->lastInsertId();
    }
    
    // Example: Simulate a mobile money API callback to credit a user's wallet
    public function processMobileMoneyCallback($callbackData) {
        // Validate callbackData fields like transaction_id, user_id, amount, etc.
        if(isset($callbackData['user_id'], $callbackData['amount'])) {
            $userId = $callbackData['user_id'];
            $amount = $callbackData['amount'];
            // Assume credit for now; in a full system, handle different transaction types.
            $newBalance = $this->updateWallet($userId, $amount, 'credit');
            return $newBalance;
        }
        return false;
    }
}
?>
