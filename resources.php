<?php
require_once 'config.php';
include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">Financial Resources</h2>
            <p class="lead">Access valuable resources to help you grow financially and make informed decisions.</p>
        </div>
    </div>
    
    <div class="row mb-5">
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Financial Education</h4>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <i class="fas fa-book-open mr-2 text-primary"></i>
                            <a href="basics/personal_finance.php" class="text-decoration-none">Basics of Personal Finance</a>
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-chart-line mr-2 text-primary"></i>
                            <a href="basics/intro_investments.php" class="text-decoration-none">Introduction to Investments</a>
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-piggy-bank mr-2 text-primary"></i>
                            <a href="basics/saving_strategy.php" class="text-decoration-none">Saving Strategies for Youth</a>
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-hand-holding-usd mr-2 text-primary"></i>
                            <a href="basics/debt_management.php" class="text-decoration-none">Debt Management Guide</a>
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-calculator mr-2 text-primary"></i>
                            <a href="basics/budgeting101.php" class="text-decoration-none">Budgeting 101</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">Business Development</h4>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <i class="fas fa-lightbulb mr-2 text-success"></i>
                            <a href="business/ideas.php" class="text-decoration-none">Business Ideas for Youth</a>
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-file-alt mr-2 text-success"></i>
                            <a href="business/plan.php" class="text-decoration-none">Business Plan Templates</a>
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-money-bill-wave mr-2 text-success"></i>
                            <a href="business/funding.php" class="text-decoration-none">Funding Opportunities</a>
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-users mr-2 text-success"></i>
                            <a href="business/networking.php" class="text-decoration-none">Networking Strategies</a>
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-bullhorn mr-2 text-success"></i>
                            <a href="business/marketing.php" class="text-decoration-none">Marketing on a Budget</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0">Tools & Calculators</h4>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <i class="fas fa-calculator mr-2 text-info"></i>
                            <a href="tools/savings_calculator.php" class="text-decoration-none">Savings Calculator</a>
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-percentage mr-2 text-info"></i>
                            <a href="tools/loan_calculator.php" class="text-decoration-none">Loan Repayment Calculator</a>
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-chart-pie mr-2 text-info"></i>
                            <a href="tools/budget_planner.php" class="text-decoration-none">Budget Planner</a>
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-coins mr-2 text-info"></i>
                            <a href="tools/investment_return.php" class="text-decoration-none">Investment Return Calculator</a>
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-file-invoice-dollar mr-2 text-info"></i>
                            <a href="tools/financial_goal.php" class="text-decoration-none">Financial Goal Tracker</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Upcoming Financial Workshops</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Workshop</th>
                                    <th>Facilitator</th>
                                    <th>Location</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>June 15, 2023</td>
                                    <td>Introduction to Stock Market</td>
                                    <td>Mr. John Kamau</td>
                                    <td>Agape Youth Center</td>
                                    <td><a href="#" class="btn btn-sm btn-outline-primary">Register</a></td>
                                </tr>
                                <tr>
                                    <td>June 22, 2023</td>
                                    <td>Digital Marketing for Small Businesses</td>
                                    <td>Ms. Sarah Ochieng</td>
                                    <td>Virtual (Zoom)</td>
                                    <td><a href="#" class="btn btn-sm btn-outline-primary">Register</a></td>
                                </tr>
                                <tr>
                                    <td>July 5, 2023</td>
                                    <td>Real Estate Investment for Beginners</td>
                                    <td>Mr. David Mwangi</td>
                                    <td>Agape Youth Center</td>
                                    <td><a href="#" class="btn btn-sm btn-outline-primary">Register</a></td>
                                </tr>
                                <tr>
                                    <td>July 18, 2023</td>
                                    <td>Financial Planning for Young Adults</td>
                                    <td>Mrs. Elizabeth Wanjiku</td>
                                    <td>Agape Youth Center</td>
                                    <td><a href="#" class="btn btn-sm btn-outline-primary">Register</a></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">Recommended Books</h4>
                </div>
                <div class="card-body">
                    <div class="media mb-3">
                        <img src="assets/images/book1.jpg" class="mr-3" alt="Book Cover" width="64">
                        <div class="media-body">
                            <h5 class="mt-0">Rich Dad Poor Dad</h5>
                            <p class="mb-1">By Robert Kiyosaki</p>
                            <p class="text-muted small">Learn about financial literacy and how to make money work for you.</p>
                        </div>
                    </div>
                    <div class="media mb-3">
                        <img src="assets/images/book2.jpg" class="mr-3" alt="Book Cover" width="64">
                        <div class="media-body">
                            <h5 class="mt-0">The Intelligent Investor</h5>
                            <p class="mb-1">By Benjamin Graham</p>
                            <p class="text-muted small">A practical guide to value investing strategies.</p>
                        </div>
                    </div>
                    <div class="media mb-3">
                        <img src="assets/images/book3.jpg" class="mr-3" alt="Book Cover" width="64">
                        <div class="media-body">
                            <h5 class="mt-0">Think and Grow Rich</h5>
                            <p class="mb-1">By Napoleon Hill</p>
                            <p class="text-muted small">Classic book on personal development and wealth creation.</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="#" class="btn btn-success btn-sm">View More Books</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0">Financial Partners</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4 mb-3">
                            <img src="assets/images/partner1.png" alt="Partner Logo" class="img-fluid mb-2" style="max-height: 60px;">
                            <p class="small">Equity Bank</p>
                        </div>
                        <div class="col-4 mb-3">
                            <img src="assets/images/partner2.png" alt="Partner Logo" class="img-fluid mb-2" style="max-height: 60px;">
                            <p class="small">KCB Bank</p>
                        </div>
                        <div class="col-4 mb-3">
                            <img src="assets/images/partner3.png" alt="Partner Logo" class="img-fluid mb-2" style="max-height: 60px;">
                            <p class="small">Safaricom</p>
                        </div>
                        <div class="col-4 mb-3">
                            <img src="assets/images/partner4.png" alt="Partner Logo" class="img-fluid mb-2" style="max-height: 60px;">
                            <p class="small">Co-operative Bank</p>
                        </div>
                        <div class="col-4 mb-3">
                            <img src="assets/images/partner5.png" alt="Partner Logo" class="img-fluid mb-2" style="max-height: 60px;">
                            <p class="small">M-Pesa</p>
                        </div>
                        <div class="col-4 mb-3">
                            <img src="assets/images/partner6.png" alt="Partner Logo" class="img-fluid mb-2" style="max-height: 60px;">
                            <p class="small">SACCO Society</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="#" class="btn btn-info btn-sm">Learn About Our Partners</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>