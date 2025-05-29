-- Drop existing views first
DROP VIEW IF EXISTS v_complaint_resolution_stats;
DROP VIEW IF EXISTS v_pending_complaints_by_role;
DROP VIEW IF EXISTS v_complaint_trends;

-- Drop existing tables in reverse order of dependencies
DROP TABLE IF EXISTS complaint_history;
DROP TABLE IF EXISTS complaint_attachments;
DROP TABLE IF EXISTS complaint_details;
DROP TABLE IF EXISTS complaints;
DROP TABLE IF EXISTS complaint_subcategories;
DROP TABLE IF EXISTS complaint_categories;
DROP TABLE IF EXISTS priority_levels;
DROP TABLE IF EXISTS complaint_status_types;
DROP TABLE IF EXISTS user;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS roles;

-- Temporarily disable foreign key checks for all data insertions
SET FOREIGN_KEY_CHECKS = 0;

-- Create roles table first
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `role_level` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default roles
INSERT INTO `roles` (`role_name`, `role_level`) VALUES
('Administrator', 100),
('HOD', 75),
('Warden', 75),
('Teacher', 50),
('Student', 10);

-- Department table to avoid redundancy
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert departments first
INSERT INTO `departments` (`name`, `code`) VALUES
('Computer Engineering', 'COE'),
('Electronics & Communication', 'ECE'),
('Civil Engineering', 'CE'),
('Information Technology', 'IT'),
('Library', 'LIB'),
('Hostel Administration', 'HST'),
('Mechanical Engineering', 'ME');

