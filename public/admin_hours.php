<?php
require_once __DIR__ . '/../src/security.php';
/* session initialized by security.php */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Event.php';
require_once __DIR__ . '/../src/Notification.php';

$eventModel = new Event($pdo);
$notifModel = new Notification($pdo);
$unreadCount = 0;

$currentAY = currentAcademicYear();
$selectedAY = requestedAcademicYear();

$summary = $eventModel->getHoursSummaryAll($selectedAY);

// ดึงเป้าหมายทุกชั้นปีของปีที่เลือก
$goalStmt = $pdo->prepare("SELECT student_year, required_hours FROM hour_goals WHERE academic_year = :ay");
$goalStmt->execute(['ay' => $selectedAY]);
$goals = [];
foreach ($goalStmt->fetchAll() as $g) {
    $goals[$g['student_year']] = (float)$g['required_hours'];
}


$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$yearFilter = is_string($_GET['year'] ?? null) ? $_GET['year'] : '';
$statusFilter = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
if (mb_strlen($search) > 150 || ($yearFilter !== '' && !preg_match('/^[1-8]$/D', $yearFilter)) || !in_array($statusFilter, ['', 'complete', 'incomplete', 'unset'], true)) {
    http_response_code(400); exit('ตัวกรองไม่ถูกต้อง');
}
$filterParams = array_filter(['ay'=>$selectedAY,'search'=>$search,'year'=>$yearFilter,'status'=>$statusFilter], static fn($v) => $v !== '');
$returnUrl = 'admin_hours.php?' . http_build_query($filterParams);
$stats = ['total'=>count($summary), 'complete'=>0, 'incomplete'=>0, 'unset'=>0];
foreach ($summary as &$student) {
    $student['goal'] = (float)($goals[$student['student_year']] ?? 0);
    $student['state'] = $student['goal'] <= 0 ? 'unset' : ((float)$student['total_hours'] >= $student['goal'] ? 'complete' : 'incomplete');
    $stats[$student['state']]++;
}
unset($student);
$filtered = array_values(array_filter($summary, static function ($student) use ($search, $yearFilter, $statusFilter) {
    return ($search === '' || mb_stripos($student['fullname'], $search) !== false || mb_stripos($student['username'], $search) !== false)
        && ($yearFilter === '' || (string)$student['student_year'] === $yearFilter)
        && ($statusFilter === '' || $student['state'] === $statusFilter);
}));
$matchingCount = count($filtered);
$perPage = 20;
$pageCount = max(1, (int)ceil($matchingCount / $perPage));
$pageNumber = min($pageCount, max(1, (int)(is_scalar($_GET['page'] ?? 1) ? ($_GET['page'] ?? 1) : 1)));
$visibleStudents = array_slice($filtered, ($pageNumber - 1) * $perPage, $perPage);
$yearOptions = [(int)$selectedAY];
for ($y = (int)$currentAY; $y >= (int)$currentAY - 5; $y--) $yearOptions[] = $y;
$yearOptions = array_unique($yearOptions); rsort($yearOptions);
[$periodStart, $periodEnd] = academicYearRange($selectedAY);
$periodLast = (new DateTimeImmutable($periodEnd))->modify('-1 day');
$thaiMonths = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];
$periodDate = new DateTimeImmutable($periodStart);
$periodLabel = $periodDate->format('j') . ' ' . $thaiMonths[(int)$periodDate->format('n')] . ' ' . ((int)$periodDate->format('Y') + 543)
    . ' – ' . $periodLast->format('j') . ' ' . $thaiMonths[(int)$periodLast->format('n')] . ' ' . ((int)$periodLast->format('Y') + 543);
$goalYears = [1,2,3,4];
foreach (array_merge(array_keys($goals), array_column($summary, 'student_year')) as $year) {
    if ((int)$year >= 1 && (int)$year <= 8) $goalYears[] = (int)$year;
}
$goalYears = array_unique($goalYears); sort($goalYears);
$number = static fn($value): string => rtrim(rtrim(number_format((float)$value, 1, '.', ''), '0'), '.');

