<?php
// src/Registration.php

require_once __DIR__ . '/../config/database.php';

class Registration
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // เช็คว่า user คนนี้ลงทะเบียน event นี้ไว้หรือยัง
    public function isRegistered(int $userId, int $eventId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM registrations
            WHERE user_id = :user_id AND event_id = :event_id AND status = 'registered'"
        );
        $stmt->execute(['user_id' => $userId, 'event_id' => $eventId]);
        return (bool) $stmt->fetch();
    }

    // ลงทะเบียนเข้าร่วม (ถ้าเคยยกเลิกไว้ก่อน ก็เปลี่ยนกลับเป็น registered แทนการ insert ซ้ำ)
    public function register(int $userId, int $eventId): bool|string
    {
        // ดึงข้อมูล event + ข้อมูล user มาเช็คสิทธิ์ก่อน
        $eventStmt = $this->pdo->prepare("SELECT max_participants, target_years FROM events WHERE id = :id");
        $eventStmt->execute(['id' => $eventId]);
        $event = $eventStmt->fetch();

        $userStmt = $this->pdo->prepare("SELECT student_year FROM users WHERE id = :id");
        $userStmt->execute(['id' => $userId]);
        $user = $userStmt->fetch();

        // เช็คว่าชั้นปีตรงกับที่กิจกรรมกำหนดไหม (ถ้า target_years เป็น NULL แปลว่าเปิดทุกปี)
        if ($event && $event['target_years'] !== null) {
            $allowedYears = explode(',', $event['target_years']);
            if (!$user || !in_array((string) $user['student_year'], $allowedYears, true)) {
                return 'year_not_allowed';
            }
        }

        // เช็คที่นั่งเต็มไหม
        if ($event && $event['max_participants'] !== null) {
            $countStmt = $this->pdo->prepare(
                "SELECT COUNT(*) as cnt FROM registrations
                WHERE event_id = :event_id AND status = 'registered'"
            );
            $countStmt->execute(['event_id' => $eventId]);
            $current = $countStmt->fetch()['cnt'];

            if ($current >= $event['max_participants']) {
                return 'full';
            }
        }

        $check = $this->pdo->prepare(
            "SELECT id FROM registrations WHERE user_id = :user_id AND event_id = :event_id"
        );
        $check->execute(['user_id' => $userId, 'event_id' => $eventId]);
        $existing = $check->fetch();

        if ($existing) {
            $stmt = $this->pdo->prepare(
                "UPDATE registrations SET status = 'registered', registered_at = NOW() WHERE id = :id"
            );
            return $stmt->execute(['id' => $existing['id']]);
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO registrations (user_id, event_id, status) VALUES (:user_id, :event_id, 'registered')"
        );
        return $stmt->execute(['user_id' => $userId, 'event_id' => $eventId]);
    }

    // ยกเลิกลงทะเบียน
    public function cancel(int $userId, int $eventId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE registrations SET status = 'cancelled'
            WHERE user_id = :user_id AND event_id = :event_id"
        );
        return $stmt->execute(['user_id' => $userId, 'event_id' => $eventId]);
    }
        // เช็คอินยืนยันการเข้าร่วมด้วยรหัส
    public function checkin(int $userId, int $eventId, string $code): string
    {
        $regStmt = $this->pdo->prepare(
            "SELECT id, status FROM registrations WHERE user_id = :user_id AND event_id = :event_id"
        );
        $regStmt->execute(['user_id' => $userId, 'event_id' => $eventId]);
        $reg = $regStmt->fetch();

        if (!$reg || $reg['status'] === 'cancelled') {
            return 'not_registered';
        }
        if ($reg['status'] === 'attended') {
            return 'already_checked_in';
        }

        $eventStmt = $this->pdo->prepare("SELECT checkin_code, start_datetime FROM events WHERE id = :id");
        $eventStmt->execute(['id' => $eventId]);
        $event = $eventStmt->fetch();

        if (!$event) {
            return 'event_not_found';
        }
        if (strtotime($event['start_datetime']) > time()) {
            return 'not_started';
        }
        if (empty($event['checkin_code']) || strcasecmp(trim($code), $event['checkin_code']) !== 0) {
            return 'wrong_code';
        }

        $update = $this->pdo->prepare("UPDATE registrations SET status = 'attended' WHERE id = :id");
        $update->execute(['id' => $reg['id']]);
        return 'success';
    }
}