-- Create user table
CREATE TABLE IF NOT EXISTS `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `email` varchar(128) NOT NULL,
  `password` varchar(256) NOT NULL,
  `role_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `roll_number` varchar(20) DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `image` varchar(128) NOT NULL DEFAULT 'default.jpg',
  `last_login` datetime DEFAULT NULL,
  `failed_attempts` int(11) NOT NULL DEFAULT '0',
  `last_failed_attempt` int(11) DEFAULT NULL,
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `fk_user_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert admin user first (password: admin123)
INSERT INTO `user` (`name`, `email`, `password`, `role_id`, `department_id`, `gender`, `status`, `date_created`) VALUES
('Administrator', 'admin@dtu.ac.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
 (SELECT id FROM roles WHERE role_name = 'Administrator'), NULL, 'Male', 'active', CURRENT_TIMESTAMP);

-- Insert wardens (password: warden123)
INSERT INTO `user` (`name`, `email`, `password`, `role_id`, `department_id`, `gender`, `status`, `date_created`) VALUES
('Dr. Rajesh Kumar', 'boys.hostel.warden@dtu.ac.in', '$2y$10$wH6Qw6Qw6Qw6Qw6Qw6Qw6uQw6Qw6Qw6Qw6Qw6Qw6Qw6Qw6Qw6Qw6', 
 (SELECT id FROM roles WHERE role_name = 'Warden'), 
 (SELECT id FROM departments WHERE code = 'HST'), 'Male', 'active', CURRENT_TIMESTAMP),
('Dr. Priya Sharma', 'girls.hostel.warden@dtu.ac.in', '$2y$10$wH6Qw6Qw6Qw6Qw6Qw6Qw6uQw6Qw6Qw6Qw6Qw6Qw6Qw6Qw6Qw6Qw6', 
 (SELECT id FROM roles WHERE role_name = 'Warden'), 
 (SELECT id FROM departments WHERE code = 'HST'), 'Female', 'active', CURRENT_TIMESTAMP);

-- Insert HODs (password: hod123)
INSERT INTO `user` (`name`, `email`, `password`, `role_id`, `department_id`, `gender`, `status`, `date_created`) VALUES
('Prof. Rajeev Malhotra', 'hod.coe@dtu.ac.in', '$2y$10$hod123HashHere', 
 (SELECT id FROM roles WHERE role_name = 'HOD'), 
 (SELECT id FROM departments WHERE code = 'COE'), 'Male', 'active', CURRENT_TIMESTAMP),
('Prof. Deepika Mehta', 'hod.ece@dtu.ac.in', '$2y$10$hod123HashHere', 
 (SELECT id FROM roles WHERE role_name = 'HOD'), 
 (SELECT id FROM departments WHERE code = 'ECE'), 'Female', 'active', CURRENT_TIMESTAMP);

-- Insert teachers (password: teacher123)
INSERT INTO `user` (`name`, `email`, `department_id`, `password`, `role_id`, `gender`, `status`, `date_created`) VALUES
('Dr. Sunita Sharma', 'sunita.sharma@dtu.ac.in', 
 (SELECT id FROM departments WHERE code = 'COE'), '$2y$10$teacher123HashHere', 
 (SELECT id FROM roles WHERE role_name = 'Teacher'), 'Female', 'active', CURRENT_TIMESTAMP),
('Dr. Amit Singh', 'amit.singh@dtu.ac.in', 
 (SELECT id FROM departments WHERE code = 'ECE'), '$2y$10$teacher123HashHere', 
 (SELECT id FROM roles WHERE role_name = 'Teacher'), 'Male', 'active', CURRENT_TIMESTAMP),
('Dr. Ramesh Chandra', 'ramesh.chandra@dtu.ac.in', 
 (SELECT id FROM departments WHERE code = 'COE'), '$2y$10$teacher123HashHere', 
 (SELECT id FROM roles WHERE role_name = 'Teacher'), 'Male', 'active', CURRENT_TIMESTAMP);

-- Insert students (password: student123)
INSERT INTO `user` (`name`, `email`, `roll_number`, `department_id`, `image`, `password`, `role_id`, `gender`, `status`, `date_created`) VALUES
('Rahul Kumar', 'rahul.kumar@dtu.ac.in', '2K21/CO/123', 
 (SELECT id FROM departments WHERE code = 'COE'), 'default.jpg', '$2y$10$student123HashHere', 
 (SELECT id FROM roles WHERE role_name = 'Student'), 'Male', 'active', CURRENT_TIMESTAMP),
('Priya Singh', 'priya2021.singh@dtu.ac.in', '2K21/IT/234', 
 (SELECT id FROM departments WHERE code = 'IT'), 'default.jpg', '$2y$10$student123HashHere', 
 (SELECT id FROM roles WHERE role_name = 'Student'), 'Female', 'active', CURRENT_TIMESTAMP),
('Michael Thomas', 'michael2021.thomas@dtu.ac.in', '2K21/ECE/345', 
 (SELECT id FROM departments WHERE code = 'ECE'), 'default.jpg', '$2y$10$student123HashHere', 
 (SELECT id FROM roles WHERE role_name = 'Student'), 'Male', 'active', CURRENT_TIMESTAMP),
('Sarah Wilson', 'sarah2021.wilson@dtu.ac.in', '2K21/ME/456', 
 (SELECT id FROM departments WHERE code = 'ME'), 'default.jpg', '$2y$10$student123HashHere', 
 (SELECT id FROM roles WHERE role_name = 'Student'), 'Female', 'active', CURRENT_TIMESTAMP),
('Ankit Sharma', 'ankit2020.sharma@dtu.ac.in', '2K20/CO/112', 
 (SELECT id FROM departments WHERE code = 'COE'), 'default.jpg', '$2y$10$student123HashHere', 
 (SELECT id FROM roles WHERE role_name = 'Student'), 'Male', 'active', CURRENT_TIMESTAMP),
('Neha Gupta', 'neha2022.gupta@dtu.ac.in', '2K22/IT/156', 
 (SELECT id FROM departments WHERE code = 'IT'), 'default.jpg', '$2y$10$student123HashHere', 
 (SELECT id FROM roles WHERE role_name = 'Student'), 'Female', 'active', CURRENT_TIMESTAMP);

-- Create complaint categories table
CREATE TABLE `complaint_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(50) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  `department_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_category_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert complaint categories
INSERT INTO `complaint_categories` (`category_name`, `description`, `department_id`) VALUES
('COE', 'Computer Engineering Department', (SELECT id FROM departments WHERE code = 'COE')),
('ECE', 'Electronics and Communication Engineering', (SELECT id FROM departments WHERE code = 'ECE')),
('CE', 'Civil Engineering', (SELECT id FROM departments WHERE code = 'CE')),
('IT', 'Information Technology', (SELECT id FROM departments WHERE code = 'IT')),
('Library', 'Library Services', (SELECT id FROM departments WHERE code = 'LIB')),
('Hostel', 'Hostel Administration', (SELECT id FROM departments WHERE code = 'HST')),
('Ragging', 'Ragging Related Complaints', NULL),
('Harassment', 'Harassment Related Complaints', NULL),
('Misbehavior', 'Complaints about Misconduct', NULL),
('Other', 'Other General Complaints', NULL);

-- Create complaint subcategories table
CREATE TABLE `complaint_subcategories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_subcat` (`category_id`, `name`),
  CONSTRAINT `fk_subcat_category` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add subcategories for all departments
-- COE Subcategories
INSERT INTO `complaint_subcategories` (category_id, name, description) 
SELECT id, 'Academic', 'Academic related issues and concerns'
FROM complaint_categories WHERE category_name = 'COE';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Faculty', 'Issues related to faculty members'
FROM complaint_categories WHERE category_name = 'COE';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Labs', 'Laboratory related issues'
FROM complaint_categories WHERE category_name = 'COE';

-- ECE Subcategories
INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Academic', 'Academic related issues and concerns'
FROM complaint_categories WHERE category_name = 'ECE';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Faculty', 'Issues related to faculty members'
FROM complaint_categories WHERE category_name = 'ECE';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Labs', 'Laboratory related issues'
FROM complaint_categories WHERE category_name = 'ECE';

-- CE Subcategories
INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Academic', 'Academic related issues and concerns'
FROM complaint_categories WHERE category_name = 'CE';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Faculty', 'Issues related to faculty members'
FROM complaint_categories WHERE category_name = 'CE';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Labs', 'Laboratory related issues'
FROM complaint_categories WHERE category_name = 'CE';

-- IT Subcategories
INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Academic', 'Academic related issues and concerns'
FROM complaint_categories WHERE category_name = 'IT';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Faculty', 'Issues related to faculty members'
FROM complaint_categories WHERE category_name = 'IT';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Labs', 'Laboratory related issues'
FROM complaint_categories WHERE category_name = 'IT';

-- Library Subcategories
INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Books', 'Issues with book availability or condition'
FROM complaint_categories WHERE category_name = 'Library';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Staff', 'Issues related to library staff'
FROM complaint_categories WHERE category_name = 'Library';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Infrastructure', 'Issues with library facilities'
FROM complaint_categories WHERE category_name = 'Library';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Digital Resources', 'Issues with e-resources and online services'
FROM complaint_categories WHERE category_name = 'Library';

-- Hostel Subcategories
INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Room', 'Issues with hostel rooms'
FROM complaint_categories WHERE category_name = 'Hostel';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Mess', 'Issues with mess facilities and food'
FROM complaint_categories WHERE category_name = 'Hostel';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Infrastructure', 'Issues with hostel infrastructure'
FROM complaint_categories WHERE category_name = 'Hostel';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Staff', 'Issues with hostel staff'
FROM complaint_categories WHERE category_name = 'Hostel';

-- Campus Security Subcategories
INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'General', 'General security concerns'
FROM complaint_categories WHERE category_name = 'Campus Security';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Emergency', 'Emergency security situations'
FROM complaint_categories WHERE category_name = 'Campus Security';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Access Control', 'Issues with campus access'
FROM complaint_categories WHERE category_name = 'Campus Security';

-- Add subcategories for Ragging
INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Physical Ragging', 'Physical abuse or threats'
FROM complaint_categories WHERE category_name = 'Ragging';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Verbal Ragging', 'Verbal abuse or intimidation'
FROM complaint_categories WHERE category_name = 'Ragging';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Cyber Ragging', 'Online harassment or bullying'
FROM complaint_categories WHERE category_name = 'Ragging';

-- Add subcategories for Harassment
INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Sexual Harassment', 'Sexual harassment related issues'
FROM complaint_categories WHERE category_name = 'Harassment';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Workplace Harassment', 'Harassment in academic or work environment'
FROM complaint_categories WHERE category_name = 'Harassment';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Discrimination', 'Discriminatory behavior or practices'
FROM complaint_categories WHERE category_name = 'Harassment';

-- Add subcategories for Misbehavior
INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Staff Misbehavior', 'Misconduct by staff members'
FROM complaint_categories WHERE category_name = 'Misbehavior';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Student Misbehavior', 'Misconduct by students'
FROM complaint_categories WHERE category_name = 'Misbehavior';

INSERT INTO `complaint_subcategories` (category_id, name, description)
SELECT id, 'Public Misconduct', 'Misconduct in public areas'
FROM complaint_categories WHERE category_name = 'Misbehavior';

-- Note: 'Other' category intentionally has no subcategories

-- Create complaint status types table
CREATE TABLE `complaint_status_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status_name` varchar(20) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `status_name` (`status_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert initial data for status types
INSERT INTO `complaint_status_types` (`status_name`, `description`) VALUES
('pending', 'Complaint is newly created'),
('in_progress', 'Complaint is being processed'),
('resolved', 'Complaint has been resolved'),
('rejected', 'Complaint has been rejected');

