## Author's Note

Hello! I'm Shreevatsa Acharya, currently studying at DTU, Delhi.

This was a project I created during my first year of college to explore and apply what I was learning.

My main focus was on understanding Database Management Systems (DBMS) — not full-stack web development.

   - I specifically wanted to learn:

   - How data is stored, retrieved, and managed in relational databases

   - SQL query writing and execution

   - Database normalization and relational design

   - Role-based access control using user levels

I used PHP and MySQL simply to interact with the database easily and prototype my ideas.

This is not a professional-grade app — it’s a learning project that helped me bridge theory with practice.

I referred to open-source templates and tutorials to help speed up the frontend/backend setup.

If you're reviewing this as part of an evaluation or interview, I'd love to hear your feedback or suggestions.

# DTU Complaint Management System

## System Information
- **Database Name:** dtu_portal
- **PHP Version:** 5.6 or higher
- **Character Set:** UTF-8

## How to Launch the Web App

1. **Set Up the Database:**
   - Import the SQL file located at `/DATABASE FILE/dtu_portal.sql` into your MySQL server. This creates the necessary tables and data.

2. **Configure Database Connection:**
   - Open `includes/config.php`.
   - Set your MySQL database credentials (host, username, password, database name) in this file.

3. **(Optional) Configure Email:**
   - In `includes/config.php`, update the SMTP settings if you want email notifications to work.

4. **Set File Permissions:**
   - Make sure the `uploads/` directory is writable (permission 755 is recommended).

5. **Start Your Web Server:**
   - If using XAMPP, place the project folder (`DTU_complaint_mgmt_system`) inside the `htdocs` directory.
   - Start Apache and MySQL from the XAMPP control panel.

6. **Access the App:**
   - Open your browser and go to: `http://localhost/DTU_complaint_mgmt_system`

7. **Login:**
   - Use the default admin credentials (see below).

## Default Admin Login
- **Email:** admin@dtu.ac.in
- **Password:** admin123


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
 