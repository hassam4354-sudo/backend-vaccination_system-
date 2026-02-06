# Child Vaccination System - Backend (PHP)

## 🏥 Overview
Complete PHP backend for Child Vaccination System with three user roles:
- **Admin**: Manage system, approve appointments, manage hospitals and vaccines
- **Parent**: Register children, book vaccination appointments, track history
- **Hospital**: Manage appointments, update vaccination records, maintain inventory

## 📋 Features

### Admin Module
- ✅ View all children details
- ✅ Manage vaccination dates and schedules
- ✅ Generate vaccination reports (child-wise, date-wise)
- ✅ View vaccine availability status
- ✅ Approve/Reject parent appointment requests
- ✅ Add, Update, Delete hospitals
- ✅ View all booking details
- ✅ Verify hospitals
- ✅ System activity logs

### Parent Module
- ✅ Register and Login with OTP verification
- ✅ Add and manage children details
- ✅ View vaccination dates and schedules
- ✅ Book hospital appointments
- ✅ Submit appointment requests to admin
- ✅ View vaccination history and reports
- ✅ Update profile information
- ✅ Receive notifications

### Hospital Module
- ✅ Register and Login
- ✅ Update vaccine inventory status
- ✅ View appointment bookings
- ✅ Mark vaccinations as completed
- ✅ Manage vaccination records
- ✅ View pending verifications

## 🗄️ Database Structure
The system uses the following main tables:
- `users` - Main user authentication (with password_hash, phone, last_login)
- `admins` - Admin profiles
- `parents` - Parent profiles (with emergency_contact)
- `hospitals` - Hospital details
- `children` - Children information
- `vaccines` - Vaccine master data
- `vaccination_schedule` - Recommended schedules
- `appointment_requests` - Booking requests
- `vaccination_bookings` - Confirmed appointments
- `vaccination_records` - Completed vaccinations
- `hospital_vaccine_inventory` - Stock management
- `notifications` - User notifications (with notification_type enum)
- `audit_logs` - System activity tracking

**Key Database Fields:**
- `users.password_hash` - Stores hashed passwords
- `users.phone` - Phone number (shared across user types)
- `parents.emergency_contact` - Emergency contact number
- `notifications.notification_type` - Enum values: vaccination_reminder, appointment_approved, appointment_rejected, booking_confirmation, vaccination_completed, system

## 🚀 Installation Instructions

### Prerequisites
- XAMPP/WAMP/LAMP (PHP 7.4+ and MySQL 5.7+)
- Web browser
- Text editor (VS Code recommended)

