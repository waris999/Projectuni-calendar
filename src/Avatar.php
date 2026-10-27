<?php
function defaultAvatarSvg(): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><circle cx="128" cy="128" r="128" fill="#ccc"/><circle cx="128" cy="96" r="48" fill="#999"/><path d="M32 256v-24c0-42 43-80 96-80s96 38 96 80v24z" fill="#999"/></svg>';
}
function avatarAccount(PDO $pdo, int $id): array|false
{
    $s=$pdo->prepare('SELECT id, profile_image FROM users WHERE id=:id');
    $s->execute(['id'=>$id]); return $s->fetch(PDO::FETCH_ASSOC);
}
function avatarFilePath(?string $stored): ?string
{
    $name=basename(str_replace('\\','/',trim($stored ?? '')));
    if (!preg_match('/\A[a-zA-Z0-9_-]+\.(?:jpe?g|png|webp)\z/iD',$name)) return null;
    $folder=realpath(__DIR__.'/../public/img/avatars');
    $file=$folder ? realpath($folder.'/'.$name) : false;
    return $file && $folder && str_starts_with($file,$folder.DIRECTORY_SEPARATOR) && is_file($file) ? $file : null;
}
function currentAvatarUrl(PDO $pdo,int $id): string
{
    $user=avatarAccount($pdo,$id); $_SESSION['profile_image']=$user['profile_image'] ?? '';
    return 'api/avatar.php?v='.substr(hash('sha256',(string)$_SESSION['profile_image']),0,16);
}
function avatarDataFromPost(): string
{
    $data=$_POST['avatar_data'] ?? '';
    if (!is_string($data)) throw new InvalidArgumentException('ข้อมูลรูปไม่ถูกต้อง');
    if (($_POST['avatar_selected'] ?? '')==='1' && $data==='') throw new InvalidArgumentException('รูปยังไม่พร้อม กรุณาเลือกรูปใหม่ หรือใช้รูปเริ่มต้น');
    return $data;
}
function decodeAvatarData($data): ?string
{
    if (!is_string($data)) throw new InvalidArgumentException('ข้อมูลรูปไม่ถูกต้อง');
    if ($data==='') return null;
    if (strlen($data)>450000 || !preg_match('~^data:image/jpeg;base64,([A-Za-z0-9+/]+={0,2})$~D',$data,$match)) throw new InvalidArgumentException('รูปโปรไฟล์ไม่ถูกต้องหรือใหญ่เกินไป กรุณาเลือกรูปใหม่');
    $bytes=base64_decode($match[1],true); $info=$bytes!==false ? @getimagesizefromstring($bytes) : false;
    if (!$bytes || strlen($bytes)>350000 || !$info || ($info['mime'] ?? '')!=='image/jpeg' || $info[0]>512 || $info[1]>512 || $info[0]<1 || $info[1]<1) throw new InvalidArgumentException('ไฟล์รูปไม่ถูกต้อง กรุณาเลือกรูปใหม่');
    return $bytes;
}
// Caller owns the transaction. Verify the database link before committing.
function writeAccountAvatar(PDO $pdo,int $id,string $bytes): string
{
    if ($id<1) throw new RuntimeException('Invalid avatar account');
    $folder=__DIR__.'/../public/img/avatars';
    if (!is_dir($folder) && !mkdir($folder,0755,true) && !is_dir($folder)) throw new RuntimeException('Avatar directory unavailable');
    if (!is_writable($folder)) throw new RuntimeException('Avatar directory not writable');
    $name='avatar_'.bin2hex(random_bytes(16)).'.jpg'; $path=$folder.'/'.$name;
    try {
        if (file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes)) throw new RuntimeException('Avatar write failed');
        $s=$pdo->prepare('UPDATE users SET profile_image=:image WHERE id=:id');$s->execute(['image'=>$name,'id'=>$id]);
        $user=avatarAccount($pdo,$id);
        if (!$user || $user['profile_image']!==$name) throw new RuntimeException('Avatar account link failed');
        return $name;
    } catch (Throwable $e) { if (is_file($path)) unlink($path); throw $e; }
}
function updateEncodedAvatar(PDO $pdo,int $id,$data): void
{
    $bytes=decodeAvatarData($data);
    if ($bytes===null) throw new InvalidArgumentException('กรุณาเลือกรูปก่อนบันทึก');
    $name=null; $pdo->beginTransaction();
    try {
        if (!avatarAccount($pdo,$id)) throw new RuntimeException('Account not found');
        $name=writeAccountAvatar($pdo,$id,$bytes); $pdo->commit(); $_SESSION['profile_image']=$name;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($name!==null && ($path=avatarFilePath($name))) unlink($path);
        throw $e;
    }
}
