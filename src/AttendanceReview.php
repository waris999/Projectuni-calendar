<?php
class AttendanceReview
{
    public function __construct(private PDO $pdo) {}

    // Called only while Registration already holds the event and registration locks.
    public function verifyLocked(int $registrationId): string
    {
        $s=$this->pdo->prepare('SELECT status FROM attendance_reviews WHERE registration_id=? FOR UPDATE');
        $s->execute([$registrationId]);$state=$s->fetchColumn();
        if ($state==='pending') return 'pending_approval';
        if ($state==='approved') return 'already_checked_in';
        if ($state==='awaiting_evidence' || $state==='rejected') return 'awaiting_evidence';
        $s=$this->pdo->prepare("INSERT INTO attendance_reviews(registration_id,status) VALUES(?,'awaiting_evidence')");
        $s->execute([$registrationId]);return 'awaiting_evidence';
    }

    private function change(int $registrationId,callable $action): string
    {
        $this->pdo->beginTransaction();
        try {
            $s=$this->pdo->prepare('SELECT event_id FROM registrations WHERE id=?');$s->execute([$registrationId]);$eventId=$s->fetchColumn();
            if (!$eventId) { $this->pdo->rollBack();return 'not_found'; }
            $s=$this->pdo->prepare('SELECT * FROM events WHERE id=? FOR UPDATE');$s->execute([$eventId]);$event=$s->fetch();
            $s=$this->pdo->prepare('SELECT * FROM registrations WHERE id=? FOR UPDATE');$s->execute([$registrationId]);$reg=$s->fetch();
            $s=$this->pdo->prepare('SELECT * FROM attendance_reviews WHERE registration_id=? FOR UPDATE');$s->execute([$registrationId]);$review=$s->fetch();
            if (!$event || !$reg || !$review) { $this->pdo->rollBack();return 'not_found'; }
            $result=$action($event,$reg,$review,$this->pdo->query('SELECT NOW()')->fetchColumn());
            $this->pdo->commit();return $result;
        } catch (Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack();throw $e; }
    }

    public function submit(int $studentId,int $registrationId,$data,string $caption): string
    {
        if (!is_string($data) || strlen($data)>1000000 || !preg_match('~^data:image/jpeg;base64,([A-Za-z0-9+/]+={0,2})$~D',$data,$m)) return 'invalid_image';
        $bytes=base64_decode($m[1],true);$info=$bytes!==false ? @getimagesizefromstring($bytes) : false;
        if (!$bytes || strlen($bytes)>750000 || !$info || ($info['mime'] ?? '')!=='image/jpeg' || $info[0]>1024 || $info[1]>1024) return 'invalid_image';
        if (preg_match_all('/./us',$caption)>500) return 'invalid_caption';
        $created=null;
        try {
            return $this->change($registrationId,function($event,$reg,$review,$now) use($studentId,$bytes,$caption,&$created) {
                if ((int)$reg['user_id']!==$studentId || $reg['status']==='cancelled') return 'forbidden';
                if ($reg['status']==='attended' || $review['status']==='approved') return 'already_approved';
                if ($review['status']==='pending') return 'already_pending';
                $deadline=(new DateTimeImmutable($event['end_datetime']))->modify('+2 days')->format('Y-m-d H:i:s');
                if ($now>$deadline) return 'deadline_passed';
                $folder=__DIR__.'/../public/img/event_photos';
                if (!is_dir($folder) && !mkdir($folder,0755,true) && !is_dir($folder)) throw new RuntimeException('Evidence directory unavailable');
                $name='review_'.bin2hex(random_bytes(16)).'.jpg';$created=$folder.'/'.$name;
                if (file_put_contents($created,$bytes,LOCK_EX)!==strlen($bytes)) throw new RuntimeException('Evidence storage failed');
                $s=$this->pdo->prepare("UPDATE attendance_reviews SET status='pending',submitted_at=NOW(),evidence_filename=?,evidence_caption=?,reviewed_at=NULL,reviewed_by=NULL,review_note=NULL WHERE id=?");
                $s->execute([$name,$caption,$review['id']]);
                $s=$this->pdo->prepare("INSERT INTO notifications(user_id,message,link) SELECT id,?, 'admin_attendance.php' FROM users WHERE role='admin'");
                $s->execute(['มีหลักฐานรออนุมัติ: '.$event['title']]);return 'pending';
            });
        } catch (Throwable $e) { if ($created && is_file($created)) unlink($created);throw $e; }
    }

    public function decide(int $adminId,int $registrationId,string $decision,string $note): string
    {
        $s=$this->pdo->prepare('SELECT role FROM users WHERE id=?');$s->execute([$adminId]);
        if ($s->fetchColumn()!=='admin') return 'forbidden';
        if (!in_array($decision,['approve','reject'],true)) return 'invalid_decision';
        $note=trim($note);
        if (($decision==='reject' && $note==='') || preg_match_all('/./us',$note)>500) return 'invalid_note';
        return $this->change($registrationId,function($event,$reg,$review,$now) use($adminId,$decision,$note) {
            if ($review['status']!=='pending' || $reg['status']!=='registered') return 'already_reviewed';
            $file=self::evidenceFile($review['evidence_filename']);
            if (!$file || !@getimagesize($file)) return 'missing_evidence';
            $state=$decision==='approve' ? 'approved' : 'rejected';
            if ($decision==='approve') {
                $s=$this->pdo->prepare("UPDATE registrations SET status='attended' WHERE id=? AND status='registered'");$s->execute([$reg['id']]);
            }
            $s=$this->pdo->prepare('UPDATE attendance_reviews SET status=?,reviewed_at=NOW(),reviewed_by=?,review_note=? WHERE id=?');
            $s->execute([$state,$adminId,$note,$review['id']]);
            $message=$decision==='approve' ? 'อนุมัติชั่วโมงกิจกรรมแล้ว: '.$event['title'] : 'ไม่อนุมัติหลักฐาน: '.$event['title'].' เหตุผล: '.$note;
            $s=$this->pdo->prepare("INSERT INTO notifications(user_id,message,link) VALUES(?,?,'checkin.php')");$s->execute([$reg['user_id'],$message]);
            return $state;
        });
    }

    public static function evidenceFile(?string $name): ?string
    {
        if (!preg_match('/\Areview_[a-f0-9]{32}\.jpg\z/D',$name ?? '')) return null;
        $path=__DIR__.'/../public/img/event_photos/'.$name;
        return is_file($path) ? $path : null;
    }
}
