// Main JavaScript for Agape Youth Chama

// Document Ready Function
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Initialize popovers
    $('[data-toggle="popover"]').popover();
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a.smooth-scroll').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Financial charts initialization (if on dashboard page)
    if (document.getElementById('savingsChart')) {
        initializeSavingsChart();
    }
    
    if (document.getElementById('contributionsChart')) {
        initializeContributionsChart();
    }
});

// Initialize Savings Growth Chart
function initializeSavingsChart() {
    const ctx = document.getElementById('savingsChart').getContext('2d');
    
    // Sample data - this would be replaced with actual data from the backend
    const savingsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Savings Growth (KSh)',
                data: [5000, 8000, 12000, 14000, 16000, 19000, 22000, 25000, 28000, 32000, 36000, 40000],
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(0, 123, 255, 1)',
                pointRadius: 4,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'KSh ' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'KSh ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// Initialize Monthly Contributions Chart
function initializeContributionsChart() {
    const ctx = document.getElementById('contributionsChart').getContext('2d');
    
    // Sample data - this would be replaced with actual data from the backend
    const contributionsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Monthly Contributions (KSh)',
                data: [2000, 2000, 3000, 2000, 2000, 3000, 3000, 2000, 3000, 4000, 4000, 4000],
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'KSh ' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'KSh ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// Form validation for contribution form
if (document.getElementById('contributionForm')) {
    document.getElementById('contributionForm').addEventListener('submit', function(e) {
        const amountInput = document.getElementById('contributionAmount');
        const amount = parseFloat(amountInput.value);
        const minAmount = parseFloat(amountInput.getAttribute('min'));
        
        if (isNaN(amount) || amount < minAmount) {
            e.preventDefault();
            document.getElementById('amountError').textContent = `Contribution must be at least KSh ${minAmount.toLocaleString()}`;
            amountInput.classList.add('is-invalid');
        }
    });
}

// Notification system
function showNotification(message, type = 'info') {
    const notificationArea = document.getElementById('notificationArea');
    if (!notificationArea) return;
    
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show`;
    notification.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    `;
    
    notificationArea.appendChild(notification);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}

// Initialize loan calculator if on loan page
if (document.getElementById('loanCalculator')) {
    initializeLoanCalculator();
}

function initializeLoanCalculator() {
    const loanAmountInput = document.getElementById('loanAmount');
    const loanTermInput = document.getElementById('loanTerm');
    const interestRateInput = document.getElementById('interestRate');
    const calculateBtn = document.getElementById('calculateLoan');
    const monthlyPaymentOutput = document.getElementById('monthlyPayment');
    const totalPaymentOutput = document.getElementById('totalPayment');
    const totalInterestOutput = document.getElementById('totalInterest');
    
    calculateBtn.addEventListener('click', function() {
        const principal = parseFloat(loanAmountInput.value);
        const term = parseInt(loanTermInput.value);
        const annualRate = parseFloat(interestRateInput.value);
        
        if (isNaN(principal) || isNaN(term) || isNaN(annualRate)) {
            showNotification('Please enter valid numbers for all fields', 'danger');
            return;
        }
        
        // Convert annual rate to monthly rate and convert percentage to decimal
        const monthlyRate = annualRate / 100 / 12;
        
        // Calculate monthly payment using loan formula
        const monthlyPayment = principal * monthlyRate * Math.pow(1 + monthlyRate, term) / (Math.pow(1 + monthlyRate, term) - 1);
        
        // Calculate total payment and interest
        const totalPayment = monthlyPayment * term;
        const totalInterest = totalPayment - principal;
        
        // Display results
        monthlyPaymentOutput.textContent = 'KSh ' + monthlyPayment.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        totalPaymentOutput.textContent = 'KSh ' + totalPayment.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        totalInterestOutput.textContent = 'KSh ' + totalInterest.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        
        // Show the results section
        document.getElementById('loanResults').classList.remove('d-none');
    });
}

// Handle file uploads with preview
if (document.querySelector('.custom-file-input')) {
    document.querySelectorAll('.custom-file-input').forEach(input => {
        input.addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'Choose file';
            this.nextElementSibling.textContent = fileName;
            
            // If there's an image preview element and the file is an image
            const previewElement = document.getElementById(this.getAttribute('data-preview'));
            if (previewElement && this.files[0] && this.files[0].type.match('image.*')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewElement.src = e.target.result;
                    previewElement.classList.remove('d-none');
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
}

// Handle dynamic form fields (for adding multiple items)
if (document.getElementById('addFieldBtn')) {
    document.getElementById('addFieldBtn').addEventListener('click', function() {
        const container = document.getElementById('dynamicFieldsContainer');
        const fieldCount = container.children.length;
        
        const newField = document.createElement('div');
        newField.className = 'dynamic-field mb-3 row';
        newField.innerHTML = `
            <div class="col-md-5">
                <input type="text" name="fieldName[]" class="form-control" placeholder="Field Name">
            </div>
            <div class="col-md-5">
                <input type="text" name="fieldValue[]" class="form-control" placeholder="Field Value">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger btn-sm remove-field">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        container.appendChild(newField);
        
        // Add event listener to the remove button
        newField.querySelector('.remove-field').addEventListener('click', function() {
            container.removeChild(newField);
        });
    });
}

// Handle confirmation dialogs
document.querySelectorAll('[data-confirm]').forEach(element => {
    element.addEventListener('click', function(e) {
        const message = this.getAttribute('data-confirm');
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
});

// Handle tabs with URL hash
if (document.querySelector('.nav-tabs')) {
    // Activate tab based on URL hash
    const hash = window.location.hash;
    if (hash) {
        const tabTrigger = document.querySelector(`.nav-tabs a[href="${hash}"]`);
        if (tabTrigger) {
            tabTrigger.click();
        }
    }
    
    // Update URL hash when tab changes
    document.querySelectorAll('.nav-tabs a').forEach(tab => {
        tab.addEventListener('click', function() {
            history.pushState(null, null, this.getAttribute('href'));
        });
    });
}

// Initialize datepickers
if (document.querySelector('.datepicker')) {
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true
    });
}

// Initialize timepickers
if (document.querySelector('.timepicker')) {
    $('.timepicker').timepicker({
        showMeridian: true,
        icons: {
            up: 'fa fa-chevron-up',
            down: 'fa fa-chevron-down'
        }
    });
}

// Handle print functionality
if (document.getElementById('printBtn')) {
    document.getElementById('printBtn').addEventListener('click', function() {
        window.print();
    });
}

// Export table to CSV
if (document.getElementById('exportCsvBtn')) {
    document.getElementById('exportCsvBtn').addEventListener('click', function() {
        const table = document.getElementById(this.getAttribute('data-table'));
        if (!table) return;
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (let i = 0; i < rows.length; i++) {
            let row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length; j++) {
                // Replace any commas in the cell content with spaces to avoid CSV issues
                let text = cols[j].innerText.replace(/,/g, ' ');
                // Wrap in quotes to handle any special characters
                row.push('"' + text + '"');
            }
            
            csv.push(row.join(','));
        }
        
        // Download CSV file
        const csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', 'agape_chama_export.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
}