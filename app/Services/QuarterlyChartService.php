<?php

namespace App\Services;

use App\Models\ProjectModel;
use App\Models\UploadModel;

class QuarterlyChartService
{
    protected $uploadModel;
    protected $projectModel;

    public function __construct()
    {
        $this->uploadModel = new UploadModel();
        $this->projectModel = new ProjectModel();
    }

    public function generate(string $selectedYear, float $usdRate, string $selectedCurrency): array
    {
        $allUploads = $this->uploadModel->getAllUploads() ?? [];

        log_message('debug', 'QuarterlyChartService - Total uploads: ' . count($allUploads));
        log_message('debug', 'QuarterlyChartService - Selected year: ' . $selectedYear);

        $quarterlyData = $this->initializeQuarterlyData();

        foreach ($allUploads as $upload) {
            $this->processUpload($upload, $selectedYear, $quarterlyData);
        }

        log_message('debug', 'Final quarterly data before conversion: ' . json_encode($quarterlyData));

        // Convert to USD if needed
        if ($selectedCurrency === 'USD') {
            $this->convertToUSD($quarterlyData, $usdRate);
        }

        return [
            'labels' => ['Q1', 'Q2', 'Q3', 'Q4'],
            'values' => array_values($quarterlyData),
            'year' => $selectedYear === 'all' ? 'Semua Tahun' : $selectedYear
        ];
    }

    public function generateAllYears(float $usdRate, string $selectedCurrency): array
    {
        $allUploads = $this->uploadModel->getAllUploads() ?? [];

        log_message('debug', 'QuarterlyChartService (All Years) - Total uploads: ' . count($allUploads));

        $yearlyQuarterlyData = [];

        foreach ($allUploads as $upload) {
            if ($upload['status'] === 'completed') {
                $year = isset($upload['year']) ? strval($upload['year']) : 'Unknown';
                
                if (!isset($yearlyQuarterlyData[$year])) {
                    $yearlyQuarterlyData[$year] = $this->initializeQuarterlyData();
                    log_message('debug', 'Initialized year: ' . $year);
                }

                $this->processUploadForYear($upload, $yearlyQuarterlyData[$year]);
            }
        }

        log_message('debug', 'Yearly quarterly data before conversion: ' . json_encode($yearlyQuarterlyData));

        // Convert to USD if needed
        if ($selectedCurrency === 'USD') {
            foreach ($yearlyQuarterlyData as $year => &$quarterlyData) {
                $this->convertToUSD($quarterlyData, $usdRate);
            }
        }

        // Convert to expected format
        $result = [];
        foreach ($yearlyQuarterlyData as $year => $quarterlyData) {
            $result[$year] = [
                'labels' => ['Q1', 'Q2', 'Q3', 'Q4'],
                'values' => array_values($quarterlyData)
            ];
        }

        log_message('debug', 'QuarterlyChartService (All Years) - Final result: ' . json_encode($result));

        return $result;
    }

    private function initializeQuarterlyData(): array
    {
        return [
            'Q1' => 0,
            'Q2' => 0,
            'Q3' => 0,
            'Q4' => 0
        ];
    }

    private function processUpload(array $upload, string $selectedYear, array &$quarterlyData): void
    {
        log_message('debug', 'Processing upload ID: ' . ($upload['id'] ?? 'N/A') .
            ', Status: ' . ($upload['status'] ?? 'N/A') .
            ', Year: ' . ($upload['year'] ?? 'N/A') .
            ', Quarter: ' . ($upload['quarter'] ?? 'N/A'));

        if ($upload['status'] !== 'completed') {
            return;
        }

        if ($selectedYear !== 'all' && $upload['year'] != $selectedYear) {
            return;
        }

        $uploadId = $upload['id'];
        $additionalInvestment = $this->projectModel->getAdditionalInvestment($uploadId, []);

        log_message('debug', 'Upload ID ' . $uploadId . ' - PMA: ' . ($additionalInvestment['PMA'] ?? 0) .
            ', PMDN: ' . ($additionalInvestment['PMDN'] ?? 0));

        $quarterKey = isset($upload['quarter']) ? strtoupper(trim($upload['quarter'])) : '';

        if (in_array($quarterKey, ['Q1', 'Q2', 'Q3', 'Q4'])) {
            $totalInvestment = $this->calculateTotalInvestment($additionalInvestment);
            $quarterlyData[$quarterKey] += $totalInvestment;

            log_message('debug', 'Added to ' . $quarterKey . ': ' . $totalInvestment .
                ' (Total now: ' . $quarterlyData[$quarterKey] . ')');
        } else {
            log_message('warning', 'Invalid quarter for upload ID ' . $uploadId . ': ' . $quarterKey);
        }
    }

    private function processUploadForYear(array $upload, array &$quarterlyData): void
    {
        $uploadId = $upload['id'];
        $quarterKey = isset($upload['quarter']) ? strtoupper(trim($upload['quarter'])) : '';

        $additionalInvestment = $this->projectModel->getAdditionalInvestment($uploadId, []);

        log_message('debug', 'Upload ID ' . $uploadId . ' - PMA: ' . 
            ($additionalInvestment['PMA'] ?? 0) . ', PMDN: ' . ($additionalInvestment['PMDN'] ?? 0));

        if (in_array($quarterKey, ['Q1', 'Q2', 'Q3', 'Q4'])) {
            $totalInvestment = $this->calculateTotalInvestment($additionalInvestment);
            $quarterlyData[$quarterKey] += $totalInvestment;

            log_message('debug', 'Added to ' . $quarterKey . ': ' . $totalInvestment .
                ' (Total now: ' . $quarterlyData[$quarterKey] . ')');
        } else {
            log_message('warning', 'Invalid quarter for upload ID ' . $uploadId . ': ' . $quarterKey);
        }
    }

    private function calculateTotalInvestment(array $additionalInvestment): float
    {
        $pmaInvestment = isset($additionalInvestment['PMA']) ? floatval($additionalInvestment['PMA']) : 0;
        $pmdnInvestment = isset($additionalInvestment['PMDN']) ? floatval($additionalInvestment['PMDN']) : 0;
        return $pmaInvestment + $pmdnInvestment;
    }

    private function convertToUSD(array &$quarterlyData, float $usdRate): void
    {
        foreach ($quarterlyData as $quarter => &$amount) {
            $amount = round($amount / $usdRate, 2);
        }
        log_message('debug', 'Converted to USD with rate ' . $usdRate);
    }
}