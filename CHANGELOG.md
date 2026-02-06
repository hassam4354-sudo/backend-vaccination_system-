# CHANGELOG - Database Alignment Updates

## Changes Made to Align with Your Database Structure

### Date: February 6, 2026

---

## Database Schema Differences Addressed

### 1. **users** Table Changes
- **Column Name Change**: `password` → `password_hash`
  - Updated all authentication queries
  - Files updated: `verify_process.php`, `login_process.php`

- **New Column**: `phone` (varchar(20))
  - Used to store phone numbers for all user types
  - Hospitals now store phone in users.phone instead of separate column
  
- **New Column**: `last_login` (timestamp)
  - Automatically tracks user login times
  - Updated by login_process.php

- **Removed Column**: `is_verified`
  - Email verification logic simplified
  - All verified users can login directly

### 2. **parents** Table Changes
- **Column Name Change**: `phone_number` → `emergency_contact`
  - Updated parent registration forms
  - Files updated: `parent/complete_profile.php`, `parent/save_profile.php`
  - Dashboard queries updated to use emergency_contact

### 3. **hospitals** Table Changes
- **Removed Columns**: 
  - `phone_number` (moved to users.phone)
  - `contact_email` (email is in users.email)
  - `operating_hours` (simplified structure)
  
- **Files Updated**:
  - `hospital/complete_profile.php` - Removed extra form fields
  - `hospital/save_hospital_profile.php` - Updates users.phone separately
  - `admin/manage_hospitals.php` - Joins users table to show phone

### 4. **notifications** Table Changes
- **Column Name Changes**:
  - `notification_title` → `title`
  - `notification_message` → `message`
  - `link_url` → `related_id` (stores ID instead of URL)

- **notification_type Enum Values**:
  - vaccination_reminder
  - appointment_approved
  - appointment_rejected
  - booking_confirmation
  - vaccination_completed
  - system

- **Files Updated**:
  - `dbconnection.php` - Updated create_notification() function
  - All notification calls updated to use correct enum values

---

## Files Modified (Total: 15)

### Core Files (3)
1. `dbconnection.php` - Updated create_notification function
2. `verify_process.php` - Changed to password_hash column
3. `login_process.php` - Changed to password_hash column

### Parent Module (3)
4. `parent/complete_profile.php` - Changed phone_number to emergency_contact
5. `parent/save_profile.php` - Updated database insert query
6. `parent/dashboard.php` - Updated JOIN queries
7. `parent/submit_appointment_request.php` - Updated notification type

### Hospital Module (3)
8. `hospital/complete_profile.php` - Removed extra fields (email, operating_hours)
9. `hospital/save_hospital_profile.php` - Simplified hospital insert, updates users.phone
10. `hospital/dashboard.php` - Updated JOIN to get phone from users table

### Admin Module (5)
11. `admin/appointment_requests.php` - Updated to use emergency_contact
12. `admin/approve_request.php` - Updated notification type
13. `admin/reject_request.php` - Updated notification type
14. `admin/manage_hospitals.php` - Added users.phone to query
15. `admin/verify_hospital.php` - Updated notification type

### New Files Created (1)
16. `admin/toggle_hospital_status.php` - Hospital activation/deactivation

### Documentation (2)
17. `README.md` - Updated with correct database info and admin credentials
18. `CHANGELOG.md` - This file

---

## Default Admin Account

**Email:** admin@vaccination.com  
**Password:** admin123

*This account is pre-configured in your database.*

---

## Query Examples with New Structure

### Get Parent with Contact
```sql
SELECT p.*, u.phone, u.email 
FROM parents p 
JOIN users u ON p.user_id = u.user_id 
WHERE p.parent_id = ?
```

### Get Hospital with Contact Details
```sql
SELECT h.*, u.phone, u.email 
FROM hospitals h 
JOIN users u ON h.user_id = u.user_id 
WHERE h.hospital_id = ?
```

### Create Notification
```sql
INSERT INTO notifications (user_id, title, message, notification_type, related_id)
VALUES (?, ?, ?, 'appointment_approved', ?)
```

---

## Testing Checklist

- [x] Parent registration with emergency_contact
- [x] Hospital registration with phone in users table
- [x] Login with password_hash verification
- [x] Notifications with correct enum types
- [x] Admin viewing hospital phone numbers
- [x] Dashboard JOIN queries working correctly

---

## Migration Notes

If upgrading from old structure:
1. Run these SQL commands:

```sql
-- Add phone to users table (if not exists)
ALTER TABLE users ADD COLUMN phone VARCHAR(20) AFTER user_type;

-- Add last_login to users table (if not exists)
ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL AFTER updated_at;

-- Rename password to password_hash
ALTER TABLE users CHANGE password password_hash VARCHAR(255) NOT NULL;

-- Rename parent phone_number to emergency_contact
ALTER TABLE parents CHANGE phone_number emergency_contact VARCHAR(20);

-- Update notification columns
ALTER TABLE notifications CHANGE notification_title title VARCHAR(200) NOT NULL;
ALTER TABLE notifications CHANGE notification_message message TEXT NOT NULL;
ALTER TABLE notifications CHANGE link_url related_id INT(11);
```

---

## Support

For any issues related to database alignment:
1. Check column names in phpMyAdmin
2. Verify enum values match notification_type
3. Ensure all JOIN queries include necessary tables
4. Test with actual database records

---

**Version:** 1.1  
**Database Schema Version:** child_vaccination_system (1)  
**Last Updated:** February 6, 2026
