<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service | ConsultPro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Primary Colors */
            --primary-50: #eff6ff;
            --primary-100: #dbeafe;
            --primary-200: #bfdbfe;
            --primary-300: #93c5fd;
            --primary-400: #60a5fa;
            --primary-500: #3b82f6;
            --primary-600: #2563eb;
            --primary-700: #1d4ed8;
            --primary-800: #1e40af;
            --primary-900: #1e3a8a;
            --primary-950: #172554;
            
            /* Secondary Colors */
            --secondary-50: #f5f3ff;
            --secondary-100: #ede9fe;
            --secondary-200: #ddd6fe;
            --secondary-300: #c4b5fd;
            --secondary-400: #a78bfa;
            --secondary-500: #8b5cf6;
            --secondary-600: #7c3aed;
            --secondary-700: #6d28d9;
            --secondary-800: #5b21b6;
            --secondary-900: #4c1d95;
            --secondary-950: #2e1065;
            
            /* Gray Colors */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --gray-950: #030712;
            
            /* UI Variables */
            --border-radius-sm: 0.375rem;
            --border-radius: 0.5rem;
            --border-radius-md: 0.75rem;
            --border-radius-lg: 1rem;
            
            --box-shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --box-shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --box-shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            
            --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-700);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            color: white;
            padding: 40px 0;
            text-align: center;
            border-radius: var(--border-radius-lg);
            margin-bottom: 40px;
            box-shadow: var(--box-shadow-lg);
        }

        header h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        header p {
            font-size: 1.1rem;
            max-width: 800px;
            margin: 0 auto;
            opacity: 0.9;
        }

        .terms-container {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-md);
            padding: 40px;
            margin-bottom: 40px;
        }

        .terms-section {
            margin-bottom: 30px;
        }

        .terms-section:last-child {
            margin-bottom: 0;
        }

        .terms-section h2 {
            color: var(--primary-700);
            font-size: 1.5rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-100);
        }

        .terms-section h3 {
            color: var(--primary-600);
            font-size: 1.2rem;
            margin: 20px 0 10px;
        }

        .terms-section p, .terms-section ul, .terms-section ol {
            margin-bottom: 15px;
        }

        .terms-section ul, .terms-section ol {
            padding-left: 20px;
        }

        .terms-section li {
            margin-bottom: 8px;
        }

        .highlight-box {
            background-color: var(--primary-50);
            border-left: 4px solid var(--primary-500);
            padding: 15px;
            margin: 20px 0;
            border-radius: var(--border-radius);
        }

        .highlight-box.warning {
            background-color: #fff3cd;
            border-left-color: #ffc107;
        }

        .highlight-box.info {
            background-color: #cff4fc;
            border-left-color: #0dcaf0;
        }

        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background-color: var(--primary-600);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: var(--box-shadow-md);
            transition: var(--transition);
            opacity: 0;
            visibility: hidden;
        }

        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
        }

        .back-to-top:hover {
            background-color: var(--primary-700);
            transform: translateY(-5px);
        }

        .table-of-contents {
            background-color: var(--gray-100);
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
        }

        .table-of-contents h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--gray-800);
        }

        .table-of-contents ul {
            list-style-type: none;
            padding-left: 0;
        }

        .table-of-contents li {
            margin-bottom: 10px;
        }

        .table-of-contents a {
            color: var(--primary-600);
            text-decoration: none;
            transition: var(--transition);
            display: inline-block;
        }

        .table-of-contents a:hover {
            color: var(--primary-800);
            transform: translateX(5px);
        }

        .last-updated {
            text-align: center;
            margin-top: 40px;
            color: var(--gray-500);
            font-size: 0.9rem;
        }

        .footer {
            text-align: center;
            padding: 30px 0;
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .footer a {
            color: var(--primary-600);
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            header {
                padding: 30px 15px;
            }
            
            header h1 {
                font-size: 2rem;
            }
            
            .terms-container {
                padding: 25px;
            }
            
            .terms-section h2 {
                font-size: 1.3rem;
            }
            
            .terms-section h3 {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Terms of Service</h1>
            <p>Please read these terms and conditions carefully before using ConsultPro</p>
        </header>
        
        <div class="terms-container">
            <div class="table-of-contents">
                <h3>Table of Contents</h3>
                <ul>
                    <li><a href="#introduction">1. Introduction</a></li>
                    <li><a href="#definitions">2. Definitions</a></li>
                    <li><a href="#account-registration">3. Account Registration</a></li>
                    <li><a href="#user-conduct">4. User Conduct</a></li>
                    <li><a href="#consultations">5. Consultations</a></li>
                    <li><a href="#payments">6. Payments and Fees</a></li>
                    <li><a href="#withdrawals">7. Withdrawals</a></li>
                    <li><a href="#reporting">8. Reporting System</a></li>
                    <li><a href="#account-suspension">9. Account Suspension and Termination</a></li>
                    <li><a href="#privacy">10. Privacy and Data Protection</a></li>
                    <li><a href="#intellectual-property">11. Intellectual Property</a></li>
                    <li><a href="#limitation-liability">12. Limitation of Liability</a></li>
                    <li><a href="#modifications">13. Modifications to Terms</a></li>
                    <li><a href="#governing-law">14. Governing Law</a></li>
                    <li><a href="#contact">15. Contact Information</a></li>
                </ul>
            </div>
            
            <section id="introduction" class="terms-section">
                <h2>1. Introduction</h2>
                <p>Welcome to ConsultPro. These Terms of Service ("Terms") govern your access to and use of the ConsultPro website, services, and applications (collectively, the "Service"). By accessing or using the Service, you agree to be bound by these Terms. If you do not agree to these Terms, you may not access or use the Service.</p>
                <p>ConsultPro is a platform that connects clients seeking expert advice with qualified experts across various fields. Our mission is to facilitate valuable knowledge exchange and professional consultations in a secure and efficient environment.</p>
            </section>
            
            <section id="definitions" class="terms-section">
                <h2>2. Definitions</h2>
                <p>Throughout these Terms, the following definitions apply:</p>
                <ul>
                    <li><strong>"Service"</strong> refers to the ConsultPro platform, including the website, applications, and all features offered.</li>
                    <li><strong>"User"</strong> refers to any individual who accesses or uses the Service, including Clients and Experts.</li>
                    <li><strong>"Client"</strong> refers to a User who seeks consultations or advice from Experts.</li>
                    <li><strong>"Expert"</strong> refers to a User who provides consultations or advice to Clients.</li>
                    <li><strong>"Consultation"</strong> refers to the exchange of information, advice, or guidance between a Client and an Expert.</li>
                    <li><strong>"Content"</strong> refers to all information, text, graphics, photos, videos, or other materials uploaded, downloaded, or appearing on the Service.</li>
                    <li><strong>"Admin"</strong> refers to ConsultPro staff members who manage and oversee the platform.</li>
                </ul>
            </section>
            
            <section id="account-registration" class="terms-section">
                <h2>3. Account Registration</h2>
                <p>To access certain features of the Service, you must register for an account. When registering, you agree to provide accurate, current, and complete information. You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account.</p>
                
                <h3>3.1 Account Types</h3>
                <p>ConsultPro offers two types of user accounts:</p>
                <ul>
                    <li><strong>Client Account:</strong> For users seeking expert advice and consultations.</li>
                    <li><strong>Expert Account:</strong> For professionals offering their expertise and consultations.</li>
                </ul>
                
                <h3>3.2 Expert Verification</h3>
                <p>Expert accounts are subject to verification by ConsultPro administrators. Experts must provide accurate professional information, credentials, and qualifications. ConsultPro reserves the right to reject expert applications that do not meet our standards.</p>
                
                <h3>3.3 Expert Account Rejection</h3>
                <p>If an expert account is rejected by the administration, the expert will still be able to connect to their account and update their information to address the reasons for rejection. Once updated, the account will be reviewed again for approval.</p>
                
                <h3>3.4 Account Security</h3>
                <p>You are responsible for safeguarding your account and password. ConsultPro cannot and will not be liable for any loss or damage arising from your failure to comply with this security obligation.</p>
            </section>
            
            <section id="user-conduct" class="terms-section">
                <h2>4. User Conduct</h2>
                <p>By using ConsultPro, you agree to adhere to the following conduct guidelines:</p>
                
                <h3>4.1 General Conduct</h3>
                <ul>
                    <li>You will not use the Service for any illegal purpose or in violation of any laws.</li>
                    <li>You will not post or transmit any Content that is unlawful, harmful, threatening, abusive, harassing, defamatory, vulgar, obscene, or otherwise objectionable.</li>
                    <li>You will not impersonate any person or entity or falsely state or misrepresent your affiliation with a person or entity.</li>
                    <li>You will not interfere with or disrupt the Service or servers or networks connected to the Service.</li>
                    <li>You will not collect or store personal data about other users without their consent.</li>
                </ul>
                
                <h3>4.2 Expert Conduct</h3>
                <ul>
                    <li>Experts must provide accurate information about their qualifications, experience, and expertise.</li>
                    <li>Experts must deliver consultations in a professional, respectful, and timely manner.</li>
                    <li>Experts must not make false claims or guarantees about the outcomes of their consultations.</li>
                    <li>Experts must maintain the confidentiality of client information shared during consultations.</li>
                </ul>
                
                <h3>4.3 Client Conduct</h3>
                <ul>
                    <li>Clients must provide accurate information when seeking consultations.</li>
                    <li>Clients must treat experts with respect and professionalism.</li>
                    <li>Clients must not use the Service to obtain advice for illegal activities.</li>
                    <li>Clients must not share consultations with third parties without the expert's consent.</li>
                </ul>
                
                <h3>4.4 Communication Monitoring</h3>
                <p>By using ConsultPro, you acknowledge and agree that administrators have the right to view chat discussions between clients and experts for quality assurance, dispute resolution, and platform security purposes. All communications on the platform may be monitored to ensure compliance with these Terms.</p>
                
                <div class="highlight-box">
                    <p><strong>Important:</strong> ConsultPro administrators have the ability to view all chat discussions between clients and experts. This monitoring is conducted to ensure quality of service, resolve disputes, and maintain platform integrity.</p>
                </div>
            </section>
            
            <section id="consultations" class="terms-section">
                <h2>5. Consultations</h2>
                
                <h3>5.1 Consultation Process</h3>
                <p>Consultations on ConsultPro follow this general process:</p>
                <ol>
                    <li>A client selects an expert based on their profile, expertise, and ratings.</li>
                    <li>The client initiates a consultation request, specifying their needs and questions.</li>
                    <li>The expert accepts or declines the consultation request.</li>
                    <li>If accepted, the client and expert engage in the consultation through the platform's communication tools.</li>
                    <li>Upon completion, the client may provide a rating and review of the expert's service.</li>
                </ol>
                
                <h3>5.2 Consultation Content</h3>
                <p>ConsultPro does not guarantee the accuracy, completeness, or usefulness of any consultations provided by experts. Clients are advised to use their judgment when applying advice received through the Service.</p>
                
                <h3>5.3 Prohibited Consultation Topics</h3>
                <p>The following consultation topics are prohibited on ConsultPro:</p>
                <ul>
                    <li>Advice on illegal activities</li>
                    <li>Medical advice that would require a licensed physician in the client's jurisdiction</li>
                    <li>Legal advice that would create an attorney-client relationship in the client's jurisdiction</li>
                    <li>Financial advice that would require a licensed financial advisor in the client's jurisdiction</li>
                    <li>Any other topic that violates applicable laws or regulations</li>
                </ul>
            </section>
            
            <section id="payments" class="terms-section">
                <h2>6. Payments and Fees</h2>
                
                <h3>6.1 Fee Structure</h3>
                <p>ConsultPro charges fees for the use of the Service. The current fee structure is available on the Service and may be updated from time to time.</p>
                
                <h3>6.2 Payment Processing</h3>
                <p>All payments are processed through secure third-party payment processors. ConsultPro does not store credit card information.</p>
                
                <h3>6.3 Expert Compensation</h3>
                <p>Experts receive compensation for their consultations as specified in their agreement with ConsultPro. ConsultPro retains a service fee from each transaction.</p>
                
                <h3>6.4 Refunds</h3>
                <p>Refunds may be issued in accordance with ConsultPro's refund policy, which is available on the Service. In general, refunds are considered when:</p>
                <ul>
                    <li>The expert fails to provide the agreed-upon consultation</li>
                    <li>The consultation violates these Terms</li>
                    <li>A valid report against an expert is accepted by the administration</li>
                </ul>
            </section>
            
            <section id="withdrawals" class="terms-section">
                <h2>7. Withdrawals</h2>
                
                <h3>7.1 Expert Withdrawals</h3>
                <p>Experts may withdraw their earned funds from their ConsultPro balance at any time, subject to the following conditions:</p>
                <ul>
                    <li>The minimum withdrawal amount is met (as specified on the platform)</li>
                    <li>The expert's account is in good standing</li>
                    <li>The expert has completed all required verification steps</li>
                    <li>The withdrawal request complies with applicable laws and regulations</li>
                </ul>
                
                <div class="highlight-box info">
                    <p><strong>Note:</strong> Experts can request withdrawals at any time. There are no time restrictions on when funds can be withdrawn from your account balance, provided all other conditions are met.</p>
                </div>
                
                <h3>7.2 Processing Time</h3>
                <p>Withdrawal requests are typically processed within 3-5 business days, but may take longer depending on the payment method and other factors.</p>
                
                <h3>7.3 Withdrawal Methods</h3>
                <p>ConsultPro supports various withdrawal methods, which may vary by region. Available methods are displayed in the expert's account dashboard.</p>
            </section>
            
            <section id="reporting" class="terms-section">
                <h2>8. Reporting System</h2>
                
                <h3>8.1 Report Submission</h3>
                <p>Users may submit reports regarding violations of these Terms, inappropriate behavior, or other concerns. Reports should include detailed information about the issue and any relevant evidence.</p>
                
                <h3>8.2 Report Review</h3>
                <p>All reports are reviewed by ConsultPro administrators. The review process may include:</p>
                <ul>
                    <li>Examination of chat logs and communication history</li>
                    <li>Review of user profiles and activity</li>
                    <li>Communication with involved parties</li>
                    <li>Assessment of any provided evidence</li>
                </ul>
                
                <h3>8.3 Report Outcomes</h3>
                <p>Based on the review, reports may be accepted or rejected. If a report is accepted, appropriate actions will be taken, which may include:</p>
                <ul>
                    <li>Warning the reported user</li>
                    <li>Temporary suspension of the reported user's account</li>
                    <li>Permanent termination of the reported user's account</li>
                    <li>Refund of client payments (in case of expert misconduct)</li>
                    <li>Other actions as deemed appropriate by ConsultPro administrators</li>
                </ul>
            </section>
            
            <section id="account-suspension" class="terms-section">
                <h2>9. Account Suspension and Termination</h2>
                
                <h3>9.1 Automatic Suspension</h3>
                <p>Accounts may be automatically suspended under the following circumstances:</p>
                
                <div class="highlight-box warning">
                    <p><strong>Important:</strong> Expert accounts will be automatically suspended when a report against them is accepted by the administration. This suspension includes processing refunds to affected clients. Similarly, client accounts will be automatically suspended when a report against them is accepted by the administration.</p>
                </div>
                
                <h3>9.2 Suspension Duration</h3>
                <p>Account suspensions are typically temporary and will be automatically lifted after 30 days, unless otherwise specified by the administration. During the suspension period, users cannot access their accounts or use the Service.</p>
                
                <h3>9.3 Multiple Violations</h3>
                <p>Users who receive multiple suspensions may face longer suspension periods or permanent account termination, at the discretion of ConsultPro administrators.</p>
                
                <h3>9.4 Account Termination</h3>
                <p>ConsultPro reserves the right to terminate user accounts for serious or repeated violations of these Terms. Upon termination:</p>
                <ul>
                    <li>The user will no longer have access to the Service</li>
                    <li>Any pending consultations will be cancelled</li>
                    <li>Experts may forfeit unpaid earnings, subject to applicable laws</li>
                    <li>Clients may receive refunds for unused credits or pending consultations</li>
                </ul>
            </section>
            
            <section id="privacy" class="terms-section">
                <h2>10. Privacy and Data Protection</h2>
                <p>ConsultPro values your privacy and is committed to protecting your personal data. Our Privacy Policy, which is incorporated into these Terms by reference, explains how we collect, use, and disclose information about you.</p>
                
                <h3>10.1 Data Collection</h3>
                <p>ConsultPro collects and processes personal data as necessary to provide the Service, including but not limited to:</p>
                <ul>
                    <li>Account registration information</li>
                    <li>Profile information</li>
                    <li>Communication content</li>
                    <li>Payment information</li>
                    <li>Usage data</li>
                </ul>
                
                <h3>10.2 Communication Monitoring</h3>
                <p>As mentioned in Section 4.4, ConsultPro administrators have the ability to view chat discussions between clients and experts. This monitoring is conducted solely for the purposes of:</p>
                <ul>
                    <li>Quality assurance</li>
                    <li>Dispute resolution</li>
                    <li>Investigating reported violations</li>
                    <li>Ensuring compliance with these Terms</li>
                    <li>Protecting the safety and security of our users</li>
                </ul>
            </section>
            
            <section id="intellectual-property" class="terms-section">
                <h2>11. Intellectual Property</h2>
                
                <h3>11.1 ConsultPro Intellectual Property</h3>
                <p>The Service and its original content, features, and functionality are owned by ConsultPro and are protected by international copyright, trademark, patent, trade secret, and other intellectual property or proprietary rights laws.</p>
                
                <h3>11.2 User Content</h3>
                <p>Users retain ownership of the Content they submit, post, or display on or through the Service. By submitting, posting, or displaying Content on or through the Service, you grant ConsultPro a worldwide, non-exclusive, royalty-free license to use, reproduce, modify, adapt, publish, translate, create derivative works from, distribute, and display such Content in connection with providing the Service.</p>
            </section>
            
            <section id="limitation-liability" class="terms-section">
                <h2>12. Limitation of Liability</h2>
                <p>To the maximum extent permitted by applicable law, ConsultPro and its officers, directors, employees, and agents shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including but not limited to loss of profits, data, use, goodwill, or other intangible losses, resulting from:</p>
                <ul>
                    <li>Your access to or use of or inability to access or use the Service</li>
                    <li>Any conduct or Content of any third party on the Service</li>
                    <li>Any Content obtained from the Service</li>
                    <li>Unauthorized access, use, or alteration of your transmissions or Content</li>
                </ul>
                <p>In no event shall ConsultPro's total liability to you for all claims exceed the amount you have paid to ConsultPro in the past six months.</p>
            </section>
            
            <section id="modifications" class="terms-section">
                <h2>13. Modifications to Terms</h2>
                <p>ConsultPro reserves the right to modify or replace these Terms at any time. If a revision is material, we will provide at least 30 days' notice prior to any new terms taking effect. What constitutes a material change will be determined at our sole discretion.</p>
                <p>By continuing to access or use our Service after any revisions become effective, you agree to be bound by the revised terms. If you do not agree to the new terms, you are no longer authorized to use the Service.</p>
            </section>
            
            <section id="governing-law" class="terms-section">
                <h2>14. Governing Law</h2>
                <p>These Terms shall be governed and construed in accordance with the laws of [Jurisdiction], without regard to its conflict of law provisions.</p>
                <p>Our failure to enforce any right or provision of these Terms will not be considered a waiver of those rights. If any provision of these Terms is held to be invalid or unenforceable by a court, the remaining provisions of these Terms will remain in effect.</p>
            </section>
            
            <section id="contact" class="terms-section">
                <h2>15. Contact Information</h2>
                <p>If you have any questions about these Terms, please contact us at:</p>
                <p>Email: Consultpro29000@gmail.com</p>
            </section>
            
            <div class="last-updated">
                <p>Last updated: <?php echo date("F d, Y"); ?></p>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date("Y"); ?> ConsultPro. All rights reserved.</p>
        </div>
    </div>
    
    <a href="#" class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </a>
    
    <script>
        // Back to top button functionality
        const backToTopButton = document.getElementById('backToTop');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.add('visible');
            } else {
                backToTopButton.classList.remove('visible');
            }
        });
        
        backToTopButton.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Smooth scrolling for table of contents links
        document.querySelectorAll('.table-of-contents a').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                window.scrollTo({
                    top: targetElement.offsetTop - 20,
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>