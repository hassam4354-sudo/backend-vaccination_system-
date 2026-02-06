<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Vaccination System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        .nav-buttons a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 10px 25px;
            border: 2px solid white;
            border-radius: 25px;
            transition: all 0.3s;
        }
        .nav-buttons a:hover {
            background: white;
            color: #667eea;
        }
        .hero {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 50px 20px;
        }
        .hero-content {
            color: white;
            max-width: 800px;
        }
        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .hero p {
            font-size: 20px;
            margin-bottom: 40px;
            opacity: 0.9;
        }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        .feature-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            transition: transform 0.3s;
        }
        .feature-card:hover {
            transform: translateY(-10px);
        }
        .feature-card h3 {
            font-size: 24px;
            margin-bottom: 15px;
        }
        .feature-card p {
            font-size: 16px;
            opacity: 0.9;
        }
        .cta-buttons {
            margin-top: 40px;
        }
        .cta-btn {
            display: inline-block;
            padding: 15px 40px;
            margin: 10px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 30px;
            font-weight: bold;
            font-size: 18px;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .cta-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        .footer {
            background: rgba(0, 0, 0, 0.2);
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">🏥 Child Vaccination System</div>
        <div class="nav-buttons">
            <a href="login.php">Login</a>
            <a href="signup.php">Sign Up</a>
        </div>
    </div>

    <div class="hero">
        <div class="hero-content">
            <h1>Welcome to Child Vaccination System</h1>
            <p>A modern platform to manage and track your child's vaccination schedule efficiently</p>

            <div class="features">
                <div class="feature-card">
                    <h3>👨‍👩‍👧 For Parents</h3>
                    <p>Register your children, book appointments, and track vaccination history</p>
                </div>
                <div class="feature-card">
                    <h3>🏥 For Hospitals</h3>
                    <p>Manage appointments, update vaccination records, and track inventory</p>
                </div>
                <div class="feature-card">
                    <h3>🔐 For Admins</h3>
                    <p>Oversee the entire system, approve requests, and generate reports</p>
                </div>
            </div>

            <div class="cta-buttons">
                <a href="signup.php" class="cta-btn">Get Started →</a>
                <a href="login.php" class="cta-btn">Login</a>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2026 Child Vaccination System. All rights reserved.</p>
        <p>Protecting children's health, one vaccination at a time 💉</p>
    </div>
</body>
</html>
