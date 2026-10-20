<?php
// Optional registration image. Account and avatar are saved together.
function registerStudentWithAvatar(User $users, PDO $pdo, string $username, string $password, string $fullname, int $year, ?array $upload): bool
{
    $filename = null;
    $destination = null;
    $error = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_NO_FILE) {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('รูปโปรไฟล์ต้องมีขนาดไม่เกิน 2 MB');
        }
        if ($error !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])) {
            throw new InvalidArgumentException('อัปโหลดรูปไม่สำเร็จ กรุณาเลือกรูปใหม่');
        }
        $size = filesize($upload['tmp_name']);
        if ($size === false || $size > 2 * 1024 * 1024) {
            throw new InvalidArgumentException('รูปโปรไฟล์ต้องมีขนาดไม่เกิน 2 MB');
        }
        try { $extension = imageExtension($upload['tmp_name']); }
        catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('กรุณาเลือกไฟล์รูป JPG, PNG หรือ WEBP ที่ถูกต้อง');
        }
        $filename = 'avatar_' . bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = __DIR__ . '/../public/img/avatars/' . $filename;
    }
    $pdo->beginTransaction();
    try {
        if (!$users->register($username, $password, $fullname, 'student', $year)) {
            $pdo->rollBack();
            return false;
        }
        $id = (int)$pdo->lastInsertId();
        if ($destination !== null) {
            if (!move_uploaded_file($upload['tmp_name'], $destination)) {
                throw new RuntimeException('Avatar storage failed');
            }
            if (!$users->updateAvatar($id, $filename)) {
                throw new RuntimeException('Avatar update failed');
            }
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($destination !== null && is_file($destination)) unlink($destination);
        throw $e;
    }
}
