<?php
// src/Event.php

require_once __DIR__ . '/../config/database.php';

class Event
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ดึงกิจกรรมทั้งหมด (สำหรับวาดลงปฏิทิน)
    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM events ORDER BY start_datetime ASC");
        return $stmt->fetchAll();
    }

    public function getAllForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.*,
                    (SELECT COUNT(*) FROM registrations r
                    WHERE r.event_id = e.id AND r.user_id = :user_id AND r.status = 'registered') AS is_registered,
                    (SELECT COUNT(*) FROM registrations r2
                    WHERE r2.event_id = e.id AND r2.status = 'registered') AS registered_count
            FROM events e
            ORDER BY e.start_datetime ASC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
    

    // ดึงกิจกรรมเดียวจาก id
    public function findById(int $id): array|false
    {
        $stmt = $this->pdo->prepare("SELECT * FROM events WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    // เพิ่มกิจกรรมใหม่ (เฉพาะ admin)
        public function create(array $data): bool
    {
            $stmt = $this->pdo->prepare(
            "INSERT INTO events (title, description, location, start_datetime, end_datetime, max_participants, target_years, checkin_code, activity_hours, created_by)
            VALUES (:title, :description, :location, :start_datetime, :end_datetime, :max_participants, :target_years, :checkin_code, :activity_hours, :created_by)"
        );
        return $stmt->execute([
            'title'            => $data['title'],
            'description'      => $data['description'],
            'location'         => $data['location'],
            'start_datetime'   => $data['start_datetime'],
            'end_datetime'     => $data['end_datetime'],
            'max_participants' => $data['max_participants'] ?: null,
            'target_years'     => $data['target_years'] ?: null,
            'checkin_code'     => $data['checkin_code'] ?: null,
            'activity_hours'   => $data['activity_hours'] ?? 0,
            'created_by'       => $data['created_by'],
        ]);
    }

    // ลบกิจกรรม (เฉพาะ admin)
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM events WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    // ดึงกิจกรรมที่ user คนหนึ่งลงทะเบียนไว้ (ใช้ในหน้า dashboard)
    public function getRegisteredByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.* FROM events e
            INNER JOIN registrations r ON r.event_id = e.id
            WHERE r.user_id = :user_id AND r.status = 'registered'
            ORDER BY e.start_datetime ASC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
        // ดึงกิจกรรมที่ user ลงทะเบียนไว้ พร้อมสถานะ (สำหรับหน้าเช็คอิน)
    public function getRegisteredByUserForCheckin(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.title, e.location, e.start_datetime, e.end_datetime, r.status
            FROM events e
            INNER JOIN registrations r ON r.event_id = e.id
            WHERE r.user_id = :user_id AND r.status IN ('registered', 'attended')
            ORDER BY e.start_datetime DESC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
        // ประวัติกิจกรรมทั้งหมดที่ user เคยลงทะเบียน (ไม่ว่าจะสถานะไหน)
    public function getHistoryForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.*, r.status AS reg_status
            FROM events e
            INNER JOIN registrations r ON r.event_id = e.id
            WHERE r.user_id = :user_id
            ORDER BY e.start_datetime DESC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
        // ดึงรายชื่อผู้ลงทะเบียนทั้งหมดของ event หนึ่ง พร้อมข้อมูลนักศึกษา (สำหรับพิมพ์รายชื่อ)
    public function getAttendeesList(int $eventId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.fullname, u.username, u.student_year, r.status, r.registered_at
            FROM registrations r
            INNER JOIN users u ON u.id = r.user_id
            WHERE r.event_id = :event_id AND r.status IN ('registered', 'attended')
            ORDER BY u.student_year ASC, u.fullname ASC"
        );
        $stmt->execute(['event_id' => $eventId]);
        return $stmt->fetchAll();
    }
        // ดึงชั่วโมงสะสมรวมของ user คนหนึ่งในปีการศึกษาที่กำหนด
    public function getTotalHours(int $userId, string $academicYear): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(e.activity_hours), 0) AS total
            FROM registrations r
            INNER JOIN events e ON e.id = r.event_id
            WHERE r.user_id = :user_id
            AND r.status = 'attended'
            AND YEAR(e.start_datetime) + 543 = :academic_year"
        );
        $stmt->execute([
            'user_id'       => $userId,
            'academic_year' => $academicYear,
        ]);
        return (float) $stmt->fetch()['total'];
    }

    // ดึงสรุปชั่วโมงรายคน ทุก student (สำหรับหน้า admin)
    public function getHoursSummaryAll(string $academicYear): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                u.id, u.fullname, u.username, u.student_year,
                COALESCE(SUM(e.activity_hours), 0) AS total_hours,
                COUNT(r.id) AS attended_count
            FROM users u
            LEFT JOIN registrations r ON r.user_id = u.id AND r.status = 'attended'
            LEFT JOIN events e ON e.id = r.event_id
            AND YEAR(e.start_datetime) + 543 = :academic_year
            WHERE u.role = 'student'
            GROUP BY u.id
            ORDER BY u.student_year ASC, total_hours DESC"
        );
        $stmt->execute(['academic_year' => $academicYear]);
        return $stmt->fetchAll();
    }
        // ยอดลงทะเบียน vs เช็คอินจริง แยกตามกิจกรรม (5 อันล่าสุด)
    public function getRegistrationStats(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                e.title,
                COUNT(CASE WHEN r.status IN ('registered','attended') THEN 1 END) AS total_reg,
                COUNT(CASE WHEN r.status = 'attended' THEN 1 END) AS total_attended
            FROM events e
            LEFT JOIN registrations r ON r.event_id = e.id
            GROUP BY e.id
            ORDER BY e.start_datetime DESC
            LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // กิจกรรมใหม่รายเดือน 6 เดือนล่าสุด
    public function getMonthlyEventCount(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                DATE_FORMAT(start_datetime, '%Y-%m') AS month,
                COUNT(*) AS count
            FROM events
            WHERE start_datetime >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(start_datetime, '%Y-%m')
            ORDER BY month ASC"
        );
        return $stmt->fetchAll();
    }
        public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE events SET
                title            = :title,
                description      = :description,
                location         = :location,
                start_datetime   = :start_datetime,
                end_datetime     = :end_datetime,
                max_participants = :max_participants,
                target_years     = :target_years,
                checkin_code     = :checkin_code,
                activity_hours   = :activity_hours
            WHERE id = :id"
        );
        return $stmt->execute([
            'title'            => $data['title'],
            'description'      => $data['description'],
            'location'         => $data['location'],
            'start_datetime'   => $data['start_datetime'],
            'end_datetime'     => $data['end_datetime'],
            'max_participants' => $data['max_participants'] ?: null,
            'target_years'     => $data['target_years'] ?: null,
            'checkin_code'     => $data['checkin_code'] ?: null,
            'activity_hours'   => $data['activity_hours'] ?? 0,
            'id'               => $id,
        ]);
    }
}