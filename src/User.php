<?php
// src/User.php

require_once __DIR__ . '/../config/database.php';

class User
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ตรวจสอบ username + password ตอน login
        public function login(string $username, string $password): array|string
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            return 'invalid';
        }

        // เช็คว่าบัญชีถูกล็อกอยู่ไหม
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return 'locked';
        }

        if (password_verify($password, $user['password'])) {
            // login สำเร็จ -> รีเซ็ตตัวนับกลับเป็น 0
            $reset = $this->pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = :id");
            $reset->execute(['id' => $user['id']]);
            return $user;
        }

        // login ผิด -> เพิ่มตัวนับ
        $newAttempts = $user['failed_attempts'] + 1;
        $lockUntil = null;

        if ($newAttempts >= 5) {
            $lockUntil = date('Y-m-d H:i:s', time() + 15 * 60); // ล็อก 15 นาที
        }

        $update = $this->pdo->prepare(
            "UPDATE users SET failed_attempts = :attempts, locked_until = :locked WHERE id = :id"
        );
        $update->execute([
            'attempts' => $newAttempts,
            'locked'   => $lockUntil,
            'id'       => $user['id'],
        ]);

        return $lockUntil ? 'locked' : 'invalid';
    }

    // สมัครสมาชิกใหม่
    public function register(string $username, string $password, string $fullname, string $role = 'student', ?int $studentYear = null): bool
    {
        $check = $this->pdo->prepare("SELECT id FROM users WHERE username = :username");
        $check->execute(['username' => $username]);
        if ($check->fetch()) {
            return false;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, password, fullname, role, student_year)
            VALUES (:username, :password, :fullname, :role, :student_year)"
        );
        return $stmt->execute([
            'username'     => $username,
            'password'     => $hashedPassword,
            'fullname'     => $fullname,
            'role'         => $role,
            'student_year' => $studentYear,
        ]);
    }

    // ดึงข้อมูล user จาก id
    public function findById(int $id): array|false
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
        // อัปเดตข้อมูลส่วนตัว (ไม่รวมรหัสผ่าน/รูป)
    public function updateProfile(int $id, string $fullname, ?string $phone, ?string $email): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET fullname = :fullname, phone = :phone, email = :email WHERE id = :id"
        );
        return $stmt->execute([
            'fullname' => $fullname,
            'phone'    => $phone ?: null,
            'email'    => $email ?: null,
            'id'       => $id,
        ]);
    }

    // อัปเดตรูปโปรไฟล์
    public function updateAvatar(int $id, string $filename): bool
    {
        $stmt = $this->pdo->prepare("UPDATE users SET profile_image = :img WHERE id = :id");
        return $stmt->execute(['img' => $filename, 'id' => $id]);
    }
        public function getAll(string $search = '', string $roleFilter = '', string $yearFilter = ''): array
    {
        $sql = "SELECT u.*, 
                (SELECT COUNT(*) FROM registrations r WHERE r.user_id = u.id AND r.status IN ('registered','attended')) AS reg_count
                FROM users u WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (u.fullname LIKE :search OR u.username LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }
        if ($roleFilter !== '') {
            $sql .= " AND u.role = :role";
            $params['role'] = $roleFilter;
        }
        if ($yearFilter !== '') {
            $sql .= " AND u.student_year = :year";
            $params['year'] = $yearFilter;
        }
        $sql .= " ORDER BY u.role ASC, u.student_year ASC, u.fullname ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function deleteUser(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function getStats(): array
    {
        $stats = [];
        $stats['total_users'] = (int) $this->pdo->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
        $stats['total_students'] = (int) $this->pdo->query("SELECT COUNT(*) c FROM users WHERE role='student'")->fetch()['c'];
        $stats['total_admins'] = (int) $this->pdo->query("SELECT COUNT(*) c FROM users WHERE role='admin'")->fetch()['c'];
        $stats['by_year'] = $this->pdo->query(
            "SELECT student_year, COUNT(*) c FROM users WHERE role='student' AND student_year IS NOT NULL GROUP BY student_year ORDER BY student_year"
        )->fetchAll();
        return $stats;
    }
        public function saveCertificatePath(int $userId, string $path): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET certificate_path = :path WHERE id = :id"
        );
        return $stmt->execute(['path' => $path, 'id' => $userId]);
    }
        public function changePassword(int $userId, string $oldPassword, string $newPassword): string
    {
        $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return 'user_not_found';
        }
        if (!password_verify($oldPassword, $user['password'])) {
            return 'wrong_password';
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $this->pdo->prepare("UPDATE users SET password = :pwd WHERE id = :id");
        $update->execute(['pwd' => $hashed, 'id' => $userId]);
        return 'success';
    }
}