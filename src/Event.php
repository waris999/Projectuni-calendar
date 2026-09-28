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
            "INSERT INTO events (title, description, location, start_datetime, end_datetime, max_participants, target_years, checkin_code, created_by)
            VALUES (:title, :description, :location, :start_datetime, :end_datetime, :max_participants, :target_years, :checkin_code, :created_by)"
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
}