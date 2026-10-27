-- Import once into the existing Uni Calendar database. Existing credits are unchanged.
CREATE TABLE IF NOT EXISTS attendance_reviews (
    id INT(11) NOT NULL AUTO_INCREMENT,
    registration_id INT(11) NOT NULL,
    status ENUM('awaiting_evidence','pending','approved','rejected') NOT NULL DEFAULT 'awaiting_evidence',
    verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME DEFAULT NULL,
    evidence_filename VARCHAR(255) DEFAULT NULL,
    evidence_caption VARCHAR(500) DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    reviewed_by INT(11) DEFAULT NULL,
    review_note VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY attendance_registration (registration_id),
    KEY attendance_queue (status, submitted_at),
    CONSTRAINT attendance_registration_fk FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    CONSTRAINT attendance_reviewer_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
