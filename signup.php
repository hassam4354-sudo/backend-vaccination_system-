<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SignUp - Child Vaccination System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue-900: #0a1628;
            --blue-700: #1a3a6e;
            --blue-600: #1e4db7;
            --blue-500: #2563eb;
            --blue-400: #3b82f6;
            --blue-300: #93c5fd;
            --blue-100: #dbeafe;
            --blue-50:  #eff6ff;
            --accent:   #0ea5e9;
            --gray-50:  #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-700: #334155;
            --white:    #ffffff;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        /* ── LEFT PANEL ── */
        .left-panel {
            background: linear-gradient(135deg, #0a1628 0%, #1e4db7 60%, #0ea5e9 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            padding: 60px 56px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .left-panel::before {
            content: '';
            position: absolute;
            bottom: -100px; right: -100px;
            width: 350px; height: 350px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(37,99,235,0.25) 0%, transparent 70%);
        }

        .panel-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 56px;
        }
        .logo-icon {
            width: 44px; height: 44px;
            background: var(--blue-500);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            box-shadow: 0 4px 14px rgba(37,99,235,0.45);
        }
        .logo-name {
            font-weight: 800;
            font-size: 20px;
            letter-spacing: -0.3px;
        }

        .panel-title {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 20px;
            letter-spacing: -0.5px;
        }
        .panel-title span { color: #7dd3fc; }

        .panel-desc {
            font-size: 16px;
            color: rgba(255,255,255,0.75);
            line-height: 1.75;
            margin-bottom: 48px;
            max-width: 360px;
        }

        .panel-features { display: flex; flex-direction: column; gap: 18px; }
        .pf-item {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 15px;
            color: rgba(255,255,255,0.85);
            font-weight: 500;
        }
        .pf-icon {
            width: 38px; height: 38px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        /* ── RIGHT PANEL ── */
        .right-panel {
            background: var(--gray-50);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px 56px;
            overflow-y: auto;
        }

        .form-box {
            width: 100%;
            max-width: 420px;
        }

        .form-header {
            margin-bottom: 36px;
        }
        .form-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 800;
            color: var(--blue-900);
            margin-bottom: 8px;
            letter-spacing: -0.4px;
        }
        .form-header p {
            color: var(--gray-500);
            font-size: 15px;
        }

        .form-group { margin-bottom: 20px; }

        label {
            display: block;
            margin-bottom: 7px;
            color: var(--gray-700);
            font-weight: 600;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid var(--gray-200);
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--blue-900);
            background: var(--white);
            transition: border-color 0.2s, box-shadow 0.2s;
            appearance: none;
        }
        input::placeholder { color: var(--gray-400); }
        input:focus, select:focus {
            outline: none;
            border-color: var(--blue-400);
            box-shadow: 0 0 0 4px rgba(37,99,235,0.1);
        }

        .select-wrap { position: relative; }
        .select-wrap::after {
            content: '▾';
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            pointer-events: none;
            font-size: 14px;
        }

        .input-icon-wrap { position: relative; }
        .input-icon-wrap input { padding-left: 44px; }
        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 17px;
            pointer-events: none;
        }

        .password-wrap { position: relative; }
        .password-wrap input { padding-right: 44px; }
        .toggle-pass {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray-400);
            font-size: 17px;
            background: none;
            border: none;
            padding: 0;
            width: auto;
        }
        .toggle-pass:hover { color: var(--blue-500); }

        .submit-btn {
            width: 100%;
            padding: 14px;
            background: var(--blue-500);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer;
            margin-top: 8px;
            box-shadow: 0 6px 20px rgba(37,99,235,0.35);
            transition: all 0.25s;
        }
        .submit-btn:hover {
            background: var(--blue-600);
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(37,99,235,0.45);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
            color: var(--gray-400);
            font-size: 13px;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--gray-200);
        }

        .login-link {
            text-align: center;
            font-size: 15px;
            color: var(--gray-500);
        }
        .login-link a {
            color: var(--blue-500);
            font-weight: 700;
            text-decoration: none;
        }
        .login-link a:hover { text-decoration: underline; }

        /* Responsive */
        @media (max-width: 860px) {
            body { grid-template-columns: 1fr; }
            .left-panel { display: none; }
            .right-panel { padding: 40px 28px; }
        }
    </style>
</head>
<body>

    <!-- LEFT PANEL -->
    <div class="left-panel">
        <div class="panel-logo">
            
            <span class="logo-name">Vaccination_Booking_System</span>
        </div>
        <h2 class="panel-title">Your Child's Health<br><span>Starts Here</span></h2>
        <p class="panel-desc">Join thousands of families managing their children's vaccination schedules with ease and confidence.</p>
        <div class="panel-features">
            <div class="pf-item"><div class="pf-icon">📅</div> Easy appointment booking</div>
            <div class="pf-item"><div class="pf-icon">🔔</div> Automated vaccine reminders</div>
            <div class="pf-item"><div class="pf-icon">📋</div> Complete vaccination history</div>
            <div class="pf-item"><div class="pf-icon">🛡️</div> Secure & private records</div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
        <div class="form-box">
            <div class="form-header">
                <h1>Create Account</h1>
                <p>Fill in your details to get started for free.</p>
            </div>

            <form action="send_otp.php" method="post">

                <div class="form-group">
                    <label for="user_type">Register As</label>
                    <div class="select-wrap">
                        <select name="user_type" id="user_type" required>
                            <option value="">Select User Type</option>
                            <option value="parent"> Parent</option>
                            <option value="hospital"> Hospital</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-icon-wrap">
                        <span class="input-icon">📧</span>
                        <input type="email" name="email" id="email" placeholder="example@domain.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrap">
                        <input type="password" name="password" id="password" placeholder="Create a strong password" required>
                        <button type="button" class="toggle-pass" onclick="togglePass('password', this)">👁️</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-wrap">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter your password" required>
                        <button type="button" class="toggle-pass" onclick="togglePass('confirm_password', this)">👁️</button>
                    </div>
                </div>

                <button type="submit" name="signupbtn" class="submit-btn">Send OTP →</button>
            </form>

            <div class="divider">or</div>

            <div class="login-link">
                Already have an account? <a href="login.php">Login Here</a>
            </div>
        </div>
    </div>

    <script>
        function togglePass(id, btn) {
            const input = document.getElementById(id);
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙈';
            } else {
                input.type = 'password';
                btn.textContent = '👁️';
            }
        }
    </script>

</body>
</html>