-- Create priority levels table
CREATE TABLE `priority_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level_name` varchar(20) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `level_name` (`level_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert initial data for priority levels
INSERT INTO `priority_levels` (`level_name`, `description`) VALUES
('low', 'Non-urgent issues'),
('medium', 'Standard priority issues'),
('high', 'Urgent issues requiring immediate attention');

-- Create complaints table
CREATE TABLE `complaints` (
  `id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `sub_category_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status_id` int(11) NOT NULL,
  `priority_id` int(11) NOT NULL,
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_updated` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `assigned_to` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `category_id` (`category_id`),
  KEY `sub_category_id` (`sub_category_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `status_id` (`status_id`),
  KEY `priority_id` (`priority_id`),
  KEY `idx_complaint_date` (`date_created`),
  KEY `idx_complaint_status_date` (`status_id`, `date_created`),
  KEY `idx_complaint_department` (`category_id`, `status_id`),
  CONSTRAINT `fk_complaint_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_complaint_category` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`id`),
  CONSTRAINT `fk_complaint_subcategory` FOREIGN KEY (`sub_category_id`) REFERENCES `complaint_subcategories` (`id`),
  CONSTRAINT `fk_complaint_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_complaint_status` FOREIGN KEY (`status_id`) REFERENCES `complaint_status_types` (`id`),
  CONSTRAINT `fk_complaint_priority` FOREIGN KEY (`priority_id`) REFERENCES `priority_levels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create complaint details table (for large text fields)
CREATE TABLE `complaint_details` (
  `complaint_id` varchar(64) NOT NULL,
  `resolution_comments` text,
  `internal_notes` text,
  PRIMARY KEY (`complaint_id`),
  CONSTRAINT `fk_details_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create complaint attachments table
