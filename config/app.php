<?php
date_default_timezone_set('Asia/Bangkok');

function academicYearStartMonth(): int
{
    $month = (int)(getenv('ACADEMIC_YEAR_START_MONTH') ?: 6);
    return ($month >= 1 && $month <= 12) ? $month : 6;
}

function currentAcademicYear(?DateTimeImmutable $now = null): string
{
    $now ??= new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    return (string)((int)$now->format('Y') + 543 - ((int)$now->format('n') < academicYearStartMonth() ? 1 : 0));
}

function academicYearRange(string $year): array
{
    if (!preg_match('/^25\d{2}$/D', $year)) {
        throw new InvalidArgumentException('Invalid academic year');
    }
    $start = sprintf('%04d-%02d-01 00:00:00', (int)$year - 543, academicYearStartMonth());
    return [$start, (new DateTimeImmutable($start))->modify('+1 year')->format('Y-m-d H:i:s')];
}

function requestedAcademicYear(): string
{
    $year = $_GET['ay'] ?? currentAcademicYear();
    if (!is_string($year) || !preg_match('/^25\d{2}$/D', $year)) {
        http_response_code(400);
        exit('ปีการศึกษาไม่ถูกต้อง');
    }
    return $year;
}
