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
            $patientId = $_POST['patient_id'] ?? null;
            $exportFormat = $_POST['export_format'] ?? 'pdf';
            $medicinesSelected = $_POST['medicines_selected'] ?? '';
            $prescriptionNotes = $_POST['prescription_notes'] ?? '';
            $refill = $_POST['refill'] ?? '';
            $label = isset($_POST['label_checkbox']) ? '☑' : '☐';
            $prescriptionDate = $_POST['prescription_date'] ?? date('Y-m-d');
            $signature = $_POST['signature'] ?? '';

            if (!$patientId) {
                $this->error('Patient ID is required', 400);
            }

            // Fetch patient data
            $patientQuery = "SELECT first_name, last_name, address FROM patients WHERE id = ?";
            $stmt = $this->db->prepare($patientQuery);
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $this->db->error);
            }
            $stmt->bind_param('i', $patientId);
            $stmt->execute();
            $patientResult = $stmt->get_result();
            $patient = $patientResult->fetch_assoc();

            if (!$patient) {
                $this->error('Patient not found', 404);
            }

            $patientName = $patient['first_name'] . ' ' . $patient['last_name'];
            $patientAddress = $patient['address'] ?? '';

            // Generate HTML prescription template
            $html = $this->generatePrescriptionHTML(
                $patientName,
                $patientAddress,
                $medicinesSelected,
                $prescriptionNotes,
                $refill,
                $label,
                $prescriptionDate,
                $signature
            );

            if ($exportFormat === 'pdf') {
                $this->exportPDF($html, $patientId);
            } elseif ($exportFormat === 'word') {
                $this->exportWord($html, $patientId);
            } else {
                $this->error('Invalid export format', 400);
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
        if (!empty($medicines)) {
            $medicinesArray = explode(';', $medicines);
            $medicinesList = '<div style="margin: 1rem 0; line-height: 2;">
                ' . implode('<br>', array_map(fn($m) => '• ' . trim($m), $medicinesArray)) . '
            </div>';
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
