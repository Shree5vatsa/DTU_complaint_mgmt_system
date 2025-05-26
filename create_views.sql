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