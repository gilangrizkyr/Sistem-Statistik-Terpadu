<?php

namespace App\Controllers;

use App\Services\DashboardService;
use App\Services\UploadService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class Dashboard extends BaseController
{
    protected $dashboardService;
    protected $uploadService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->dashboardService = new DashboardService();
        $this->uploadService = new UploadService();
    }

    public function index()
    {
        $filters = $this->getFilters();
        $data = $this->dashboardService->getDashboardData($filters);
        
        return view('dashboard', ['data' => $data]);
    }

    public function upload()
    {
        $file = $this->request->getFile('excel_file');
        $result = $this->uploadService->handleUpload($file);

        if ($result['success']) {
            return redirect()->to('/dashboard/metadata/' . $result['uploadId'])
                ->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    public function metadata($uploadId)
    {
        $upload = $this->uploadService->getUploadById($uploadId);

        if (!$upload || $upload['status'] !== 'uploaded') {
            return redirect()->to('/dashboard')->with('error', 'Upload tidak ditemukan atau tidak valid.');
        }

        return view('upload_metadata', ['upload' => $upload]);
    }

    public function processMetadata()
    {
        $metadata = [
            'upload_id' => $this->request->getPost('upload_id'),
            'upload_name' => $this->request->getPost('upload_name'),
            'quarter' => $this->request->getPost('quarter'),
            'year' => $this->request->getPost('year'),
            'usd_value' => $this->request->getPost('usd_value')
        ];

        $result = $this->uploadService->processMetadata($metadata);

        if ($result['success']) {
            return redirect()->to('/dashboard')->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    public function editMetadata($uploadId)
    {
        $upload = $this->uploadService->getUploadById($uploadId);

        if (!$upload) {
            return redirect()->to('/dashboard')->with('error', 'Upload tidak ditemukan.');
        }

        return view('upload_metadata', ['upload' => $upload, 'isEdit' => true]);
    }

    public function updateMetadata()
    {
        $metadata = [
            'upload_id' => $this->request->getPost('upload_id'),
            'upload_name' => $this->request->getPost('upload_name'),
            'quarter' => $this->request->getPost('quarter'),
            'year' => $this->request->getPost('year'),
            'usd_value' => $this->request->getPost('usd_value')
        ];

        $result = $this->uploadService->updateMetadata($metadata);

        if ($result['success']) {
            return redirect()->to('/dashboard')->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    public function deleteUpload()
    {
        $uploadId = $this->request->getPost('upload_id');
        $result = $this->uploadService->deleteUpload($uploadId);

        if ($result['success']) {
            return redirect()->to('/dashboard')->with('success', $result['message']);
        }

        return redirect()->to('/dashboard')->with('error', $result['message']);
    }

    public function download()
    {
        $result = $this->dashboardService->generateExcelDownload();

        if (!$result['success']) {
            return redirect()->to('/dashboard')->with('error', $result['message']);
        }

        // Output Excel file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($result['spreadsheet']);
        $filename = 'hasil_analisis_' . date('Y-m-d_H-i-s') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;
    }

    public function setLanguage()
    {
        $language = $this->request->getPost('language');

        if (!in_array($language, ['id', 'en'])) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid language']);
        }

        // Set the locale in the session
        session()->set('locale', $language);
        service('request')->setLocale($language);

        // Force reload the language service with new locale
        $languageService = \Config\Services::language();
        $languageService->setLocale($language);
        $languageService->getLine('Dashboard.dashboard_title');

        return $this->response->setJSON(['success' => true, 'message' => 'Language set successfully']);
    }

    private function getFilters(): array
    {
        return [
            'upload' => $this->request->getGet('upload') ?? 'all',
            'quarter' => $this->request->getGet('quarter') ?? 'all',
            'year' => $this->request->getGet('year') ?? 'all',
            'quarterly_year' => $this->request->getGet('quarterly_year') ?? 'all',
            'currency' => $this->request->getGet('currency') ?? 'IDR'
        ];
    }
}