CREATE TABLE `complaint_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` varchar(64) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `complaint_id` (`complaint_id`),
  CONSTRAINT `fk_attachment_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create complaint history table
CREATE TABLE `complaint_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` varchar(64) NOT NULL,
  `status_id` int(11) NOT NULL,
  `comments` text,
  `updated_by` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `complaint_id` (`complaint_id`),
  KEY `status_id` (`status_id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `fk_history_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`),
  CONSTRAINT `fk_history_status` FOREIGN KEY (`status_id`) REFERENCES `complaint_status_types` (`id`),
  CONSTRAINT `fk_history_user` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add sample complaints
INSERT INTO `complaints` (`id`, `user_id`, `category_id`, `sub_category_id`, `title`, `description`, `status_id`, `priority_id`, `assigned_to`, `date_created`, `last_updated`) VALUES
-- Student Complaints
('COMP001', 
 (SELECT id FROM user WHERE email = 'rahul.kumar@dtu.ac.in'),
 (SELECT c.id FROM complaint_categories c WHERE c.category_name = 'COE'),
 (SELECT cs.id FROM complaint_subcategories cs 
  JOIN complaint_categories c ON cs.category_id = c.id 
  WHERE c.category_name = 'COE' AND cs.name = 'Labs'),
 'Computer Lab PCs Not Working',
 'In Lab 3, computers numbered 12, 14, and 15 are not booting up properly. This is affecting our practical sessions.',
 (SELECT id FROM complaint_status_types WHERE status_name = 'pending'),
 (SELECT id FROM priority_levels WHERE level_name = 'high'),
 (SELECT id FROM user WHERE email = 'hod.coe@dtu.ac.in'),
 CURRENT_TIMESTAMP,
 CURRENT_TIMESTAMP),

