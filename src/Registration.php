<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AttendanceReview.php';

class Registration
{
    public function __construct(private PDO $pdo) {}

    public function isRegistered(int $userId, int $eventId): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM registrations WHERE user_id = ? AND event_id = ? AND status IN ('registered','attended')");
        $stmt->execute([$userId, $eventId]);
        return (bool)$stmt->fetch();
    }

    // Every transition locks the same event first, serializing seat allocation.
    private function change(int $userId, int $eventId, callable $action): bool|string
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM events WHERE id = ? FOR UPDATE');
            $stmt->execute([$eventId]);
            $event = $stmt->fetch();
            if (!$event) { $this->pdo->rollBack(); return 'event_not_found'; }
            $stmt = $this->pdo->prepare('SELECT student_year, role FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if (!$user || $user['role'] !== 'student') { $this->pdo->rollBack(); return 'forbidden'; }
            $stmt = $this->pdo->prepare('SELECT * FROM registrations WHERE user_id = ? AND event_id = ? FOR UPDATE');
            $stmt->execute([$userId, $eventId]);
            $reg = $stmt->fetch();
            $now = $this->pdo->query('SELECT NOW()')->fetchColumn();
            $result = $action($event, $user, $reg, $now);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function register(int $userId, int $eventId): bool|string
    {
        return $this->change($userId, $eventId, function ($event, $user, $reg, $now) use ($userId, $eventId) {
            if ($reg && $reg['status'] === 'attended') return 'already_checked_in';
            if ($event['start_datetime'] <= $now) return 'event_started';
            if ($reg && $reg['status'] === 'registered') return true;
            if ($event['target_years'] !== null && !in_array((string)$user['student_year'], explode(',', $event['target_years']), true)) return 'year_not_allowed';
            if ($event['max_participants'] !== null) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status IN ('registered','attended')");
                $stmt->execute([$eventId]);
                if ((int)$stmt->fetchColumn() >= (int)$event['max_participants']) return 'full';
            }
            if ($reg) return $this->pdo->prepare("UPDATE registrations SET status = 'registered', registered_at = NOW() WHERE id = ?")->execute([$reg['id']]);
            return $this->pdo->prepare("INSERT INTO registrations (user_id,event_id,status) VALUES (?,?,'registered')")->execute([$userId,$eventId]);
        });
    }

    public function cancel(int $userId, int $eventId): bool|string
    {
        return $this->change($userId, $eventId, function ($event, $user, $reg, $now) {
            if (!$reg || $reg['status'] === 'cancelled') return 'not_registered';
            if ($reg['status'] === 'attended') return 'already_checked_in';
            if ($event['start_datetime'] <= $now) return 'event_started';
            return $this->pdo->prepare("UPDATE registrations SET status = 'cancelled' WHERE id = ? AND status = 'registered'")->execute([$reg['id']]);
        });
    }

    public function checkin(int $userId, int $eventId, string $code): string
    {
        return (string)$this->change($userId, $eventId, function ($event, $user, $reg, $now) use ($code) {
            if (!$reg || $reg['status'] === 'cancelled') return 'not_registered';
            if ($reg['status'] === 'attended') return 'already_checked_in';
            if ($event['start_datetime'] > $now) return 'not_started';
            if ($event['end_datetime'] <= $now) return 'event_ended';
            if (empty($event['checkin_code']) || !hash_equals(strtoupper($event['checkin_code']), strtoupper(trim($code)))) return 'wrong_code';
            return (new AttendanceReview($this->pdo))->verifyLocked((int)$reg['id']);
        });
    }
}
