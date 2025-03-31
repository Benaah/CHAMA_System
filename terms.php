<?php
require_once 'config.php';
include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Terms and Conditions</h4>
                </div>
                <div class="card-body">
                    <h5>1. Membership</h5>
                    <p>1.1. Membership in Agape Youth Chama is open to young adults between the ages of 18 and 35 years.</p>
                    <p>1.2. All members must complete the registration process and provide accurate personal information.</p>
                    <p>1.3. Members agree to abide by these terms and conditions and any other rules or regulations established by the Chama.</p>
                    <p>1.4. Membership may be terminated if a member violates these terms or fails to meet their financial obligations.</p>
                    
                    <h5>2. Contributions</h5>
                    <p>2.1. Each member is required to make a minimum monthly contribution of KES <?php echo number_format(MIN_CONTRIBUTION, 2); ?>.</p>
                    <p>2.2. Contributions must be made by the 5th day of each month.</p>
                    <p>2.3. Members may make additional contributions above the minimum requirement.</p>
                    <p>2.4. All contributions are subject to verification and approval by the Chama administrators.</p>
                    
                    <h5>3. Loans</h5>
                    <p>3.1. Members may apply for loans after a minimum of three months of consistent contributions.</p>
                    <p>3.2. The maximum loan amount is <?php echo MAX_LOAN_MULTIPLIER; ?> times the member's total savings.</p>
                    <p>3.3. Loans are subject to an interest rate of <?php echo LOAN_INTEREST_RATE; ?>% for the loan period.</p>
                    <p>3.4. Loan approval is at the discretion of the Chama administrators and is subject to fund availability.</p>
                    <p>3.5. Members with outstanding loans may not apply for additional loans until the existing loan is fully repaid.</p>
                    
                    <h5>4. Meetings</h5>
                    <p>4.1. Members are expected to attend all scheduled Chama meetings.</p>
                    <p>4.2. Members who cannot attend a meeting should notify the administrators in advance.</p>
                    <p>4.3. Consistent absence from meetings without valid reasons may result in penalties or membership review.</p>
                    
                    <h5>5. Withdrawals</h5>
                    <p>5.1. Members may withdraw from the Chama by providing a 30-day written notice.</p>
                    <p>5.2. Upon withdrawal, members will receive their total contributions less any outstanding loans or fees.</p>
                    <p>5.3. Processing of withdrawal requests may take up to 60 days.</p>
                    
                    <h5>6. Dispute Resolution</h5>
                    <p>6.1. Any disputes between members or with the Chama administration will be resolved through internal mediation.</p>
                    <p>6.2. If internal mediation fails, disputes will be referred to external arbitration.</p>
                    
                    <h5>7. Amendments</h5>
                    <p>7.1. These terms and conditions may be amended from time to time by the Chama administration.</p>
                    <p>7.2. Members will be notified of any amendments, and continued membership constitutes acceptance of the amended terms.</p>
                    
                    <h5>8. Liability</h5>
                    <p>8.1. The Chama and its administrators are not liable for any financial losses incurred by members due to market fluctuations or investment decisions.</p>
                    <p>8.2. Members acknowledge that all investments carry risk and that returns are not guaranteed.</p>
                    
                    <h5>9. Governing Law</h5>
                    <p>9.1. These terms and conditions are governed by the laws of Kenya.</p>
                    <p>9.2. Any legal proceedings arising from these terms shall be conducted in the courts of Kenya.</p>
                </div>
                <div class="card-footer text-center">
                    <p class="mb-0">Last updated: <?php echo date('F d, Y'); ?></p>
                    <a href="register.php" class="btn btn-primary mt-3">Back</a>
                </div>
            </div>
        </div>    </div>
</div>

<?php include 'includes/footer.php'; ?>