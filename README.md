# DTU Complaint Management System

## System Information
- **Database Name:** dtu_portal
- **PHP Version:** 5.6 or higher
- **Character Set:** UTF-8

## Default Admin Login
- **Email:** admin@dtu.ac.in
- **Password:** admin123

## Configuration Requirements
1. Database Setup:
   - Import `/DATABASE FILE/dtu_portal.sql`
   - Configure database connection in `includes/config.php`

2. Email Configuration:
   - Update SMTP settings in `includes/config.php`:
   ```php
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_PORT', 587);
   define('SMTP_USER', 'your-email@dtu.ac.in');
   define('SMTP_PASS', 'your-email-password');
   ```

3. File Upload Directory:
   - Ensure `uploads/` directory has write permissions (755)
   - Maximum upload size: 5MB
   - Allowed file types: pdf, doc, docx, jpg, jpeg, png

## User Roles
1. Super Admin (Level 100)
2. Dean (Level 90)
3. HOD (Level 80)
4. Warden (Level 70)
5. Faculty (Level 60)
6. Staff (Level 50)
7. Student (Level 10)

## Departments
- Computer Engineering (COE)
- Electronics & Communication (ECE)
- Information Technology (IT)
- Mechanical Engineering (ME)
- Civil Engineering (CE)
- Hostel Administration (HST)
- Library (LIB)
- Student Affairs (SA)
- Accounts (ACC)
- Medical Facilities (MED) 