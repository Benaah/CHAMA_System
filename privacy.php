<?php
require_once 'config.php';
include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Privacy Policy</h4>
                </div>
                <div class="card-body">
                    <h5>1. Introduction</h5>
                    <p>1.1. Agape Youth Chama is committed to protecting the privacy of our members and users of our platform.</p>
                    <p>1.2. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our services.</p>
                    <p>1.3. By becoming a member or using our platform, you consent to the practices described in this Privacy Policy.</p>
                    
                    <h5>2. Information We Collect</h5>
                    <p>2.1. <strong>Personal Information:</strong> We collect personal information that you provide directly to us, including:</p>
                    <ul>
                        <li>Full name</li>
                        <li>Email address</li>
                        <li>Phone number</li>
                        <li>Username and password</li>
                        <li>Date of birth</li>
                        <li>National ID or passport number</li>
                        <li>Banking information for contributions and loan disbursements</li>
                    </ul>
                    
                    <p>2.2. <strong>Usage Information:</strong> We automatically collect certain information about your use of our platform, including:</p>
                    <ul>
                        <li>IP address</li>
                        <li>Browser type</li>
                        <li>Access times</li>
                        <li>Pages viewed</li>
                        <li>Actions performed on the platform</li>
                    </ul>
                    
                    <h5>3. How We Use Your Information</h5>
                    <p>3.1. We use the information we collect to:</p>
                    <ul>
                        <li>Provide, maintain, and improve our services</li>
                        <li>Process contributions and loan applications</li>
                        <li>Communicate with you about meetings, announcements, and account activities</li>
                        <li>Verify your identity and prevent fraud</li>
                        <li>Generate reports and analytics about Chama activities</li>
                        <li>Comply with legal obligations</li>
                    </ul>
                    
                    <h5>4. Information Sharing and Disclosure</h5>
                    <p>4.1. We may share your information with:</p>
                    <ul>
                        <li>Chama administrators and committee members for operational purposes</li>
                        <li>Financial institutions to process contributions and loan disbursements</li>
                        <li>Service providers who assist in our operations</li>
                        <li>Legal authorities when required by law or to protect our rights</li>
                    </ul>
                    
                    <p>4.2. We will not sell, rent, or trade your personal information to third parties for marketing purposes.</p>
                    
                    <h5>5. Data Security</h5>
                    <p>5.1. We implement appropriate technical and organizational measures to protect your personal information from unauthorized access, disclosure, alteration, or destruction.</p>
                    <p>5.2. Despite our efforts, no method of transmission over the Internet or electronic storage is 100% secure, and we cannot guarantee absolute security.</p>
                    
                    <h5>6. Data Retention</h5>
                    <p>6.1. We retain your personal information for as long as necessary to fulfill the purposes outlined in this Privacy Policy, unless a longer retention period is required or permitted by law.</p>
                    <p>6.2. When you withdraw from the Chama, we may retain certain information as required by law or for legitimate business purposes.</p>
                    
                    <h5>7. Your Rights</h5>
                    <p>7.1. You have the right to:</p>
                    <ul>
                        <li>Access the personal information we hold about you</li>
                        <li>Request correction of inaccurate information</li>
                        <li>Request deletion of your information, subject to certain exceptions</li>
                        <li>Object to our processing of your information</li>
                        <li>Request restriction of processing in certain circumstances</li>
                    </ul>
                    
                    <h5>8. Cookies and Tracking Technologies</h5>
                    <p>8.1. We use cookies and similar tracking technologies to collect information about your browsing activities and to improve your experience on our platform.</p>
                    <p>8.2. You can set your browser to refuse all or some browser cookies, but this may affect your ability to use certain features of our platform.</p>
                    
                    <h5>9. Changes to This Privacy Policy</h5>
                    <p>9.1. We may update this Privacy Policy from time to time to reflect changes in our practices or for other operational, legal, or regulatory reasons.</p>
                    <p>9.2. We will notify you of any material changes by posting the new Privacy Policy on this page and updating the "Last Updated" date.</p>
                    
                    <h5>10. Contact Us</h5>
                    <p>10.1. If you have any questions or concerns about this Privacy Policy or our privacy practices, please contact us at:</p>
                    <p><strong>Email:</strong> <?php echo ADMIN_EMAIL; ?></p>
                    <p><strong>Address:</strong> Agape Youth Chama, P.O. Box 12345, Nairobi, Kenya</p>
                </div>
                <div class="card-footer text-center">
                    <p class="mb-0">Last updated: <?php echo date('F d, Y'); ?></p>
                    <a href="register.php" class="btn btn-primary mt-3">Back</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>