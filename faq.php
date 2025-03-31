<?php
require_once 'config.php';
include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-12 text-center mb-5">
            <h2 class="display-4">Frequently Asked Questions</h2>
            <p class="lead">Find answers to common questions about Agape Youth Group.</p>
        </div>
    </div>
    
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="accordion" id="faqAccordion">
                <!-- Membership Questions -->
                <div class="card mb-3">
                    <div class="card-header bg-light" id="headingOne">
                        <h5 class="mb-0">
                            <button class="btn btn-link text-dark" type="button" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                <i class="fas fa-users mr-2"></i> Membership Questions
                            </button>
                        </h5>
                    </div>

                    <div id="collapseOne" class="collapse show" aria-labelledby="headingOne" data-parent="#faqAccordion">
                        <div class="card-body">
                            <div class="faq-item mb-4">
                                <h5>Who can join Agape Youth Group?</h5>
                                <p>Agape Youth Group is open to young people between the ages of 18 and 35 years. We welcome members from all backgrounds who are committed to financial growth and community development.</p>
                            </div>
                            
                            <div class="faq-item mb-4">
                                <h5>How do I become a member?</h5>
                                <p>To become a member, you need to:</p>
                                <ol>
                                    <li>Complete the online registration form on our website</li>
                                    <li>Pay the one-time registration fee of KES 500</li>
                                    <li>Attend an orientation session (held monthly)</li>
                                    <li>Begin making your monthly contributions</li>
                                </ol>
                                <p>You can <a href="register.php">register here</a>.</p>
                            </div>
                            
                            <div class="faq-item mb-4">
                                <h5>Is there a membership fee?</h5>
                                <p>Yes, there is a one-time registration fee of KES 500 to join Agape Youth Group. This fee covers administrative costs and your welcome package.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h5>Can I leave the group if I need to?</h5>
                                <p>Yes, members can withdraw from the group by providing a 30-day written notice. Upon withdrawal, you will receive your savings less any outstanding loans or fees. The processing of withdrawal requests may take up to 60 days.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Savings Questions -->
                <div class="card mb-3">
                    <div class="card-header bg-light" id="headingTwo">
                        <h5 class="mb-0">
                            <button class="btn btn-link text-dark collapsed" type="button" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                <i class="fas fa-piggy-bank mr-2"></i> Savings Questions
                            </button>
                        </h5>
                    </div>
                    <div id="collapseTwo" class="collapse" aria-labelledby="headingTwo" data-parent="#faqAccordion">
                        <div class="card-body">
                            <div class="faq-item mb-4">
                                <h5>How much do I need to contribute monthly?</h5>
                                <p>The minimum monthly contribution is KES <?php echo number_format(MIN_CONTRIBUTION); ?>. However, members are encouraged to save more if they can. There is no upper limit to how much you can contribute.</p>
                            </div>
                            
                            <div class="faq-item mb-4">
                                <h5>When are monthly contributions due?</h5>
                                <p>Monthly contributions should be made by the 5th day of each month. Late contributions may attract a penalty fee of KES 100.</p>
                            </div>
                            
                            <div class="faq-item mb-4">
                                <h5>How do I make my contributions?</h5>
                                <p>Contributions can be made through:</p>
                                <ul>
                                    <li>M-Pesa (Paybill Number: 123456, Account: Your Membership Number)</li>
                                    <li>Direct bank deposit to our account</li>
                                    <li>Cash payment during monthly meetings</li>
                                </ul>
                                <p>After making your contribution, you should record it in your member portal.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h5>Do my savings earn interest?</h5>
                                <p>Yes, members' savings earn dividends based on the group's performance. Dividends are calculated quarterly and distributed annually. The rate varies depending on our investment returns, but has historically ranged between 8-12% annually.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Loan Questions -->
                <div class="card mb-3">
                    <div class="card-header bg-light" id="headingThree">
                        <h5 class="mb-0">
                            <button class="btn btn-link text-dark collapsed" type="button" data-toggle="collapse" data-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                <i class="fas fa-hand-holding-usd mr-2"></i> Loan Questions
                            </button>
                        </h5>
                    </div>
                    <div id="collapseThree" class="collapse" aria-labelledby="headingThree" data-parent="#faqAccordion">
                        <div class="card-body">
                            <div class="faq-item mb-4">
                                <h5>When can I apply for a loan?</h5>
                                <p>Members become eligible for loans after making consistent monthly contributions for at least three months.</p>
                            </div>
                            
                            <div class="faq-item mb-4">
                                <h5>How much can I borrow?</h5>
                                <p>You can borrow up to <?php echo MAX_LOAN_MULTIPLIER; ?> times your total savings. For example, if you have saved KES 10,000, you can borrow up to KES <?php echo number_format(10000 * MAX_LOAN_MULTIPLIER); ?>.</p>
                            </div>
                            
                            <div class="faq-item mb-4">
                                <h5>What is the interest rate on loans?</h5>
                                <p>Our loans have a competitive interest rate of <?php echo LOAN_INTEREST_RATE; ?>% per month on a reducing balance. This rate is significantly lower than what most financial institutions offer.</p>
                            </div>
                            
                            <div class="faq-item mb-4">
                                <h5>What can I use the loan for?</h5>
                                <p>Loans can be used for:</p>
                                <ul>
                                    <li>Education expenses (tuition, books, etc.)</li>
                                    <li>Business startup or expansion</li>
                                    <li>Asset acquisition (land, equipment, etc.)</li>
                                    <li>Emergency needs (medical, etc.)</li>
                                </ul>
                                <p>We encourage productive use of loans that will generate returns or build your future.</p>
                            </div>
                            
                            <div class="faq-item mb-4">
                                <h5>How long does loan approval take?</h5>
                                <p>Standard loans are typically processed within 7 days. Emergency loans can be processed within 24-48 hours, subject to availability of funds.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h5>What happens if I can't repay my loan on time?</h5>
                                <p>If you anticipate difficulty in repaying your loan, we encourage you to contact us immediately to discuss restructuring options. Late payments incur a penalty of 5% of the outstanding amount. Persistent default may affect your future borrowing eligibility and could result in recovery from your savings.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Meetings and Activities -->
                <div class="card mb-3">
                    <div class="card-header bg-light" id="headingFour">
                        <h5 class="mb-0">
                            <button class="btn btn-link text-dark collapsed" type="button" data-toggle="collapse" data-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                <i class="fas fa-calendar-alt mr-2"></i> Meetings and Activities
                            </button>
                        </h5>
                    </div>
                    <div id="collapseFour" class="collapse" aria-labelledby="headingFour" data-parent="#faqAccordion">
                        <div class="card-body">
                            <div class="faq-item mb-4">
                                <h5>How often does the group meet?</h5>
                                <p>We hold monthly general meetings on the first Saturday of each month from 2:00 PM to 4:00 PM. Additional committee meetings and special events are scheduled as needed.</p>
                            </div>
                            
                            <div class="faq-item mb-4">
                                <h5>Is attendance at meetings mandatory?</h5>
                                <p>While we strongly encourage attendance at monthly meetings, we understand that members may have other commitments. However, consistent absence from meetings (missing three consecutive meetings without valid reason) may affect your standing in the group.</p>
                            </div>
                            
                            <div class="faq-item mb-4">
                                <h5>What happens during monthly meetings?</h5>
                                <p>Monthly meetings typically include:</p>
                                <ul>
                                    <li>Updates on group finances and activities</li>
                                    <li>Discussion of new investment opportunities</li>
                                    <li>Financial literacy training sessions</li>
                                    <li>Member networking and experience sharing</li>
                                    <li>Planning for upcoming events and projects</li>
                                </ul>
                            </div>
                            
                            <div class="faq-item">
                                <h5>Can I participate remotely if I can't attend in person?</h5>
                                <p>Yes, we offer virtual participation options for members who cannot attend in person. Meeting links are shared via email and our member portal before each meeting.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Investment Questions -->
                <div class="card mb-3">
                    <div class="card-header bg-light" id="headingFive">
                        <h5 class="mb-0">
                            <button class="btn btn-link text-dark collapsed" type="button" data-toggle="collapse" data-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                                <i class="fas fa-chart-line mr-2"></i> Investment Questions
                            </button>
                        </h5>
                    </div>
                    <div id="collapseFive" class="collapse" aria-labelledby="headingFive" data-parent="#faqAccordion">
                        <div class="card-body">
                            <div class="faq-item mb-4">
                                <h5>How does the group invest its funds?</h5>
                                <p>Agape Youth Group invests in a diversified portfolio that includes:</p>
                                <ul>
                                    <li>Member loans (primary investment)</li>
                                    <li>Fixed deposits with financial institutions</li>
                                    <li>Government securities (Treasury bills and bonds)</li>
                                    <li>Real estate projects</li>
                                    <li>Group business ventures</li>
                                </ul>
                                <p>All investment decisions are made transparently and with member input.</p>
                            </div>
                            
                            <div class="faq-item mb-4">
                                <h5>Can I suggest investment opportunities?</h5>
                                <p>Yes! We encourage members to propose viable investment opportunities. Proposals are reviewed by the Investment Committee and presented to members for consideration if they meet our criteria for profitability, risk, and alignment with our values.</p>
                            </div>
                            
                            <div class="faq-item mb-4">
                                <h5>How are investment returns distributed?</h5>
                                <p>Investment returns are distributed in several ways:</p>
                                <ul>
                                    <li>Quarterly dividends credited to member accounts</li>
                                    <li>Annual bonus based on individual savings</li>
                                    <li>Reinvestment into group projects (as approved by members)</li>
                                    <li>Community development initiatives</li>
                                </ul>
                            </div>
                            
                            <div class="faq-item">
                                <h5>What is the average return on investment?</h5>
                                <p>Our historical average return on investment has been 12-15% annually. However, returns can vary based on economic conditions and the performance of our investment portfolio.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Technical Questions -->
                <div class="card mb-3">
                    <div class="card-header bg-light" id="headingSix">
                        <h5 class="mb-0">
                            <button class="btn btn-link text-dark collapsed" type="button" data-toggle="collapse" data-target="#collapseSix" aria-expanded="false" aria-controls="collapseSix">
                                <i class="fas fa-laptop mr-2"></i> Technical & Account Questions
                            </button>
                        </h5>
                    </div>
                    <div id="collapseSix" class="collapse" aria-labelledby="headingSix" data-parent="#faqAccordion">
                        <div class="card-body">
                            <div class="faq-item mb-4">
                                <h5>How do I access my account online?</h5>
                                <p>You can access your account by logging in to our member portal using your username and password. If you've forgotten your password, you can reset it using the "Forgot Password" link on the login page.</p>
                            </div>
                            
                            <div class="faq-item mb-4">
                                <h5>Is my personal and financial information secure?</h5>
                                <p>Yes, we take data security seriously. Our platform uses encryption and secure protocols to protect your information. We never share your personal data with third parties without your consent.</p>
                            </div>
                            
                            <div class="faq-item mb-4">
                                <h5>How do I update my contact information?</h5>
                                <p>You can update your contact information by logging into your account and navigating to the "Profile" section. It's important to keep your information current so you don't miss important communications.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h5>What should I do if I notice an error in my account?</h5>
                                <p>If you notice any discrepancies in your account, please contact our support team immediately at support@agapeyouthchama.org or call +254 712 345 678. We aim to resolve all issues within 48 hours.</p>
                    </div>
    
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card bg-light">
                <div class="card-body text-center p-5">
                    <h3>Still have questions?</h3>
                    <p class="lead mb-4">We're here to help! Reach out to us through any of these channels:</p>
                    <div class="row justify-content-center">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-envelope fa-3x text-primary mb-3"></i>
                                    <h5>Email Us</h5>
                                    <p>info@agapeyouthchama.org</p>
                                    <p class="small text-muted">We respond within 24 hours</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-phone fa-3x text-success mb-3"></i>
                                    <h5>Call Us</h5>
                                    <p>+254 712 345 678</p>
                                    <p class="small text-muted">Mon-Fri, 8:00 AM - 5:00 PM</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-comments fa-3x text-info mb-3"></i>
                                    <h5>Live Chat</h5>
                                    <p>Chat with our support team</p>
                                    <p class="small text-muted">Available on our website</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <a href="contact.php" class="btn btn-primary btn-lg mt-4">Contact Us</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>