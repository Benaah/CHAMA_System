<?php
require_once 'config.php';
include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-12 text-center mb-5">
            <h2 class="display-4">Our Programs</h2>
            <p class="lead">Empowering youth through financial literacy, entrepreneurship, and community development.</p>
        </div>
    </div>
    
    <!-- Savings Program -->
    <div class="row mb-5">
        <div class="col-md-6 order-md-2">
            <img src="assets/images/savings-program.jpg" alt="Savings Program" class="img-fluid rounded shadow-sm">
        </div>
        <div class="col-md-6 order-md-1">
            <div class="program-info">
                <h3 class="text-primary">Savings Program</h3>
                <p class="lead">Building a foundation for financial security through regular savings.</p>
                <p>Our core savings program encourages members to develop consistent saving habits. Members contribute a minimum of <?php echo number_format(MIN_CONTRIBUTION, 2, '.', ','); ?> monthly, building their financial base while contributing to the group's collective strength.</p>
                <h5>Program Features:</h5>
                <ul>
                    <li>Flexible monthly contributions (minimum <?php echo number_format(MIN_CONTRIBUTION, 2, '.', ','); ?>)</li>
                    <li>Transparent tracking of individual savings</li>
                    <li>Quarterly dividends based on group performance</li>
                    <li>Annual bonus for consistent savers</li>
                    <li>Emergency fund access for unexpected needs</li>
                </ul>
                <a href="register.php" class="btn btn-primary mt-3">Join Our Savings Program</a>
            </div>
        </div>
    </div>
    
    <!-- Loan Program -->
    <div class="row mb-5">
        <div class="col-md-6">
            <img src="assets/images/loan-program.jpg" alt="Loan Program" class="img-fluid rounded shadow-sm">
        </div>
        <div class="col-md-6">
            <div class="program-info">
                <h3 class="text-success">Loan Program</h3>
                <p class="lead">Accessible and affordable credit for education and entrepreneurship.</p>
                <p>Our loan program provides members with access to affordable credit for education, business ventures, and personal development. Loans are available to members who have consistently contributed for at least three months.</p>
                <h5>Program Features:</h5>
                <ul>
                    <li>Low interest rate of <?php echo LOAN_INTEREST_RATE; ?>%</li>
                    <li>Loan amounts up to <?php echo MAX_LOAN_MULTIPLIER; ?>x your savings</li>
                    <li>Flexible repayment periods (3-12 months)</li>
                    <li>No collateral required for small loans</li>
                    <li>Quick approval process (within 7 days)</li>
                    <li>Business plan support for entrepreneurship loans</li>
                </ul>
                <a href="loans.php" class="btn btn-success mt-3">Learn More About Loans</a>
            </div>
        </div>
    </div>
    
    <!-- Entrepreneurship Program -->
    <div class="row mb-5">
        <div class="col-md-6 order-md-2">
            <img src="assets/images/entrepreneurship-program.jpg" alt="Entrepreneurship Program" class="img-fluid rounded shadow-sm">
        </div>
        <div class="col-md-6 order-md-1">
            <div class="program-info">
                <h3 class="text-info">Entrepreneurship Program</h3>
                <p class="lead">Nurturing the next generation of business leaders.</p>
                <p>Our entrepreneurship program supports young people with innovative business ideas through mentorship, training, and startup funding. We believe in the power of youth-led businesses to transform communities.</p>
                <h5>Program Features:</h5>
                <ul>
                    <li>Business plan development workshops</li>
                    <li>Mentorship from established entrepreneurs</li>
                    <li>Seed funding for promising ventures</li>
                    <li>Marketing and branding support</li>
                    <li>Networking opportunities with potential investors</li>
                    <li>Quarterly business showcase events</li>
                </ul>
                <a href="#" class="btn btn-info mt-3">Join Entrepreneurship Program</a>
            </div>
        </div>
    </div>
    
    <!-- Financial Literacy Program -->
    <div class="row mb-5">
        <div class="col-md-6">
            <img src="assets/images/financial-literacy.jpg" alt="Financial Literacy Program" class="img-fluid rounded shadow-sm">
        </div>
        <div class="col-md-6">
            <div class="program-info">
                <h3 class="text-warning">Financial Literacy Program</h3>
                <p class="lead">Equipping youth with essential money management skills.</p>
                <p>Our financial literacy program provides members with the knowledge and skills needed to make informed financial decisions. Through workshops, seminars, and one-on-one coaching, we help young people develop healthy financial habits.</p>
                <h5>Program Features:</h5>
                <ul>
                    <li>Monthly financial literacy workshops</li>
                    <li>Personal budgeting assistance</li>
                    <li>Investment education seminars</li>
                    <li>Debt management strategies</li>
                    <li>Financial goal setting and planning</li>
                    <li>Access to financial planning tools and resources</li>
                </ul>
                <a href="resources.php" class="btn btn-warning mt-3 text-white">Access Financial Resources</a>
            </div>
        </div>
    </div>
    
    <!-- Community Development Program -->
    <div class="row mb-5">
        <div class="col-md-6 order-md-2">
            <img src="assets/images/community-program.jpg" alt="Community Development Program" class="img-fluid rounded shadow-sm">
        </div>
        <div class="col-md-6 order-md-1">
            <div class="program-info">
                <h3 class="text-danger">Community Development Program</h3>
                <p class="lead">Giving back to create sustainable impact in our communities.</p>
                <p>Our community development program focuses on giving back to the community through various initiatives. We believe that true success comes when we lift others as we rise, and we dedicate a portion of our resources to community projects.</p>
                <h5>Program Features:</h5>
                <ul>
                    <li>Quarterly community service projects</li>
                    <li>Educational support for underprivileged children</li>
                    <li>Environmental conservation initiatives</li>
                    <li>Health awareness campaigns</li>
                    <li>Skills training for vulnerable youth</li>
                    <li>Partnerships with local schools and community organizations</li>
                </ul>
                <a href="#" class="btn btn-danger mt-3">Join Community Initiatives</a>
            </div>
        </div>
    </div>
    
    <!-- Agriculture Program -->
    <div class="row mb-5">
        <div class="col-md-6">
            <img src="assets/images/agriculture-program.jpg" alt="Agriculture Program" class="img-fluid rounded shadow-sm">
        </div>
        <div class="col-md-6">
            <div class="program-info">
                <h3 class="text-success">Youth in Agriculture Program</h3>
                <p class="lead">Cultivating opportunities in modern farming and agribusiness.</p>
                <p>Our agriculture program aims to change the perception of farming among youth by introducing modern, profitable, and sustainable agricultural practices. We provide training, resources, and market linkages for young farmers.</p>
                <h5>Program Features:</h5>
                <ul>
                    <li>Training in modern farming techniques</li>
                    <li>Access to affordable farm inputs</li>
                    <li>Greenhouse farming projects</li>
                    <li>Value addition training</li>
                    <li>Market linkages for agricultural products</li>
                    <li>Group farming initiatives on leased land</li>
                </ul>
                <a href="#" class="btn btn-success mt-3">Explore Agribusiness Opportunities</a>
            </div>
        </div>
    </div>
    
    <!-- Program Calendar -->
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Upcoming Program Activities</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Program</th>
                                    <th>Activity</th>
                                    <th>Location</th>
                                    <th>Registration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>June 10, 2023</td>
                                    <td>Financial Literacy</td>
                                    <td>Investment Basics Workshop</td>
                                    <td>Agape Youth Center</td>
                                    <td><a href="#" class="btn btn-sm btn-outline-primary">Register</a></td>
                                </tr>
                                <tr>
                                    <td>June 17, 2023</td>
                                    <td>Entrepreneurship</td>
                                    <td>Business Plan Competition</td>
                                    <td>Luanda Town Hall</td>
                                    <td><a href="#" class="btn btn-sm btn-outline-primary">Register</a></td>
                                </tr>
                                <tr>
                                    <td>June 24, 2023</td>
                                    <td>Community Development</td>
                                    <td>Tree Planting Initiative</td>
                                    <td>Emabungo Primary School</td>
                                    <td><a href="#" class="btn btn-sm btn-outline-primary">Volunteer</a></td>
                                </tr>
                                <tr>
                                    <td>July 1, 2023</td>
                                    <td>Agriculture</td>
                                    <td>Greenhouse Farming Training</td>
                                    <td>Demonstration Farm</td>
                                    <td><a href="#" class="btn btn-sm btn-outline-primary">Register</a></td>
                                </tr>
                                <tr>
                                    <td>July 8, 2023</td>
                                    <td>Savings Program</td>
                                    <td>Quarterly Members Meeting</td>
                                    <td>Agape Youth Center</td>
                                    <td><a href="#" class="btn btn-sm btn-outline-primary">RSVP</a></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Success Stories -->
    <div class="row mb-5">
        <div class="col-md-12 text-center mb-4">
            <h3>Success Stories</h3>
            <p class="lead">See how our programs have transformed the lives of our members</p>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <img src="assets/images/success1.jpg" class="card-img-top" alt="Success Story">
                <div class="card-body">
                    <h5 class="card-title">Jane's Poultry Business</h5>
                    <p class="card-text">"With a loan of KES 50,000 from Agape Youth Group, I started my poultry business that now supplies eggs to local hotels. My income has tripled, and I've employed two other youth from our community."</p>
                    <p class="text-muted">- Jane Adhiambo, Member since 2019</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <img src="assets/images/success2.jpg" class="card-img-top" alt="Success Story">
                <div class="card-body">
                    <h5 class="card-title">Michael's Education Journey</h5>
                    <p class="card-text">"I couldn't afford my college fees, but through Agape's education loan program, I completed my diploma in IT. Now I work as a software developer and mentor other youth in our group."</p>
                    <p class="text-muted">- Michael Kimani, Member since 2018</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <img src="assets/images/success3.jpg" class="card-img-top" alt="Success Story">
                <div class="card-body">
                    <h5 class="card-title">Grace's Savings Milestone</h5>
                    <p class="card-text">"I never thought I could save money consistently, but the Agape savings program changed that. In two years, I've saved enough to buy a plot of land and start building my own home."</p>
                    <p class="text-muted">- Grace Wanjiku, Member since 2020</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Call to Action -->
    <div class="row">
        <div class="col-md-12">
            <div class="card bg-primary text-white">
                <div class="card-body text-center py-5">
                    <h3 class="mb-3">Ready to Join Our Programs?</h3>
                    <p class="lead mb-4">Take the first step towards financial empowerment and community impact.</p>
                    <a href="register.php" class="btn btn-light btn-lg mr-3">Register Now</a>
                    <a href="contact.php" class="btn btn-outline-light btn-lg">Contact Us</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>