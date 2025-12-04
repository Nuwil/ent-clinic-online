<?php
/**
 * Prescription Controller - Handles prescription generation and export
 */

require_once __DIR__ . '/Controller.php';

class PrescriptionController extends Controller
{
    /**
     * POST /api/prescription/export - Export prescription as PDF or Word
     */
    public function export()
    {
        try {
            $input = $this->getInput();
            $patientId = $input['patient_id'] ?? null;
            $exportFormat = $input['export_format'] ?? 'pdf';
            $medicinesSelected = $input['medicines_selected'] ?? '';
            $prescriptionNotes = $input['prescription_notes'] ?? '';
            $refill = $input['refill'] ?? '';
            $label = isset($input['label_checkbox']) ? '☑' : '☐';
            $prescriptionDate = $input['prescription_date'] ?? date('Y-m-d');
            $signature = $input['signature'] ?? '';

            if (!$patientId) {
                $this->error('Patient ID is required', 400);
            }

            // Fetch patient data using PDO
            $patientQuery = "SELECT first_name, last_name, address FROM patients WHERE id = ?";
            $stmt = $this->db->prepare($patientQuery);
            $stmt->execute([$patientId]);
            $patient = $stmt->fetch();

            if (!$patient) {
                $this->error('Patient not found', 404);
            }

            $patientName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
            $patientAddress = $patient['address'] ?? '';

            // Decode medicines payload if JSON
            $medicinesData = $medicinesSelected;
            if (!empty($medicinesSelected) && (is_string($medicinesSelected) && (strpos(trim($medicinesSelected), '[') === 0 || strpos(trim($medicinesSelected), '{') === 0))) {
                $decoded = json_decode($medicinesSelected, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $medicinesData = $decoded;
                }
            }

            // Persist prescription items if we have structured medicines array
            if (!empty($medicinesData) && is_array($medicinesData)) {
                $visitId = $input['visit_id'] ?? null;
                $doctor = $this->getApiUser();
                $doctorId = $doctor['id'] ?? null;

                // If no visit_id provided, create a minimal visit to attach prescription items
                if (empty($visitId)) {
                    $insertVisit = $this->db->prepare("INSERT INTO patient_visits (patient_id, visit_date, visit_type, created_at) VALUES (?, ?, ?, NOW())");
                    $insertVisit->execute([$patientId, date('Y-m-d H:i:s'), 'prescription_export']);
                    $visitId = $this->db->lastInsertId();
                }

                $insertPresc = $this->db->prepare("INSERT INTO prescription_items (visit_id, patient_id, medicine_id, medicine_name, instruction, doctor_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                foreach ($medicinesData as $m) {
                    if (is_array($m)) {
                        $medId = $m['id'] ?? null;
                        $medName = $m['name'] ?? ($m['medicine_name'] ?? null) ?? (isset($m[0]) ? $m[0] : null);
                        $instr = $m['instruction'] ?? $m['note'] ?? '';
                    } else {
                        $medId = null;
                        $medName = (string)$m;
                        $instr = '';
                    }
                    $insertPresc->execute([$visitId, $patientId, $medId, $medName, $instr, $doctorId]);
                }
            }

            // Generate HTML and export according to requested format
            $html = $this->generatePrescriptionHTML($patientName, $patientAddress, $medicinesData, $prescriptionNotes, $refill, $label, $prescriptionDate, $signature);

            if ($exportFormat === 'pdf') {
                $this->exportPDF($html, $patientId);
            } elseif ($exportFormat === 'word') {
                $this->exportWord($html, $patientId);
            } else {
                // Default: return HTML
                header('Content-Type: text/html; charset=UTF-8');
                echo $html;
                exit;
            }

        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/prescription/items - Return prescription items for a visit or patient
     */
    public function items()
    {
        try {
            $visitId = $_GET['visit_id'] ?? null;
            $patientId = $_GET['patient_id'] ?? null;

            if (empty($visitId) && empty($patientId)) {
                $this->error('visit_id or patient_id is required', 400);
            }

            if (!empty($visitId)) {
                $sql = "SELECT * FROM prescription_items WHERE visit_id = ? ORDER BY created_at DESC";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([(int)$visitId]);
                $rows = $stmt->fetchAll();
                $this->success($rows);
            } else {
                $sql = "SELECT * FROM prescription_items WHERE patient_id = ? ORDER BY created_at DESC";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([(int)$patientId]);
                $rows = $stmt->fetchAll();
                $this->success($rows);
            }

        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Generate HTML prescription template
     */
    private function generatePrescriptionHTML($patientName, $patientAddress, $medicines, $notes, $refill, $label, $date, $signature)
    {
        $medicinesList = '';
        // If $medicines is an array (structured with id/name/instruction), render with instructions
        if (!empty($medicines)) {
            if (is_array($medicines)) {
                $lines = [];
                foreach ($medicines as $m) {
                    $name = isset($m['name']) ? $m['name'] : (isset($m[0]) ? $m[0] : '');
                    $instr = isset($m['instruction']) ? trim($m['instruction']) : '';
                    $lines[] = '• ' . trim($name) . ($instr !== '' ? ' — ' . htmlspecialchars($instr) : '');
                }
                $medicinesList = '<div style="margin: 1rem 0; line-height: 2;">' . implode('<br>', $lines) . '</div>';
            } else {
                // fallback: string with semicolon-separated names
                $medicinesArray = explode(';', $medicines);
                $medicinesList = '<div style="margin: 1rem 0; line-height: 2;">'
                    . implode('<br>', array_map(fn($m) => '• ' . trim($m), $medicinesArray))
                    . '</div>';
            }
        }

        $notesHtml = '';
        if (!empty($notes)) {
            $notesHtml = '<div style="margin: 1rem 0; white-space: pre-wrap;">' . htmlspecialchars($notes) . '</div>';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Prescription</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .prescription-container {
            background-color: white;
            width: 8.5in;
            height: 11in;
            margin: 0 auto;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }
        .header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 40px;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
        }
        .rx-logo {
            font-size: 48px;
            font-weight: bold;
            margin-right: 20px;
            line-height: 0.8;
        }
        .patient-info {
            flex: 1;
        }
        .patient-info-row {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        .patient-info-row label {
            font-weight: bold;
            width: 120px;
        }
        .patient-info-row input {
            border: none;
            border-bottom: 1px solid #000;
            flex: 1;
            padding: 5px 0;
            font-size: 14px;
        }
        .prescription-label {
            font-weight: bold;
            margin: 20px 0 10px 0;
            font-size: 16px;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }
        .medicines-list {
            margin: 20px 0;
            line-height: 2;
            padding: 10px;
            border: 1px solid #ccc;
            min-height: 200px;
        }
        .footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .footer-item {
            flex: 1;
        }
        .footer-row {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #000;
        }
        .footer-field {
            text-align: center;
            min-width: 150px;
        }
        .footer-label {
            font-size: 12px;
            color: #666;
        }
        .checkbox {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="prescription-container">
        <!-- Header with Rx Logo and Patient Info -->
        <div class="header">
            <div class="rx-logo">
                R<br>X
            </div>
            <div class="patient-info">
                <div class="patient-info-row">
                    <label>Patient name</label>
                    <input type="text" value="{$patientName}" readonly />
                </div>
                <div class="patient-info-row">
                    <label>Address</label>
                    <input type="text" value="{$patientAddress}" readonly />
                </div>
                <div style="margin-top: 10px; border-bottom: 1px solid #000;"></div>
            </div>
        </div>

        <!-- Prescription Section -->
        <div class="prescription-label">Prescription:</div>
        <div class="medicines-list">
            {$medicinesList}
            {$notesHtml}
        </div>

        <!-- Footer with Refill, Label, Date, Signature -->
        <div class="footer-row">
            <div class="footer-field">
                <div>Refill {$refill}</div>
                <div class="footer-label">Refill</div>
            </div>
            <div class="footer-field">
                <div>Label {$label}</div>
                <div class="footer-label">Label</div>
            </div>
        </div>

        <div class="footer-row" style="margin-top: 40px;">
            <div class="footer-field">
                <div>_________________</div>
                <div class="footer-label">Date: {$date}</div>
            </div>
            <div class="footer-field">
                <div>_________________</div>
                <div class="footer-label">Signature: {$signature}</div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Export prescription as PDF
     */
    private function exportPDF($html, $patientId)
    {
        // Check if dompdf is available
        $dompdfPath = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($dompdfPath)) {
            // Fallback: generate a simple printable HTML response
            header('Content-Type: text/html; charset=UTF-8');
            echo $html;
            exit;
        }

        require_once $dompdfPath;

        try {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="prescription_' . $patientId . '_' . date('Ymd_His') . '.pdf"');
            echo $dompdf->output();
            exit;
        } catch (Exception $e) {
            // Fallback to HTML if dompdf fails
            header('Content-Type: text/html; charset=UTF-8');
            echo $html;
            exit;
        }
    }

    /**
     * Export prescription as Word Document
     */
    private function exportWord($html, $patientId)
    {
        // Check if phpword is available
        $phpwordPath = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($phpwordPath)) {
            // Fallback: convert HTML to Word-compatible format
            $this->generateWordFallback($html, $patientId);
            exit;
        }

        require_once $phpwordPath;

        try {
            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $section = $phpWord->addSection();

            // Convert HTML to Word (basic)
            // For simplicity, we'll add text content directly
            // In production, you might use a more sophisticated HTML to Word converter

            $section->addText('Prescription', ['size' => 16, 'bold' => true]);
            $section->addTextBreak();

            // Extract text from HTML and add to Word
            $text = strip_tags($html);
            $section->addText($text);

            $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="prescription_' . $patientId . '_' . date('Ymd_His') . '.docx"');
            
            $objWriter->save('php://output');
            exit;
        } catch (Exception $e) {
            // Fallback
            $this->generateWordFallback($html, $patientId);
            exit;
        }
    }

    /**
     * Fallback: Generate Word-compatible HTML (RTF-like)
     */
    private function generateWordFallback($html, $patientId)
    {
        $filename = 'prescription_' . $patientId . '_' . date('Ymd_His') . '.html';
        
        header('Content-Type: application/vnd.ms-word');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Wrap HTML in Office-compatible format
        $output = <<<WORD
<html xmlns:o='urn:schemas-microsoft-com:office:office'
xmlns:w='urn:schemas-microsoft-com:office:word'
xmlns='http://www.w3.org/TR/REC-html40'>
<head>
<meta charset=UTF-8>
<title>Prescription</title>
</head>
<body>
{$html}
</body>
</html>
WORD;

        echo $output;
    }
}