### Step 1: Database Setup
1. Start XAMPP/WAMP and run Apache + MySQL
2. Open phpMyAdmin (http://localhost/phpmyadmin)
3. Import the database file:
   - Click "New" to create a database named `child_vaccination_system`
   - Select the database
   - Click "Import" tab
   - Choose the `child_vaccination_system.sql` file
   - Click "Go" to import

### Step 2: File Setup
1. Copy the entire `vaccination_system` folder to your web server directory:
   - For XAMPP: `C:/xampp/htdocs/vaccination_system`
   - For WAMP: `C:/wamp64/www/vaccination_system`
   - For LAMP: `/var/www/html/vaccination_system`

2. Create uploads directory:
   ```
   vaccination_system/uploads/children/
   ```

### Step 3: Configuration
1. Open `config.php` and update:
   ```php
   $host = "localhost";
   $dbusername = "root";
   $dbpassword = ""; // Your MySQL password
   $databasename = "child_vaccination_system";
   
   // For OTP email functionality (optional)
   $my_email = "your_email@gmail.com";
   $app_password = "your_app_password";
   ```

2. If using Gmail for OTP:
   - Enable 2-factor authentication in your Google account
   - Generate an App Password: https://myaccount.google.com/apppasswords
   - Use the 16-character password in `$app_password`

### Step 4: PHPMailer Setup (For OTP)
1. Download PHPMailer: https://github.com/PHPMailer/PHPMailer
2. Extract and place in: `vaccination_system/PHPMailer/`
3. Required files:
   - PHPMailer/src/Exception.php
   - PHPMailer/src/PHPMailer.php
   - PHPMailer/src/SMTP.php

### Step 5: Default Admin Account

Your database already has a default admin account:

**Default Admin Credentials:**
- Email: admin@vaccination.com
- Password: admin123

⚠️ **IMPORTANT**: Change the admin password after first login!

To create additional admin accounts, run this SQL:

```sql
-- Insert admin user
INSERT INTO users (email, password_hash, user_type, phone, is_active) 
VALUES ('newadmin@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '+923001234567', 1);

-- Get the user_id (check from phpMyAdmin users table)
-- Insert admin profile
INSERT INTO admins (user_id, full_name, role) 
VALUES (LAST_INSERT_ID(), 'New Administrator', 'Admin');
```

## 📂 File Structure
```
vaccination_system/
├── admin/
│   ├── dashboard.php
│   ├── appointment_requests.php
│   ├── approve_request.php
│   ├── reject_request.php
│   ├── manage_hospitals.php
│   ├── verify_hospital.php
│   └── manage_vaccines.php
├── parent/
│   ├── dashboard.php
│   ├── complete_profile.php
│   ├── save_profile.php
│   ├── add_child.php
│   ├── create_child.php
│   ├── my_children.php
│   ├── book_appointment.php
│   └── submit_appointment_request.php
├── hospital/
│   ├── dashboard.php
│   ├── complete_profile.php
│   ├── save_hospital_profile.php
│   └── bookings.php
├── uploads/
│   └── children/
├── PHPMailer/
│   └── src/
├── config.php
├── dbconnection.php
├── index.php
├── login.php
├── login_process.php
├── signup.php
├── send_otp.php
├── verify_otp.php
├── verify_process.php
└── logout.php
```

## 🎯 Usage Guide

### For Parents:
1. Visit: http://localhost/vaccination_system/
2. Click "Sign Up" → Select "Parent"
3. Enter email and password
4. Verify OTP sent to email
5. Complete profile with personal details
6. Login and add children
7. Book vaccination appointments
8. Track vaccination history

### For Hospitals:
1. Sign Up as "Hospital"
2. Verify email with OTP
3. Complete hospital profile
4. Wait for admin verification
5. After verification, manage appointments
6. Update vaccination records
7. Maintain vaccine inventory

### For Admins:
1. Login with admin credentials
2. Verify new hospital registrations
3. Approve/reject appointment requests
4. Manage system data
5. Generate reports
6. Monitor system activities

## 🔐 Security Features
- Password hashing using PHP's `password_hash()`
- SQL injection prevention using `mysqli_real_escape_string()`
- Session management with timeout
- OTP-based email verification
- XSS protection with `htmlspecialchars()`
- Role-based access control
- Audit logging for critical actions

## 📧 Email Configuration (OTP)
If OTP emails are not sending:
1. Check Gmail App Password is correct
2. Ensure PHP has OpenSSL extension enabled
3. Check `php.ini` for SMTP settings
4. Verify firewall allows port 587
5. Test with a different email provider if needed

## 🐛 Troubleshooting

### Database Connection Error
- Verify MySQL is running
- Check database name in config.php
- Confirm credentials are correct

### OTP Not Sending
- Verify Gmail App Password
- Check PHPMailer files are in correct location
- Enable less secure apps (if not using App Password)

### Upload Issues
- Check uploads/children/ folder exists
- Verify folder permissions (chmod 777 on Linux)
- Check PHP upload_max_filesize in php.ini

### Session Issues
- Clear browser cookies
- Check session.save_path in php.ini
- Restart Apache server

## 📱 Testing the System

### Test Accounts
After installation, create test accounts:

**Parent Account:**
- Email: parent@test.com
- Password: parent123

**Hospital Account:**
- Email: hospital@test.com
- Password: hospital123

### Test Workflow:
1. Admin approves hospital verification
2. Parent adds child
3. Parent books appointment
4. Admin approves request
5. Hospital marks vaccination complete
6. Parent views vaccination history

## 🔄 Future Enhancements (Optional)
- SMS notifications
- Mobile app integration
- QR code for vaccination certificates
- Multi-language support
- Advanced reporting with charts
- Payment integration for vaccines
- Export reports to PDF/Excel

## 📞 Support
For issues or questions:
- Check database logs in phpMyAdmin
- Review audit_logs table for system activities
- Check PHP error logs in XAMPP/WAMP

## 📄 License
This project is created for educational purposes.

## 👨‍💻 Developer Notes
- All sensitive data is sanitized before database insertion
- File uploads are validated and stored securely
- Session timeout is set to 1 hour
- Audit logs track all critical operations
- Notifications keep users informed of status changes

---

**Version**: 1.0  
**Last Updated**: February 2026  
**Database**: child_vaccination_system.sql  
**PHP Version**: 7.4+  
**MySQL Version**: 5.7+
