<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Child Vaccination System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue-900: #0a1628;
            --blue-700: #1a3a6e;
            --blue-600: #1e4db7;
            --blue-500: #2563eb;
            --blue-400: #3b82f6;
            --blue-100: #dbeafe;
            --blue-50:  #eff6ff;
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
            background: var(--gray-50);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px;
        }

        /* Top logo */
        .top-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 36px;
            font-weight: 800;
            font-size: 18px;
            color: var(--blue-700);
        }
        .logo-icon {
            width: 40px; height: 40px;
            background: var(--blue-500);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            box-shadow: 0 4px 14px rgba(37,99,235,0.3);
        }

        /* Card */
        .card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 24px;
            padding: 48px 44px;
            width: 100%;
            max-width: 440px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(37,99,235,0.08), 0 2px 8px rgba(0,0,0,0.04);
        }

        .otp-icon {
            width: 72px; height: 72px;
            background: var(--blue-50);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 36px;
            margin: 0 auto 24px;
            border: 1px solid var(--blue-100);
        }

        .card h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 800;
            color: var(--blue-900);
            margin-bottom: 10px;
            letter-spacing: -0.4px;
        }

        .card p {
            color: var(--gray-500);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 36px;
        }

        /* OTP Input */
        .otp-input {
            width: 100%;
            padding: 18px;
            border: 2px solid var(--gray-200);
            border-radius: 14px;
            font-size: 28px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            text-align: center;
            letter-spacing: 12px;
            color: var(--blue-900);
            background: var(--gray-50);
            transition: border-color 0.2s, box-shadow 0.2s;
            margin-bottom: 24px;
        }
        .otp-input::placeholder {
            color: var(--gray-200);
            letter-spacing: 8px;
        }
        .otp-input:focus {
            outline: none;
            border-color: var(--blue-400);
            box-shadow: 0 0 0 4px rgba(37,99,235,0.1);
            background: var(--white);
        }

        /* Timer */
        .timer {
            font-size: 13px;
            color: var(--gray-400);
            margin-bottom: 24px;
        }
        .timer span {
            color: var(--blue-500);
            font-weight: 700;
        }

        /* Submit button */
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: var(--blue-500);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(37,99,235,0.35);
            transition: all 0.25s;
        }
        .submit-btn:hover {
            background: var(--blue-600);
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(37,99,235,0.45);
        }

        /* Resend */
        .resend {
            margin-top: 24px;
            font-size: 14px;
            color: var(--gray-500);
        }
        .resend a {
            color: var(--blue-500);
            font-weight: 700;
            text-decoration: none;
        }
        .resend a:hover { text-decoration: underline; }

        /* Back link */
        .back-link {
            margin-top: 28px;
            font-size: 14px;
            color: var(--gray-400);
        }
        .back-link a {
            color: var(--gray-500);
            text-decoration: none;
            font-weight: 600;
        }
        .back-link a:hover { color: var(--blue-500); }
    </style>
</head>
<body>

    <div class="top-logo">
       
        Vaccination_Booking_System
    </div>

    <div class="card">
        <div class="otp-icon">📧</div>
        <h1>Verify Your Email</h1>
        <p>Enter the 6-digit OTP we sent to your email address to complete your registration.</p>

        <form action="verify_process.php" method="post">
            <input
                class="otp-input"
                type="text"
                name="otp"
                maxlength="6"
                placeholder="——————"
                pattern="[0-9]{6}"
                inputmode="numeric"
                autocomplete="one-time-code"
                required
            >

            <div class="timer">Code expires in <span id="countdown">10:00</span></div>

            <button type="submit" name="verifybtn" class="submit-btn">Verify & Register →</button>
        </form>

        <div class="resend">
            Didn't receive the code? <a href="signup.php">Resend OTP</a>
        </div>
    </div>

    <div class="back-link">
        ← <a href="signup.php">Back to Sign Up</a>
    </div>

    <script>
        // Countdown timer
        let seconds = 600;
        const el = document.getElementById('countdown');
        const timer = setInterval(() => {
            seconds--;
            const m = String(Math.floor(seconds / 60)).padStart(2, '0');
            const s = String(seconds % 60).padStart(2, '0');
            el.textContent = `${m}:${s}`;
            if (seconds <= 0) {
                clearInterval(timer);
                el.textContent = 'Expired';
                el.style.color = '#ef4444';
            }
        }, 1000);

        // Auto-format: numbers only
        document.querySelector('.otp-input').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>

</body>
</html>