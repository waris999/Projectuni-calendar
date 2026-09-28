<?php
require_once __DIR__ . '/../config/database.php';

class Notification
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $userId, string $message, ?string $link = null): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO notifications (user_id, message, link) VALUES (:user_id, :message, :link)"
        );
        return $stmt->execute(['user_id' => $userId, 'message' => $message, 'link' => $link]);
    }

    // แจ้งเตือนนักศึกษาทุกคน หรือเฉพาะชั้นปีที่กำหนด (targetYears = "1,2" หรือ null = ทุกปี)
    public function notifyStudents(?string $targetYears, string $message, ?string $link = null): void
    {
        if ($targetYears) {
            $years = explode(',', $targetYears);
            $placeholders = implode(',', array_fill(0, count($years), '?'));
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE role = 'student' AND student_year IN ($placeholders)");
            $stmt->execute($years);
        } else {
            $stmt = $this->pdo->query("SELECT id FROM users WHERE role = 'student'");
        }

        $insert = $this->pdo->prepare(
            "INSERT INTO notifications (user_id, message, link) VALUES (:user_id, :message, :link)"
        );
        foreach ($stmt->fetchAll() as $row) {
            $insert->execute(['user_id' => $row['id'], 'message' => $message, 'link' => $link]);
        }
    }

    // แจ้งเตือนทุกคนที่ลงทะเบียน event นี้ไว้ (เรียกก่อนลบ event เสมอ)
    public function notifyRegisteredUsers(int $eventId, string $message, ?string $link = null): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT user_id FROM registrations WHERE event_id = :event_id AND status IN ('registered', 'attended')"
        );
        $stmt->execute(['event_id' => $eventId]);

        $insert = $this->pdo->prepare(
            "INSERT INTO notifications (user_id, message, link) VALUES (:user_id, :message, :link)"
        );
        foreach ($stmt->fetchAll() as $row) {
            $insert->execute(['user_id' => $row['user_id'], 'message' => $message, 'link' => $link]);
        }
    }

    public function getForUser(int $userId, int $limit = 30): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countUnread(int $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) c FROM notifications WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetch()['c'];
    }

    public function markAllRead(int $userId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");
        return $stmt->execute(['user_id' => $userId]);
    }
}