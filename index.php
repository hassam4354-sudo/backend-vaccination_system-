<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Vaccination System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue-900: #0a1628;
            --blue-800: #0d2244;
            --blue-700: #1a3a6e;
            --blue-600: #1e4db7;
            --blue-500: #2563eb;
            --blue-400: #3b82f6;
            --blue-300: #93c5fd;
            --blue-100: #dbeafe;
            --blue-50:  #eff6ff;
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-500: #64748b;
            --gray-700: #334155;
            --accent: #0ea5e9;
            --accent-light: #e0f2fe;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--white);
            color: var(--blue-900);
            overflow-x: hidden;
        }

        /* ── NAVBAR ── */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid var(--gray-200);
            padding: 0 60px;
            height: 72px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 20px;
            color: var(--blue-700);
            letter-spacing: -0.4px;
        }
        .logo-icon {
            width: 42px; height: 42px;
            background: var(--blue-500);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            box-shadow: 0 4px 14px rgba(37,99,235,0.35);
        }
        .nav-buttons { display: flex; gap: 12px; align-items: center; }
        .btn-ghost {
            color: var(--blue-700);
            text-decoration: none;
            padding: 9px 22px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            transition: background 0.2s;
        }
        .btn-ghost:hover { background: var(--blue-50); }
        .btn-primary {
            background: var(--blue-500);
            color: white;
            text-decoration: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            box-shadow: 0 4px 14px rgba(37,99,235,0.3);
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background: var(--blue-600);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(37,99,235,0.4);
        }

        /* ── HERO ── */
        .hero {
            padding-top: 72px;
            min-height: 100vh;
            background:
                linear-gradient(135deg, rgba(10,22,40,0.72) 0%, rgba(30,77,183,0.65) 100%),
                url('https://images.unsplash.com/photo-1579684385127-1ef15d508118?w=1600&auto=format&fit=crop&q=80') center center / cover no-repeat;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 70% 50%, rgba(37,99,235,0.18) 0%, transparent 60%);
        }
        .hero::after { display: none; }

        .hero-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 80px 60px;
            display: grid;
            grid-template-columns: 1fr;
            max-width: 720px;
            text-align: center;
            align-items: center;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 28px;
            border: 1px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(8px);
        }
        .badge::before {
            content: '';
            width: 6px; height: 6px;
            background: #7dd3fc;
            border-radius: 50%;
        }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: 58px;
            line-height: 1.1;
            font-weight: 800;
            color: white;
            margin-bottom: 24px;
            letter-spacing: -1px;
            text-shadow: 0 2px 12px rgba(0,0,0,0.2);
        }
        .hero-title span {
            color: #7dd3fc;
            position: relative;
        }
        .hero-title span::after {
            content: '';
            position: absolute;
            bottom: 4px; left: 0; right: 0;
            height: 3px;
            background: rgba(125,211,252,0.5);
            border-radius: 2px;
        }

        .hero-desc {
            font-size: 18px;
            line-height: 1.75;
            color: rgba(255,255,255,0.8);
            margin-bottom: 40px;
            font-weight: 400;
        }

        .hero-actions { display: flex; gap: 16px; flex-wrap: wrap; justify-content: center; }
        .btn-lg {
            padding: 16px 36px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
            text-decoration: none;
            transition: all 0.25s;
        }
        .btn-lg-primary {
            background: var(--blue-500);
            color: white;
            box-shadow: 0 6px 20px rgba(37,99,235,0.35);
        }
        .btn-lg-primary:hover {
            background: var(--blue-600);
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(37,99,235,0.45);
        }
        .btn-lg-outline {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 2px solid rgba(255,255,255,0.4);
            backdrop-filter: blur(8px);
        }
        .btn-lg-outline:hover {
            background: rgba(255,255,255,0.22);
            border-color: white;
            transform: translateY(-2px);
        }

        /* Stats */
        .stats-row {
            display: flex;
            gap: 36px;
            margin-top: 52px;
            padding-top: 36px;
            border-top: 1px solid rgba(255,255,255,0.2);
            justify-content: center;
        }
        .stat { }
        .stat-num {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 800;
            color: white;
            line-height: 1;
        }
        .stat-label {
            font-size: 13px;
            color: rgba(255,255,255,0.65);
            margin-top: 4px;
            font-weight: 500;
        }

        /* Hero Visual */
        .hero-visual {
            display: flex;
            flex-direction: column;
            gap: 16px;
            position: relative;
        }
        .vis-card {
            background: white;
            border-radius: 18px;
            padding: 24px 28px;
            box-shadow: 0 8px 32px rgba(37,99,235,0.1), 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-100);
            transition: transform 0.3s;
            animation: floatCard 4s ease-in-out infinite;
        }
        .vis-card:nth-child(2) { animation-delay: 1.3s; margin-left: 30px; }
        .vis-card:nth-child(3) { animation-delay: 2.6s; }

        @keyframes floatCard {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .vis-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        .vis-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
        }
        .vis-icon.blue { background: var(--blue-50); }
        .vis-icon.sky  { background: var(--accent-light); }
        .vis-icon.indigo { background: #eef2ff; }

        .vis-title {
            font-weight: 700;
            font-size: 16px;
            color: var(--blue-900);
        }
        .vis-sub {
            font-size: 13px;
            color: var(--gray-500);
        }

        .progress-bar {
            height: 6px;
            background: var(--gray-100);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            background: linear-gradient(90deg, var(--blue-400), var(--accent));
        }
        .status-pills { display: flex; gap: 8px; flex-wrap: wrap; }
        .pill {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }
        .pill-green { background: #dcfce7; color: #15803d; }
        .pill-blue  { background: var(--blue-100); color: var(--blue-600); }
        .pill-orange{ background: #ffedd5; color: #c2410c; }

        /* ── FEATURES ── */
        .features {
            padding: 100px 60px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .section-label {
            text-align: center;
            color: var(--blue-500);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            font-weight: 800;
            text-align: center;
            color: var(--blue-900);
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }
        .section-sub {
            text-align: center;
            color: var(--gray-500);
            font-size: 17px;
            max-width: 520px;
            margin: 0 auto 64px;
            line-height: 1.7;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
        }
        .feature-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 20px;
            padding: 36px 32px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--blue-400), var(--accent));
            transform: scaleX(0);
            transition: transform 0.3s;
            transform-origin: left;
        }
        .feature-card:hover::before { transform: scaleX(1); }
        .feature-card:hover {
            border-color: var(--blue-200, #bfdbfe);
            box-shadow: 0 16px 48px rgba(37,99,235,0.12);
            transform: translateY(-4px);
        }
        .card-icon-wrap {
            width: 62px; height: 62px;
            border-radius: 16px;
            background: var(--blue-50);
            display: flex; align-items: center; justify-content: center;
            font-size: 30px;
            margin-bottom: 24px;
        }
        .feature-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--blue-900);
            margin-bottom: 12px;
        }
        .feature-card p {
            color: var(--gray-500);
            font-size: 15px;
            line-height: 1.7;
        }
        .card-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--blue-500);
            font-weight: 600;
            font-size: 14px;
            margin-top: 20px;
            text-decoration: none;
            transition: gap 0.2s;
        }
        .card-link:hover { gap: 10px; }

        /* ── HOW IT WORKS ── */
        .how {
            background: linear-gradient(135deg, var(--blue-700) 0%, var(--blue-900) 100%);
            padding: 100px 60px;
            color: white;
        }
        .how-inner { max-width: 1200px; margin: 0 auto; }
        .how .section-title { color: white; }
        .how .section-label { color: var(--blue-300); }
        .how .section-sub { color: rgba(255,255,255,0.65); }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-top: 60px;
        }
        .step {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 18px;
            padding: 32px 24px;
            text-align: center;
            transition: background 0.3s;
        }
        .step:hover { background: rgba(255,255,255,0.12); }
        .step-num {
            width: 48px; height: 48px;
            background: var(--blue-500);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800;
            font-size: 18px;
            margin: 0 auto 20px;
            box-shadow: 0 4px 16px rgba(37,99,235,0.5);
        }
        .step-emoji { font-size: 36px; margin-bottom: 16px; }
        .step h4 { font-size: 17px; font-weight: 700; margin-bottom: 10px; }
        .step p { font-size: 14px; color: rgba(255,255,255,0.65); line-height: 1.6; }

        /* ── CTA ── */
        .cta-section {
            padding: 100px 60px;
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }
        .cta-box {
            background: linear-gradient(135deg, var(--blue-50) 0%, var(--accent-light) 100%);
            border: 1px solid var(--blue-100);
            border-radius: 28px;
            padding: 80px 60px;
            position: relative;
            overflow: hidden;
        }
        .cta-box::before {
            content: '💉';
            position: absolute;
            font-size: 120px;
            right: 60px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.08;
        }
        .cta-box h2 {
            font-family: 'Playfair Display', serif;
            font-size: 44px;
            font-weight: 800;
            color: var(--blue-900);
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }
        .cta-box p {
            color: var(--gray-500);
            font-size: 18px;
            margin-bottom: 40px;
        }
        .cta-actions { display: flex; gap: 16px; justify-content: center; }

        /* ── FOOTER ── */
        footer {
            background: var(--blue-900);
            color: rgba(255,255,255,0.6);
            padding: 40px 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }
        footer .footer-logo {
            font-weight: 800;
            color: white;
            font-size: 16px;
        }

        /* ── ANIMATIONS ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .hero-text > * {
            animation: fadeUp 0.7s both;
        }
        .hero-text .badge       { animation-delay: 0.1s; }
        .hero-text .hero-title  { animation-delay: 0.25s; }
        .hero-text .hero-desc   { animation-delay: 0.4s; }
        .hero-text .hero-actions{ animation-delay: 0.55s; }
        .hero-text .stats-row   { animation-delay: 0.7s; }

        /* Responsive */
        @media (max-width: 1024px) {
            .hero-inner { grid-template-columns: 1fr; gap: 50px; }
            .hero-visual { display: none; }
            .cards-grid { grid-template-columns: 1fr 1fr; }
            .steps-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
            .navbar { padding: 0 24px; }
            .hero-inner, .features, .cta-section { padding-left: 24px; padding-right: 24px; }
            .hero-title { font-size: 38px; }
            .cards-grid, .steps-grid { grid-template-columns: 1fr; }
            .cta-actions { flex-direction: column; align-items: center; }
            footer { flex-direction: column; gap: 12px; text-align: center; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="logo">
           
            Vaccination_Booking_System
        </div>
        <div class="nav-buttons">
            <a href="login.php" class="btn-ghost">Login</a>
            <a href="signup.php" class="btn-primary">Sign Up Free</a>
        </div>
    </nav>

    <!-- HERO -->
    <section class="hero">
        <div class="hero-inner">
            <div class="hero-text">
                <div class="badge">Trusted by 10,000+ Families</div>
                <h1 class="hero-title">
                    Protect Your Child's<br>
                    <span>Health Journey</span>
                </h1>
                <p class="hero-desc">
                    A smart, modern platform to manage your child's vaccination schedule, book appointments, and stay ahead of every important milestone — all in one place.
                </p>
                <div class="hero-actions">
                    <a href="signup.php" class="btn-lg btn-lg-primary">Get Started Free →</a>
                    <a href="login.php" class="btn-lg btn-lg-outline">Login to Dashboard</a>
                </div>
                <div class="stats-row">
                    <div class="stat">
                        <div class="stat-num">50K+</div>
                        <div class="stat-label">Children Vaccinated</div>
                    </div>
                    <div class="stat">
                        <div class="stat-num">200+</div>
                        <div class="stat-label">Partner Hospitals</div>
                    </div>
                    <div class="stat">
                        <div class="stat-num">99.8%</div>
                        <div class="stat-label">Accuracy Rate</div>
                    </div>
                </div>
            </div>


        </div>
    </section>

    <!-- FEATURES -->
    <section class="features">
        <div class="section-label">What We Offer</div>
        <h2 class="section-title">Everything You Need in One Place</h2>
        <p class="section-sub">From parents to hospitals to admins — a seamless experience designed for everyone.</p>

        <div class="cards-grid">
            <div class="feature-card">
              
                <h3>For Parents</h3>
                <p>Register your children, book vaccination appointments, and view your child's complete immunization history at any time.</p>
                <a href="signup.php" class="card-link">Get Started →</a>
            </div>
            <div class="feature-card">
             
                <h3>For Hospitals</h3>
                <p>Manage patient appointments, update vaccination records in real-time, and monitor your vaccine inventory effortlessly.</p>
                <a href="signup.php" class="card-link">Join as Hospital →</a>
            </div>
            <div class="feature-card">
                
                <h3>For Admins</h3>
                <p>Oversee the entire system, approve hospital registrations, and generate detailed reports on vaccination coverage.</p>
                <a href="login.php" class="card-link">Admin Login →</a>
            </div>
            <div class="feature-card">
              
                <h3>Smart Reminders</h3>
                <p>Receive automated reminders before each scheduled vaccine so no important dose is ever missed.</p>
                <a href="signup.php" class="card-link">Learn More →</a>
            </div>
            <div class="feature-card">
               
                <h3>Detailed Reports</h3>
                <p>Track vaccination coverage, download certificates, and share records with schools or healthcare providers.</p>
                <a href="signup.php" class="card-link">Learn More →</a>
            </div>
            <div class="feature-card">
                
                <h3>Secure & Private</h3>
                <p>All health data is encrypted and stored securely, ensuring your child's records remain safe and confidential.</p>
                <a href="signup.php" class="card-link">Learn More →</a>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="how">
        <div class="how-inner">
            <div class="section-label">Simple Process</div>
            <h2 class="section-title">How It Works</h2>
            <p class="section-sub">Get your child protected in four simple steps.</p>
            <div class="steps-grid">
                <div class="step">
                  
                    <div class="step-num">1</div>
                    <h4>Create Account</h4>
                    <p>Sign up as a parent, hospital, or admin in under 2 minutes.</p>
                </div>
                <div class="step">
                
                    <div class="step-num">2</div>
                    <h4>Add Your Child</h4>
                    <p>Enter your child's details and view their personalized vaccine schedule.</p>
                </div>
                <div class="step">
                    
                    <div class="step-num">3</div>
                    <h4>Book Appointment</h4>
                    <p>Choose a nearby hospital and pick a convenient date and time.</p>
                </div>
                <div class="step">
                   
                    <div class="step-num">4</div>
                    <h4>Track Progress</h4>
                    <p>Get reminders, track each dose, and download vaccination certificates.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta-section">
        <div class="cta-box">
            <h2>Start Protecting Your Child Today</h2>
            <p>Join thousands of families who trust VacciCare for their children's health.</p>
            <div class="cta-actions">
                <a href="signup.php" class="btn-lg btn-lg-primary">Create Free Account</a>
                <a href="login.php" class="btn-lg btn-lg-primary">Login to Dashboard</a>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <div>
            <div class="footer-logo"> Vaccination_Booking_System</div>
            <div style="margin-top:6px;">Protecting children's health, one vaccination at a time 💉</div>
        </div>
        <div style="text-align:right;">
            <div>© 2026 Child Vaccination System. All rights reserved.</div>
            <div style="margin-top:4px;">Built with ❤️ for healthier futures</div>
        </div>
    </footer>

</body>
</html>