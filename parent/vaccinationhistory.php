<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vaccination History</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f2f5ff;
        }

        /* Top Bar */
        .header {
            background: linear-gradient(135deg, #5a6dfc, #6f80ff);
            padding: 20px 30px;
            color: #fff;
            font-size: 24px;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .container {
            width: 90%;
            margin: 35px auto;
        }

        .title {
            font-size: 28px;
            color: #333;
            margin-bottom: 25px;
            font-weight: 600;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 22px;
        }

        .vaccine-card {
            background: #fff;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 8px 18px rgba(0,0,0,0.08);
            transition: all 0.25s ease;
            border-left: 5px solid #5a6dfc;
        }

        .vaccine-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.12);
        }

        .vaccine-name {
            font-size: 19px;
            font-weight: 600;
            color: #5a6dfc;
            margin-bottom: 8px;
        }

        .vaccine-desc {
            font-size: 14px;
            color: #555;
            line-height: 1.6;
        }

        .action-box {
            margin-top: 45px;
            text-align: center;
            background: #fff;
            padding: 30px;
            border-radius: 18px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .action-box h3 {
            margin-bottom: 8px;
            color: #333;
            font-size: 22px;
        }

        .action-box p {
            color: #666;
            margin-bottom: 22px;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #5a6dfc, #6f80ff);
            color: #fff;
            text-decoration: none;
            padding: 14px 34px;
            font-size: 16px;
            border-radius: 10px;
            font-weight: 600;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .btn:hover {
            background: linear-gradient(135deg, #4758e6, #5f6ff5);
            transform: scale(1.05);
        }
    </style>
</head>
<body>

<div class="header">Parent Dashboard</div>

<div class="container">
    <div class="title">Vaccination History</div>

    <div class="grid">

        <div class="vaccine-card">
            <div class="vaccine-name">BCG</div>
            <div class="vaccine-desc">BCG vaccine tuberculosis (TB) se bachاؤ ke liye di jati hai. Ye newborn babies ko di jati hai.</div>
        </div>

        <div class="vaccine-card">
            <div class="vaccine-name">DPT</div>
            <div class="vaccine-desc">DPT diphtheria, pertussis aur tetanus se protection deta hai aur immunity strong karta hai.</div>
        </div>

        <div class="vaccine-card">
            <div class="vaccine-name">Hepatitis B</div>
            <div class="vaccine-desc">Ye vaccine liver infection se bachata hai aur long-term protection deta hai.</div>
        </div>

        <div class="vaccine-card">
            <div class="vaccine-name">IPV</div>
            <div class="vaccine-desc">IPV polio se bachاؤ karta hai aur nervous system ko safe rakhta hai.</div>
        </div>

        <div class="vaccine-card">
            <div class="vaccine-name">Measles</div>
            <div class="vaccine-desc">Measles vaccine khusra jaise dangerous virus se bachata hai.</div>
        </div>

        <div class="vaccine-card">
            <div class="vaccine-name">OPV</div>
            <div class="vaccine-desc">OPV oral polio vaccine hai jo drops ke zariye diya jata hai.</div>
        </div>

        <div class="vaccine-card">
            <div class="vaccine-name">PCV</div>
            <div class="vaccine-desc">PCV pneumonia aur serious bacterial infections se bachata hai.</div>
        </div>

        <div class="vaccine-card">
            <div class="vaccine-name">Pentavalent</div>
            <div class="vaccine-desc">Ye vaccine 5 dangerous diseases se ek sath bachata hai.</div>
        </div>

        <div class="vaccine-card">
            <div class="vaccine-name">Vitamin A</div>
            <div class="vaccine-desc">Vitamin A immunity strong karta hai aur eyesight ke liye bohat faida mand hota hai.</div>
        </div>

    </div>

    <div class="action-box">
        <h3>Ready for the Next Dose?</h3>
        <p>Apne child ka next vaccination appointment abhi book karein.</p>
        <a href="book_appointment.php" class="btn">Book Your Appointment</a>
    </div>

</div>

</body>
</html>
