Hello! I'm Shreevatsa Acharya currently studying in DTU Delhi, and this was a project I built during my first year of college out of curiosity and a desire to learn. At the time, I was especially interested in understanding how databases work — how data is stored, retrieved, and managed behind the scenes in real systems.

While this ended up taking the shape of a web-based complaint management system, my primary goal was to study core DBMS concepts, like relational design, SQL queries, normalization, and user-role-based access control. I used PHP and MySQL mainly because they were simple enough for me to prototype quickly.

This isn't a professional-grade application — it's more of a learning experiment that helped me bridge classroom theory with practical implementation. If you’re reviewing this as part of a project evaluation or interview, I’d love any feedback or suggestions on how I can improve!

Thanks for checking it out.

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