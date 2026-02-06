# 🚀 QUICK INSTALLATION GUIDE

## 5-Minute Setup for Child Vaccination System

### Prerequisites
- ✅ XAMPP/WAMP installed
- ✅ Web browser
- ✅ Your updated database SQL file: `child_vaccination_system__1_.sql`

---

## Step 1: Start Services (30 seconds)
1. Open XAMPP Control Panel
2. Start **Apache** 
3. Start **MySQL**

---

## Step 2: Import Database (2 minutes)
1. Open browser → http://localhost/phpmyadmin
2. Click **"New"** → Database name: `child_vaccination_system`
3. Click **"Create"**
4. Select your new database
5. Click **"Import"** tab
6. Choose file: `child_vaccination_system__1_.sql`
7. Click **"Go"**
8. ✅ Done! Database imported with admin account already created

---

## Step 3: Install Files (1 minute)
1. Extract `vaccination_system_updated.zip`
2. Copy `vaccination_system` folder to:
   - **XAMPP**: `C:/xampp/htdocs/`
   - **WAMP**: `C:/wamp64/www/`
3. ✅ Files installed!

---

## Step 4: Configure (1 minute)
1. Open `vaccination_system/config.php`
2. Verify these settings:
```php
$host = "localhost";
$dbusername = "root";
$dbpassword = "";  // Empty for XAMPP, might be different for WAMP
$databasename = "child_vaccination_system";
```
3. Save file
4. ✅ Configuration done!

---

## Step 5: Test (30 seconds)
1. Open browser
2. Go to: http://localhost/vaccination_system/
3. Click **"Login"**
4. Use default admin:
   - **Email**: admin@vaccination.com
   - **Password**: admin123
5. ✅ You're in!

---

## 📁 Folder Structure Check

Make sure your folder looks like this:
```
htdocs/
└── vaccination_system/
    ├── admin/
    ├── parent/
    ├── hospital/
    ├── config.php
    ├── index.php
    └── ... (other files)
```

---

## ⚙️ Optional: Email OTP Setup

**Only if you want OTP email verification:**

1. Get Gmail App Password:
   - Go to: https://myaccount.google.com/apppasswords
   - Create app password
   
2. Update `config.php`:
```php
$my_email = "your_email@gmail.com";
$app_password = "xxxx xxxx xxxx xxxx";  // 16-character password
```

3. Download PHPMailer:
   - Download from: https://github.com/PHPMailer/PHPMailer
   - Extract to: `vaccination_system/PHPMailer/`

**Note:** System works without OTP emails - you just won't have email verification.

---

## 🎯 Quick Test Workflow

### 1. Login as Admin
- URL: http://localhost/vaccination_system/login.php
- Email: admin@vaccination.com
- Password: admin123

### 2. Test Parent Registration
1. Logout
2. Click "Sign Up"
3. Select "Parent"
4. Fill form (skip OTP if not configured)
5. Complete profile
6. Add a child
7. Book appointment

### 3. Test Hospital Registration
1. Sign Up as "Hospital"
2. Complete hospital profile
3. Wait for admin verification
4. Login as admin → Verify hospital
5. Login as hospital → View dashboard

---

## 🐛 Common Issues

### Database Connection Error
**Error**: "Database Connection Failed"
**Fix**: 
- Check MySQL is running in XAMPP
- Verify database name: `child_vaccination_system`
- Check password in config.php (usually empty for XAMPP)

### Can't Login
**Error**: "User Not Found"
**Fix**: 
- Make sure database is imported
- Check default admin exists:
  ```sql
  SELECT * FROM users WHERE email = 'admin@vaccination.com'
  ```

### Page Not Found (404)
**Error**: Page not loading
**Fix**:
- Check folder is in htdocs: `C:/xampp/htdocs/vaccination_system/`
- Use correct URL: http://localhost/vaccination_system/
- Apache must be running

### Upload Folder Error
**Error**: Image upload fails
**Fix**:
- Create folder: `vaccination_system/uploads/children/`
- Set permissions (Linux/Mac): `chmod 777 uploads`

---

## 📊 Database Credentials

**Default Admin Account:**
- Email: admin@vaccination.com
- Password: admin123
- Type: Admin

**Database:**
- Host: localhost
- Username: root
- Password: (empty)
- Database: child_vaccination_system

---

## 🔒 Security Reminder

⚠️ **Before going live:**
1. ✅ Change admin password
2. ✅ Set strong MySQL password
3. ✅ Update config.php with secure credentials
4. ✅ Set proper folder permissions
5. ✅ Enable HTTPS

---

## 📞 Need Help?

**Check these files:**
- `README.md` - Complete documentation
- `CHANGELOG.md` - Database changes
- `FILE_SUMMARY.md` - All files explained

**Verify Database:**
- Open phpMyAdmin
- Check tables exist
- Verify admin user exists
- Check audit_logs for errors

---

## ✅ Installation Complete!

Your system is ready at: **http://localhost/vaccination_system/**

**Next Steps:**
1. Login as admin
2. Change admin password
3. Create test accounts
4. Verify hospital accounts
5. Start managing vaccinations!

---

**Installation Time:** ~5 minutes  
**System Status:** ✅ Ready to Use  
**Version:** 1.1 (Database Aligned)
