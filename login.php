<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Child Vaccination System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --blue-primary: #1a6fc4;
            --blue-dark: #1155a0;
            --blue-light: #e8f1fb;
            --blue-accent: #3b8de0;
            --white: #ffffff;
            --gray-100: #f5f7fa;
            --gray-300: #d0d9e8;
            --gray-500: #6b7a99;
            --gray-700: #374060;
            --shadow: 0 20px 60px rgba(26, 111, 196, 0.15);
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--gray-100);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .wrapper {
            display: flex;
            width: 100%;
            max-width: 900px;
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .left-panel {
            flex: 1;
            position: relative;
            min-height: 520px;
            background-image: url('https://images.unsplash.com/photo-1584820927498-cfe5211fd8bf?w=800&q=80');
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: flex-start;
            padding: 40px 35px;
            overflow: hidden;
        }
        .left-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(160deg, rgba(17,85,160,0.78) 0%, rgba(10,50,100,0.90) 100%);
            z-index: 1;
        }
        .left-panel::after {
            content: '';
            position: absolute;
            top: -50px; right: -50px;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
            z-index: 2;
        }
        .left-content { position: relative; z-index: 3; }
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            color: white;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 30px;
            margin-bottom: 20px;
            letter-spacing: 0.5px;
            backdrop-filter: blur(6px);
        }
        .brand-title {
            color: var(--white);
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
            line-height: 1.3;
        }
        .brand-sub {
            color: rgba(255,255,255,0.72);
            font-size: 13.5px;
            line-height: 1.7;
            max-width: 240px;
            margin-bottom: 28px;
        }
        .stats { display: flex; gap: 20px; }
        .stat-num { color: white; font-size: 20px; font-weight: 700; }
        .stat-label { color: rgba(255,255,255,0.6); font-size: 11px; margin-top: 2px; }
        .right-panel {
            flex: 1.1;
            padding: 50px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-heading { font-size: 27px; font-weight: 700; color: var(--gray-700); margin-bottom: 6px; letter-spacing: -0.5px; }
        .login-sub { color: var(--gray-500); font-size: 14px; margin-bottom: 36px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: var(--gray-700); font-size: 13px; font-weight: 600; letter-spacing: 0.3px; }
        .input-wrap { position: relative; }
        .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 15px; pointer-events: none; }
        input {
            width: 100%;
            padding: 13px 14px 13px 42px;
            border: 1.5px solid var(--gray-300);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            color: var(--gray-700);
            background: var(--white);
            transition: border-color 0.25s, box-shadow 0.25s, background 0.25s;
        }
        input::placeholder { color: #b0bbcc; }
        input:focus {
            outline: none;
            border-color: var(--blue-primary);
            background: var(--blue-light);
            box-shadow: 0 0 0 4px rgba(26,111,196,0.1);
        }
        .forgot {
            display: block; text-align: right; font-size: 13px;
            color: var(--blue-primary); text-decoration: none;
            margin-top: -10px; margin-bottom: 28px; font-weight: 500;
        }
        .forgot:hover { text-decoration: underline; }
        button {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, var(--blue-accent) 0%, var(--blue-primary) 100%);
            color: white; border: none; border-radius: 10px;
            font-size: 15px; font-weight: 600; font-family: inherit;
            cursor: pointer; letter-spacing: 0.3px;
            transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;
            box-shadow: 0 6px 20px rgba(26,111,196,0.35);
        }
        button:hover { transform: translateY(-1px); box-shadow: 0 10px 28px rgba(26,111,196,0.4); opacity: 0.95; }
        button:active { transform: translateY(0); }
        .divider {
            display: flex; align-items: center; gap: 12px;
            margin: 24px 0; color: var(--gray-300); font-size: 12px;
        }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--gray-300); }
        .link { text-align: center; font-size: 13.5px; color: var(--gray-500); }
        .link a { color: var(--blue-primary); text-decoration: none; font-weight: 600; }
        .link a:hover { text-decoration: underline; }
        @media (max-width: 640px) {
            .left-panel { display: none; }
            .right-panel { padding: 40px 28px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="left-panel">
            <div class="left-content">
                <div class="brand-badge"> Vaccination Booking System</div>
                <div class="brand-title">Protecting Children,<br>One Vaccine at a Time</div>
                <p class="brand-sub">Book, track, and manage your child's vaccination schedule easily and securely.</p>
                <div class="stats">
                    <div class="stat">
                        <div class="stat-num">10K+</div>
                        <div class="stat-label">Children Vaccinated</div>
                    </div>
                    <div class="stat">
                        <div class="stat-num">50+</div>
                        <div class="stat-label">Vaccines Available</div>
                    </div>
                    <div class="stat">
                        <div class="stat-num">100%</div>
                        <div class="stat-label">Safe & Secure</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="right-panel">
            <h1 class="login-heading">Welcome Back </h1>
            <p class="login-sub">Enter your credentials to access your account.</p>
            <form action="login_process.php" method="post">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrap">
                        <span class="input-icon">✉️</span>
                        <input type="email" name="email" id="email" placeholder="example@domain.com" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span class="input-icon">🔒</span>
                        <input type="password" name="password" id="password" placeholder="Enter your password" required>
                    </div>
                </div>
                <a href="#" class="forgot">Forgot Password?</a>
                <button type="submit" name="loginbtn">Login →</button>
            </form>
            <div class="divider">or</div>
            <div class="link">
                Don't have an account? <a href="signup.php">Sign Up Here</a>
            </div>
        </div>
    </div>
</body>
</html>