('COMP002',
 (SELECT id FROM user WHERE email = 'sarah2021.wilson@dtu.ac.in'),
 (SELECT c.id FROM complaint_categories c WHERE c.category_name = 'Hostel'),
 (SELECT cs.id FROM complaint_subcategories cs 
  JOIN complaint_categories c ON cs.category_id = c.id 
  WHERE c.category_name = 'Hostel' AND cs.name = 'Infrastructure'),
 'Water Supply Issue in Girls Hostel',
 'Block C of girls hostel is facing irregular water supply for the past 3 days.',
 (SELECT id FROM complaint_status_types WHERE status_name = 'in_progress'),
 (SELECT id FROM priority_levels WHERE level_name = 'high'),
 (SELECT id FROM user WHERE email = 'admin@dtu.ac.in'),
 CURRENT_TIMESTAMP,
 CURRENT_TIMESTAMP),

-- Teacher Complaints
('COMP003',
 (SELECT id FROM user WHERE email = 'ramesh.chandra@dtu.ac.in'),
 (SELECT c.id FROM complaint_categories c WHERE c.category_name = 'COE'),
 (SELECT cs.id FROM complaint_subcategories cs 
  JOIN complaint_categories c ON cs.category_id = c.id 
  WHERE c.category_name = 'COE' AND cs.name = 'Academic'),
 'Projector Not Working in Room 304',
 'The projector in Room 304 is not functioning properly, affecting the delivery of lectures.',
 (SELECT id FROM complaint_status_types WHERE status_name = 'in_progress'),
 (SELECT id FROM priority_levels WHERE level_name = 'medium'),
 (SELECT id FROM user WHERE email = 'admin@dtu.ac.in'),
 CURRENT_TIMESTAMP,
 CURRENT_TIMESTAMP),

-- HOD Complaints
('COMP004',
 (SELECT id FROM user WHERE email = 'hod.ece@dtu.ac.in'),
 (SELECT c.id FROM complaint_categories c WHERE c.category_name = 'ECE'),
 (SELECT cs.id FROM complaint_subcategories cs 
  JOIN complaint_categories c ON cs.category_id = c.id 
  WHERE c.category_name = 'ECE' AND cs.name = 'Labs'),
 'Requirement for New Lab Equipment',
 'The Digital Signal Processing lab requires new oscilloscopes and signal generators for better practical training.',
 (SELECT id FROM complaint_status_types WHERE status_name = 'pending'),
 (SELECT id FROM priority_levels WHERE level_name = 'medium'),
 (SELECT id FROM user WHERE email = 'admin@dtu.ac.in'),
 CURRENT_TIMESTAMP,
 CURRENT_TIMESTAMP);

-- Add complaint details
INSERT INTO `complaint_details` (`complaint_id`, `resolution_comments`, `internal_notes`) VALUES
('COMP001', NULL, 'IT team has been notified for immediate inspection'),
('COMP002', 'Maintenance team is working on fixing the water pump', 'Requires coordination with Delhi Jal Board'),
('COMP003', NULL, 'New projector requisition might be needed'),
('COMP004', NULL, 'Budget approval pending from finance department');

