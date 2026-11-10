ALTER TABLE courses ADD COLUMN learning_objectives TEXT NULL AFTER description;
ALTER TABLE courses ADD COLUMN course_video_url VARCHAR(500) NULL AFTER learning_objectives;
ALTER TABLE exams MODIFY exam_type ENUM('course_pretest', 'course_posttest', 'unit_pretest', 'unit_posttest', 'unit_review') NOT NULL;
ALTER TABLE exams ADD COLUMN pass_percentage DECIMAL(5,2) NOT NULL DEFAULT 70;

CREATE TABLE IF NOT EXISTS course_progress (
    course_progress_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    step_key VARCHAR(80) NOT NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_at TIMESTAMP NULL,
    UNIQUE KEY uq_course_progress (student_id, course_id, step_key),
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
