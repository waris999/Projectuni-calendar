<?php
require_once __DIR__ . '/../vendor/autoload.php';

class CertificateGenerator
{
    // สร้าง PDF ใบเกียรติบัตรและบันทึกไฟล์ไว้ใน public/certificates/
    public static function generate(array $user, float $totalHours, string $academicYear): string
    {
        $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);

        // ตั้งค่า PDF
        $pdf->SetCreator('Uni Calendar');
        $pdf->SetAuthor('Uni Calendar');
        $pdf->SetTitle('ใบเกียรติบัตร');
        $pdf->SetSubject('ใบเกียรติบัตรกิจกรรม');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->AddPage();

        // พื้นหลังสีครีม
        $pdf->Rect(0, 0, 297, 210, 'F', [], [255, 252, 240]);

        // กรอบด้านนอก (เส้นคู่)
        $pdf->SetLineStyle(['width' => 3, 'color' => [30, 86, 49]]);
        $pdf->Rect(10, 10, 277, 190);
        $pdf->SetLineStyle(['width' => 1, 'color' => [30, 86, 49]]);
        $pdf->Rect(13, 13, 271, 184);

        // แถบสีเขียวบนสุด
        $pdf->Rect(13, 13, 271, 25, 'F', [], [30, 86, 49]);

        // ชื่อระบบ (บนแถบเขียว)
        $pdf->SetFont('freeserif', 'B', 16);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(13, 18);
        $pdf->Cell(271, 12, 'UNI CALENDAR — ระบบปฏิทินกิจกรรมมหาวิทยาลัย', 0, 0, 'C');

        // หัวข้อ "ใบเกียรติบัตร"
        $pdf->SetFont('freeserif', 'B', 36);
        $pdf->SetTextColor(30, 86, 49);
        $pdf->SetXY(13, 50);
        $pdf->Cell(271, 20, 'ใบเกียรติบัตร', 0, 0, 'C');

        // เส้นคั่นใต้หัวข้อ
        $pdf->SetLineStyle(['width' => 0.5, 'color' => [180, 180, 140]]);
        $pdf->Line(60, 72, 237, 72);

        // ข้อความ "มอบให้เพื่อรับรองว่า"
        $pdf->SetFont('freeserif', '', 14);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY(13, 76);
        $pdf->Cell(271, 10, 'ใบเกียรติบัตรฉบับนี้มอบให้เพื่อรับรองว่า', 0, 0, 'C');

        // ชื่อนักศึกษา
        $pdf->SetFont('freeserif', 'B', 26);
        $pdf->SetTextColor(20, 60, 30);
        $pdf->SetXY(13, 90);
        $pdf->Cell(271, 14, $user['fullname'], 0, 0, 'C');

        // รหัสนักศึกษา
        $pdf->SetFont('freeserif', '', 13);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY(13, 106);
        $pdf->Cell(271, 8, 'รหัสนักศึกษา: ' . $user['username'] . '   |   ชั้นปี: ' . $user['student_year'], 0, 0, 'C');

        // ข้อความรับรอง
        $pdf->SetFont('freeserif', '', 14);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetXY(13, 118);
        $pdf->Cell(271, 10, 'ได้เข้าร่วมกิจกรรมสะสมชั่วโมงในปีการศึกษา ' . $academicYear, 0, 0, 'C');

        // จำนวนชั่วโมง (เน้นใหญ่)
        $pdf->SetFont('freeserif', 'B', 32);
        $pdf->SetTextColor(30, 86, 49);
        $pdf->SetXY(13, 130);
        $pdf->Cell(271, 16, 'รวม ' . $totalHours . ' ชั่วโมง', 0, 0, 'C');

        // เส้นคั่นก่อน footer
        $pdf->SetLineStyle(['width' => 0.5, 'color' => [180, 180, 140]]);
        $pdf->Line(60, 152, 237, 152);

        // วันที่ออกใบเกียรติบัตร
        $pdf->SetFont('freeserif', '', 12);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetXY(13, 155);
        $pdf->Cell(271, 8, 'ออกให้ ณ วันที่ ' . date('d/m/') . (date('Y') + 543), 0, 0, 'C');

        // แถบสีเขียวล่าง
        $pdf->Rect(13, 168, 271, 29, 'F', [], [30, 86, 49]);
        $pdf->SetFont('freeserif', '', 11);
        $pdf->SetTextColor(220, 240, 220);
        $pdf->SetXY(13, 178);
        $pdf->Cell(271, 8, 'เอกสารนี้ออกโดยระบบ Uni Calendar — University Activity Calendar System', 0, 0, 'C');

        // บันทึกไฟล์
        $filename = 'cert_' . $user['id'] . '_' . $academicYear . '.pdf';
        $filepath = __DIR__ . '/../public/certificates/' . $filename;
        $pdf->Output($filepath, 'F');

        return $filename;
    }
}