// บันทึกเป้าหมายที่แก้ไข
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_goals'])) {
    if (!is_array($_POST['goals'] ?? null)) { http_response_code(400); exit('ข้อมูลเป้าหมายไม่ถูกต้อง'); }
    foreach ($_POST['goals'] as $year => $hours) {
        if (!preg_match('/^[1-8]$/D', (string)$year) || !is_numeric($hours) || !is_finite((float)$hours) || (float)$hours < 0 || (float)$hours > 9999.9) { http_response_code(400); exit('ข้อมูลเป้าหมายไม่ถูกต้อง'); }
    }
    $pdo->beginTransaction();
    try {
    foreach ($_POST['goals'] as $year => $hours) {
        $pdo->prepare(
            "INSERT INTO hour_goals (student_year, academic_year, required_hours)
            VALUES (:year, :ay, :hours)
            ON DUPLICATE KEY UPDATE required_hours = :hours2"
        )->execute([
            'year'   => (int)$year,
            'ay'     => $selectedAY,
            'hours'  => (float)$hours,
            'hours2' => (float)$hours,
        ]);
    }
    $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    header('Location: ' . $returnUrl . '&saved=1');
    exit;
}

$avatarSrc = !empty($_SESSION['profile_image'])
    ? 'img/avatars/' . htmlspecialchars($_SESSION['profile_image'])
    : 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22256%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cdefs%3E%3CclipPath%20id%3D%22circle%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%2F%3E%3C%2FclipPath%3E%3C%2Fdefs%3E%3Cg%20clip-path%3D%22url%28%23circle%29%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%20fill%3D%22%23ccc%22%2F%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%2296%22%20r%3D%2248%22%20fill%3D%22%23999%22%2F%3E%3Cpath%20d%3D%22M32%20256v-24c0-42%2043-80%2096-80s96%2038%2096%2080v24z%22%20fill%3D%22%23999%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E';
