<?php
require_once __DIR__ . '/../config/database.php';

class EventPhoto
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ดึงรูปทั้งหมดของ user คนนี้ในกิจกรรมนี้
    public function getPhotos(int $userId, int $eventId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM event_photos
            WHERE user_id = :user_id AND event_id = :event_id
            ORDER BY uploaded_at ASC"
        );
        $stmt->execute(['user_id' => $userId, 'event_id' => $eventId]);
        return $stmt->fetchAll();
    }

    // บันทึกรูปใหม่
    public function addPhoto(int $userId, int $eventId, string $filename, ?string $caption): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO event_photos (user_id, event_id, filename, caption)
            VALUES (:user_id, :event_id, :filename, :caption)"
        );
        return $stmt->execute([
            'user_id'  => $userId,
            'event_id' => $eventId,
            'filename' => $filename,
            'caption'  => $caption ?: null,
        ]);
    }

    // ลบรูป (เฉพาะเจ้าของ)
    public function deletePhoto(int $photoId, int $userId): array|false
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM event_photos WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute(['id' => $photoId, 'user_id' => $userId]);
        $photo = $stmt->fetch();

        if (!$photo) return false;

        $del = $this->pdo->prepare("DELETE FROM event_photos WHERE id = :id");
        $del->execute(['id' => $photoId]);
        return $photo; // ส่งกลับมาเพื่อให้ลบไฟล์จริงได้ข้างนอก
    }
}