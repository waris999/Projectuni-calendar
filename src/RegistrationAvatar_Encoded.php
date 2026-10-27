<?php
require_once __DIR__.'/Avatar.php';
function registerStudentWithEncodedAvatar(User $users,PDO $pdo,string $username,string $password,string $fullname,int $year,$data): bool
{
    $bytes=decodeAvatarData($data); $name=null; $pdo->beginTransaction();
    try {
        if (!$users->register($username,$password,$fullname,'student',$year)) { $pdo->rollBack(); return false; }
        $id=(int)$pdo->lastInsertId();
        if ($bytes!==null) $name=writeAccountAvatar($pdo,$id,$bytes);
        $pdo->commit(); return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($name!==null && ($path=avatarFilePath($name))) unlink($path);
        throw $e;
    }
}