require_once __DIR__ . '/../src/Avatar.php';
$avatarSrc = currentAvatarUrl($pdo, (int)$_SESSION['user_id']);
require_once __DIR__ . '/../src/Avatar.php';
$avatarSrc = currentAvatarUrl($pdo, (int)$_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ชั่วโมงกิจกรรม | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
</head>
<body class="app-layout">

<aside class="sidebar">
    <div class="profile">
        <a href="profile.php"><img src="<?= $avatarSrc ?>" alt="รูปโปรไฟล์" width="70" height="70" onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22256%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cdefs%3E%3CclipPath%20id%3D%22circle%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%2F%3E%3C%2FclipPath%3E%3C%2Fdefs%3E%3Cg%20clip-path%3D%22url%28%23circle%29%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%20fill%3D%22%23ccc%22%2F%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%2296%22%20r%3D%2248%22%20fill%3D%22%23999%22%2F%3E%3Cpath%20d%3D%22M32%20256v-24c0-42%2043-80%2096-80s96%2038%2096%2080v24z%22%20fill%3D%22%23999%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E';"></a>
        <div class="name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
        <div class="role">เจ้าหน้าที่</div>
    </div>
    <nav>
        <a href="dashboard.php">🏠 หน้าหลัก</a>
        <a href="calendar.php">📅 ปฏิทินกิจกรรม</a>
        <a href="admin_dashboard.php">📊 แดชบอร์ด</a>
        <a href="admin_hours.php" class="active">⏱️ ชั่วโมงกิจกรรม</a>
            <a href="admin_attendance.php">📝 อนุมัติชั่วโมง</a>
        <a href="admin_users.php">👥 จัดการผู้ใช้</a>
        <a href="notifications.php">🔔 แจ้งเตือน</a>
        <form method="POST" action="logout.php" class="logout-form" onsubmit="return confirm('ยืนยันออกจากระบบหรือไม่?');">
            <?= csrfField() ?>
            <button type="submit" class="logout-button">🚪 ออกจากระบบ</button>
        </form>
    </nav>
</aside>

<div class="main">
    <div class="topbar">Home / ชั่วโมงกิจกรรม</div>

    <div class="content hours-page">
        <section class="card hours-heading" aria-labelledby="hours-title">
            <div>
                <h1 id="hours-title">ชั่วโมงกิจกรรม</h1>
                <p class="hours-subtitle">ติดตามชั่วโมงสะสมและกำหนดเป้าหมายของนักศึกษา</p>
            </div>
            <form method="GET" id="hours-filters" class="hours-year-picker">
                <label for="academic-year">ปีการศึกษา</label>
                <select id="academic-year" name="ay" onchange="this.form.requestSubmit()">
                    <?php foreach ($yearOptions as $year): ?>
                    <option value="<?= $year ?>" <?= (string)$year === $selectedAY ? 'selected' : '' ?>><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn">แสดงข้อมูล</button>
            </form>
            <p class="hours-period">ปีการศึกษา <?= $h($selectedAY) ?> · <?= $h($periodLabel) ?> <span>ใช้กับเป้าหมาย รายงาน และใบรับรองในหน้านี้</span></p>
        </section>

        <?php if (($_GET['saved'] ?? '') === '1'): ?>
        <div class="hours-success" role="status">✓ บันทึกเป้าหมายปีการศึกษา <?= $h($selectedAY) ?> แล้ว</div>
        <?php endif; ?>

        <section class="hours-stats" aria-label="ภาพรวมปีการศึกษาที่เลือก">
            <div><span>นักศึกษาทั้งหมด</span><strong><?= $stats['total'] ?> <small>คน</small></strong></div>
            <div><span>ครบเป้าหมาย</span><strong><?= $stats['complete'] ?> <small>คน</small></strong></div>
            <div><span>ยังไม่ครบ</span><strong><?= $stats['incomplete'] ?> <small>คน</small></strong></div>
            <div><span>ยังไม่กำหนดเป้าหมาย</span><strong><?= $stats['unset'] ?> <small>คน</small></strong></div>
        </section>

        <section class="card" aria-labelledby="goals-title">
            <h2 id="goals-title">เป้าหมายชั่วโมง · ปีการศึกษา <?= $h($selectedAY) ?></h2>
            <p class="hours-hint">กำหนดชั่วโมงที่ต้องสะสมแยกตามชั้นปี ค่า 0 หมายถึงไม่กำหนดเป้าหมาย</p>
            <form method="POST" action="<?= $h($returnUrl) ?>" class="hours-goal-form">
                <?= csrfField() ?>
                <?php foreach ($goalYears as $year): ?>
                <div class="hours-goal-field">
                    <label for="goal-<?= $year ?>">ชั้นปีที่ <?= $year ?></label>
                    <div class="hours-input-unit">
                        <input id="goal-<?= $year ?>" type="number" name="goals[<?= $year ?>]" value="<?= $h($goals[$year] ?? 0) ?>" min="0" max="9999.9" step="0.1" required>
                        <span>ชั่วโมง</span>
                    </div>
                </div>
                <?php endforeach; ?>
                <button type="submit" name="save_goals" class="btn">บันทึกเป้าหมาย</button>
            </form>
        </section>

        <section class="card" aria-labelledby="summary-title">
            <h2 id="summary-title">สรุปชั่วโมงสะสมของนักศึกษา</h2>
            <div class="hours-table-filters">
                <div class="hours-search-field">
                    <label for="student-search">ค้นหานักศึกษา</label>
                    <input form="hours-filters" id="student-search" type="search" name="search" maxlength="150" value="<?= $h($search) ?>" placeholder="ชื่อ-นามสกุล หรือรหัสนักศึกษา">
                </div>
                <div>
                    <label for="student-year">ชั้นปี</label>
                    <select form="hours-filters" id="student-year" name="year">
                        <option value="">ทุกชั้นปี</option>
                        <?php for ($year = 1; $year <= 8; $year++): ?>
                        <option value="<?= $year ?>" <?= $yearFilter === (string)$year ? 'selected' : '' ?>>ชั้นปีที่ <?= $year ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label for="student-status">สถานะ</label>
                    <select form="hours-filters" id="student-status" name="status">
                        <option value="">ทุกสถานะ</option>
                        <option value="complete" <?= $statusFilter === 'complete' ? 'selected' : '' ?>>ครบเป้าหมาย</option>
                        <option value="incomplete" <?= $statusFilter === 'incomplete' ? 'selected' : '' ?>>ยังไม่ครบ</option>
                        <option value="unset" <?= $statusFilter === 'unset' ? 'selected' : '' ?>>ยังไม่กำหนดเป้าหมาย</option>
                    </select>
                </div>
                <button form="hours-filters" type="submit" class="btn">ค้นหา</button>
                <?php if ($search !== '' || $yearFilter !== '' || $statusFilter !== ''): ?>
                <a class="hours-clear" href="admin_hours.php?ay=<?= $h($selectedAY) ?>">ล้างตัวกรอง</a>
                <?php endif; ?>
            </div>
            <p class="hours-result-count" role="status">พบ <?= $matchingCount ?> คน จากนักศึกษาทั้งหมด <?= $stats['total'] ?> คน</p>
            <div class="hours-table-scroll" role="region" aria-label="ตารางชั่วโมงนักศึกษา" tabindex="0">
                <table class="admin-table hours-table">
                    <thead><tr><th>นักศึกษา</th><th>ชั้นปี</th><th>เข้าร่วมกิจกรรม</th><th>ชั่วโมง / เป้าหมาย</th><th>สถานะ</th><th>ใบรับรอง</th></tr></thead>
                    <tbody>
                    <?php if (!$visibleStudents): ?>
                        <tr><td colspan="6" class="hours-empty">ไม่พบนักศึกษาที่ตรงกับตัวกรอง ลองเปลี่ยนคำค้นหาหรือล้างตัวกรอง</td></tr>
                    <?php endif; ?>
                    <?php foreach ($visibleStudents as $student):
                        $total = (float)$student['total_hours'];
                        $goal = $student['goal'];
                        $progress = $goal > 0 ? min(100, max(0, $total / $goal * 100)) : 0;
                    ?>
                        <tr>
                            <td><strong><?= $h($student['fullname']) ?></strong><span class="hours-student-id"><?= $h($student['username']) ?></span></td>
                            <td><?= $student['student_year'] ? 'ปี ' . (int)$student['student_year'] : 'ไม่ระบุ' ?></td>
                            <td><?= (int)$student['attended_count'] ?> กิจกรรม</td>
                            <td class="hours-progress-cell">
                                <div class="hours-progress-label"><strong><?= $number($total) ?></strong> / <?= $goal > 0 ? $number($goal) : '—' ?> ชั่วโมง</div>
                                <?php if ($goal > 0): ?>
                                <progress class="hours-progress" value="<?= $h(round($progress, 2)) ?>" max="100" aria-label="ชั่วโมงสะสมของ <?= $h($student['fullname']) ?>"><?= $h(round($progress)) ?>%</progress>
                                <?php else: ?><span class="hours-muted">ยังไม่กำหนดเป้าหมาย</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($student['state'] === 'complete'): ?><span class="hours-status hours-complete">✓ ครบเป้าหมาย</span>
                                <?php elseif ($student['state'] === 'incomplete'): ?><span class="hours-status hours-incomplete">ขาดอีก <?= $number(max(0, $goal - $total)) ?> ชม.</span>
                                <?php else: ?><span class="hours-status hours-unset">ยังไม่กำหนด</span><?php endif; ?>
                            </td>
                            <td><a href="generate_cert.php?user_id=<?= (int)$student['id'] ?>&amp;ay=<?= $h($selectedAY) ?>" target="_blank" rel="noopener" class="hours-certificate">ดูใบรับรอง ↗</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pageCount > 1): ?>
            <nav class="hours-pagination" aria-label="หน้ารายชื่อนักศึกษา">
                <?php if ($pageNumber > 1): ?><a href="?<?= $h(http_build_query($filterParams + ['page'=>$pageNumber-1])) ?>">← ก่อนหน้า</a><?php endif; ?>
                <span>หน้า <?= $pageNumber ?> / <?= $pageCount ?></span>
                <?php if ($pageNumber < $pageCount): ?><a href="?<?= $h(http_build_query($filterParams + ['page'=>$pageNumber+1])) ?>">ถัดไป →</a><?php endif; ?>
            </nav>
            <?php endif; ?>
            <p class="hours-certificate-note">ใบรับรองแสดงชั่วโมงที่เข้าร่วมจริงและสถานะตามเป้าหมายของปีที่เลือก สามารถดูได้แม้ชั่วโมงยังไม่ครบ</p>
        </section>
    </div>
</div>
<script src="js/mobile-layout.js?v=<?= filemtime(__DIR__ . '/js/mobile-layout.js') ?>"></script>
</body>
</html>