-- Add complaint history
INSERT INTO `complaint_history` (`complaint_id`, `status_id`, `comments`, `updated_by`, `timestamp`) VALUES
('COMP001', (SELECT id FROM complaint_status_types WHERE status_name = 'pending'), 'Complaint registered and assigned to HOD', (SELECT id FROM user WHERE email = 'admin@dtu.ac.in'), CURRENT_TIMESTAMP),
('COMP002', (SELECT id FROM complaint_status_types WHERE status_name = 'in_progress'), 'Maintenance team has been deployed', (SELECT id FROM user WHERE email = 'admin@dtu.ac.in'), CURRENT_TIMESTAMP),
('COMP003', (SELECT id FROM complaint_status_types WHERE status_name = 'in_progress'), 'Technical team inspection scheduled', (SELECT id FROM user WHERE email = 'admin@dtu.ac.in'), CURRENT_TIMESTAMP),
('COMP004', (SELECT id FROM complaint_status_types WHERE status_name = 'pending'), 'Under review by administration', (SELECT id FROM user WHERE email = 'admin@dtu.ac.in'), CURRENT_TIMESTAMP);

-- Create views for analytics and reporting
-- Create view for complaint resolution statistics by department and category
CREATE OR REPLACE VIEW v_complaint_resolution_stats AS
SELECT 
    d.name as department,
    cc.category_name,
    cs.name as subcategory,
    COUNT(*) as total_complaints,
    SUM(CASE WHEN c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'resolved') THEN 1 ELSE 0 END) as resolved_complaints,
    AVG(TIMESTAMPDIFF(HOUR, c.date_created, IFNULL(c.last_updated, CURRENT_TIMESTAMP))) as avg_resolution_time_hours
FROM complaints c
JOIN complaint_categories cc ON c.category_id = cc.id
LEFT JOIN complaint_subcategories cs ON c.sub_category_id = cs.id
JOIN departments d ON cc.department_id = d.id
GROUP BY d.name, cc.category_name, cs.name;

-- Create view for pending complaints by role
CREATE OR REPLACE VIEW v_pending_complaints_by_role AS
SELECT 
    r.role_name,
    d.name as department,
    COUNT(*) as pending_complaints,
    MAX(TIMESTAMPDIFF(HOUR, c.date_created, CURRENT_TIMESTAMP)) as oldest_pending_hours
FROM complaints c
JOIN user u ON c.assigned_to = u.id
JOIN roles r ON u.role_id = r.id
JOIN departments d ON u.department_id = d.id
WHERE c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'pending')
GROUP BY r.role_name, d.name;

-- Create view for complaint trends
CREATE OR REPLACE VIEW v_complaint_trends AS
SELECT 
    DATE(c.date_created) as complaint_date,
    COUNT(*) as total_complaints,
    SUM(CASE WHEN c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'resolved') THEN 1 ELSE 0 END) as resolved_complaints,
    SUM(CASE WHEN c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'pending') THEN 1 ELSE 0 END) as pending_complaints,
    SUM(CASE WHEN c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'in_progress') THEN 1 ELSE 0 END) as in_progress_complaints
FROM complaints c
GROUP BY DATE(c.date_created);

-- Insert default faculty members
INSERT INTO `user` (`name`, `email`, `department_id`, `image`, `password`, `role_id`, `status`, `date_created`) VALUES
-- Electronics & Communication Teachers
('Prof. Meera Patel', 'meera.patel@dtu.ac.in', (SELECT id FROM departments WHERE code = 'ECE'), 'default.jpg', '$2y$10$QxZFwRHt3XeKHdMhX/I8Y.PtX5n0UL9rQreXHEHGQ9yXZqZL2EYyy', (SELECT id FROM roles WHERE role_name = 'Teacher'), 'active', CURRENT_TIMESTAMP),

-- Information Technology Teachers
('Dr. Vikram Mehta', 'vikram.mehta@dtu.ac.in', (SELECT id FROM departments WHERE code = 'IT'), 'default.jpg', '$2y$10$QxZFwRHt3XeKHdMhX/I8Y.PtX5n0UL9rQreXHEHGQ9yXZqZL2EYyy', (SELECT id FROM roles WHERE role_name = 'Teacher'), 'active', CURRENT_TIMESTAMP),
('Prof. Priya Verma', 'priya.verma@dtu.ac.in', (SELECT id FROM departments WHERE code = 'IT'), 'default.jpg', '$2y$10$QxZFwRHt3XeKHdMhX/I8Y.PtX5n0UL9rQreXHEHGQ9yXZqZL2EYyy', (SELECT id FROM roles WHERE role_name = 'Teacher'), 'active', CURRENT_TIMESTAMP),

-- Mechanical Engineering Teachers
('Dr. Suresh Yadav', 'suresh.yadav@dtu.ac.in', (SELECT id FROM departments WHERE code = 'ME'), 'default.jpg', '$2y$10$QxZFwRHt3XeKHdMhX/I8Y.PtX5n0UL9rQreXHEHGQ9yXZqZL2EYyy', (SELECT id FROM roles WHERE role_name = 'Teacher'), 'active', CURRENT_TIMESTAMP),
('Prof. Anjali Gupta', 'anjali.gupta@dtu.ac.in', (SELECT id FROM departments WHERE code = 'ME'), 'default.jpg', '$2y$10$QxZFwRHt3XeKHdMhX/I8Y.PtX5n0UL9rQreXHEHGQ9yXZqZL2EYyy', (SELECT id FROM roles WHERE role_name = 'Teacher'), 'active', CURRENT_TIMESTAMP),

-- Civil Engineering Teachers
('Dr. Rakesh Sharma', 'rakesh.sharma@dtu.ac.in', (SELECT id FROM departments WHERE code = 'CE'), 'default.jpg', '$2y$10$QxZFwRHt3XeKHdMhX/I8Y.PtX5n0UL9rQreXHEHGQ9yXZqZL2EYyy', (SELECT id FROM roles WHERE role_name = 'Teacher'), 'active', CURRENT_TIMESTAMP),
('Prof. Neha Singh', 'neha.singh@dtu.ac.in', (SELECT id FROM departments WHERE code = 'CE'), 'default.jpg', '$2y$10$QxZFwRHt3XeKHdMhX/I8Y.PtX5n0UL9rQreXHEHGQ9yXZqZL2EYyy', (SELECT id FROM roles WHERE role_name = 'Teacher'), 'active', CURRENT_TIMESTAMP);

INSERT INTO `user` (`name`, `email`, `department_id`, `password`, `role_id`, `status`, `date_created`) VALUES
('Dr. Anjali Mathur', 'chief.librarian@dtu.ac.in', (SELECT id FROM departments WHERE code = 'LIB'), '$2y$10$QxZFwRHt3XeKHdMhX/I8Y.PtX5n0UL9rQreXHEHGQ9yXZqZL2EYyy', (SELECT id FROM roles WHERE role_name = 'Teacher'), 'active', CURRENT_TIMESTAMP),
('Mr. Vikram Singh', 'lib.assistant@dtu.ac.in', (SELECT id FROM departments WHERE code = 'LIB'), '$2y$10$QxZFwRHt3XeKHdMhX/I8Y.PtX5n0UL9rQreXHEHGQ9yXZqZL2EYyy', (SELECT id FROM roles WHERE role_name = 'Teacher'), 'active', CURRENT_TIMESTAMP);

-- Faculty Members for IT Department
INSERT INTO `user` (`name`, `email`, `department_id`, `password`, `role_id`, `status`, `date_created`) VALUES
('Dr. Anand Kumar', 'anand.kumar.it@dtu.ac.in', (SELECT id FROM departments WHERE code = 'IT'), '$2y$10$QxZFwRHt3XeKHdMhX/I8Y.PtX5n0UL9rQreXHEHGQ9yXZqZL2EYyy', (SELECT id FROM roles WHERE role_name = 'Teacher'), 'active', CURRENT_TIMESTAMP),
('Dr. Meena Tiwari', 'meena.tiwari.it@dtu.ac.in', (SELECT id FROM departments WHERE code = 'IT'), '$2y$10$QxZFwRHt3XeKHdMhX/I8Y.PtX5n0UL9rQreXHEHGQ9yXZqZL2EYyy', (SELECT id FROM roles WHERE role_name = 'Teacher'), 'active', CURRENT_TIMESTAMP);

-- Re-enable foreign key checks after all insertions
SET FOREIGN_KEY_CHECKS = 1; 

-- Create views for analytics and reporting
-- ... rest of the views creation remains the same ...

