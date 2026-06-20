<?php
// heatmap/kde_calculator.php
require_once __DIR__ . '/../../includes/config.php';

class KDECalculator {
    private $data;
    private $bandwidth;
    private $kernelType;
    private $minLat;
    private $maxLat;
    private $minLng;
    private $maxLng;
    
    public function __construct($data, $bandwidth = null, $kernelType = 'gaussian') {
        $this->data = $data;
        $this->kernelType = $kernelType;
        
        // Hitung bounds data
        if (!empty($data)) {
            $this->minLat = min(array_column($data, 'latitude'));
            $this->maxLat = max(array_column($data, 'latitude'));
            $this->minLng = min(array_column($data, 'longitude'));
            $this->maxLng = max(array_column($data, 'longitude'));
        }
        
        if ($bandwidth === null) {
            $this->bandwidth = $this->calculateOptimalBandwidth();
        } else {
            $this->bandwidth = $bandwidth;
        }
    }
    
    /**
     * Menghitung bandwidth optimal menggunakan Silverman's Rule of Thumb
     */
    private function calculateOptimalBandwidth() {
        $n = count($this->data);
        if ($n < 2) return 0.01;
        
        $lats = array_column($this->data, 'latitude');
        $lngs = array_column($this->data, 'longitude');
        
        $stdLat = $this->calculateStdDev($lats);
        $stdLng = $this->calculateStdDev($lngs);
        $std = ($stdLat + $stdLng) / 2;
        
        // Silverman's rule untuk 2D
        $h = pow(4/(2+2), 1/(2+4)) * pow($n, -1/(2+4)) * $std;
        
        return max($h, 0.001);
    }
    
    /**
     * Menghitung standar deviasi
     */
    private function calculateStdDev($array) {
        $n = count($array);
        if ($n < 2) return 0;
        
        $mean = array_sum($array) / $n;
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $array)) / $n;
        
        return sqrt($variance);
    }
    
    /**
     * Kernel Gaussian 2D
     */
    private function gaussianKernel($distance) {
        $h = $this->bandwidth;
        $sigma = $h / 2.0;
        
        if ($sigma == 0) return 0;
        
        return (1 / (2 * M_PI * pow($sigma, 2))) * 
               exp(-pow($distance, 2) / (2 * pow($sigma, 2)));
    }
    
    /**
     * Kernel Epanechnikov 2D
     */
    private function epanechnikovKernel($distance) {
        $h = $this->bandwidth;
        
        if ($distance > $h || $h == 0) return 0;
        
        $ratio = $distance / $h;
        return (2 / M_PI) * (1 - pow($ratio, 2));
    }
    
    /**
     * Kernel Quartic (Biweight)
     */
    private function quarticKernel($distance) {
        $h = $this->bandwidth;
        
        if ($distance > $h || $h == 0) return 0;
        
        $ratio = $distance / $h;
        return (15 / (16 * M_PI)) * pow(1 - pow($ratio, 2), 2);
    }
    
    /**
     * Jarak Euclidean antar titik koordinat
     */
    private function euclideanDistance($lat1, $lng1, $lat2, $lng2) {
        return sqrt(pow($lat1 - $lat2, 2) + pow($lng1 - $lng2, 2));
    }
    
    /**
     * Menghitung estimasi KDE untuk satu titik
     */
    public function estimate($lat, $lng) {
        $density = 0;
        $n = count($this->data);
        
        if ($n === 0) return 0;
        
        foreach ($this->data as $point) {
            $distance = $this->euclideanDistance(
                $lat, $lng, 
                $point['latitude'], $point['longitude']
            );
            
            switch ($this->kernelType) {
                case 'gaussian':
                    $density += $this->gaussianKernel($distance);
                    break;
                case 'epanechnikov':
                    $density += $this->epanechnikovKernel($distance);
                    break;
                case 'quartic':
                    $density += $this->quarticKernel($distance);
                    break;
                default:
                    $density += $this->gaussianKernel($distance);
            }
        }
        
        return $density / $n;
    }
    
    /**
     * Menghitung KDE untuk grid dengan optimasi
     */
    public function estimateGridOptimized($bounds, $gridSize = 50) {
        $grid = [];
        $stepLat = ($bounds['maxLat'] - $bounds['minLat']) / $gridSize;
        $stepLng = ($bounds['maxLng'] - $bounds['minLng']) / $gridSize;
        
        // Filter data dalam batas + bandwidth
        $filteredData = array_filter($this->data, function($point) use ($bounds) {
            return $point['latitude'] >= $bounds['minLat'] - $this->bandwidth &&
                   $point['latitude'] <= $bounds['maxLat'] + $this->bandwidth &&
                   $point['longitude'] >= $bounds['minLng'] - $this->bandwidth &&
                   $point['longitude'] <= $bounds['maxLng'] + $this->bandwidth;
        });
        
        if (empty($filteredData)) {
            $filteredData = $this->data;
        }
        
        $tempData = $this->data;
        $this->data = array_values($filteredData);
        
        $lat = $bounds['minLat'];
        for ($i = 0; $i < $gridSize; $i++) {
            $row = [];
            $lng = $bounds['minLng'];
            for ($j = 0; $j < $gridSize; $j++) {
                $row[] = $this->estimate($lat, $lng);
                $lng += $stepLng;
            }
            $grid[] = $row;
            $lat += $stepLat;
        }
        
        $this->data = $tempData;
        
        return $grid;
    }
    
    /**
     * Mendapatkan statistik KDE
     */
    public function getStatistics() {
        return [
            'total_points' => count($this->data),
            'bandwidth' => $this->bandwidth,
            'kernel_type' => $this->kernelType,
            'min_lat' => $this->minLat,
            'max_lat' => $this->maxLat,
            'min_lng' => $this->minLng,
            'max_lng' => $this->maxLng
        ];
    }
}

/**
 * API function untuk mendapatkan data KDE
 */
function getKDEHeatmapData($conn, $params) {
    // Default bounds berdasarkan data
    $defaultBounds = [
        'minLat' => -3.50,
        'maxLat' => -3.42,
        'minLng' => 114.70,
        'maxLng' => 114.90
    ];
    
    $bounds = [
        'minLat' => isset($params['minLat']) ? (float)$params['minLat'] : $defaultBounds['minLat'],
        'maxLat' => isset($params['maxLat']) ? (float)$params['maxLat'] : $defaultBounds['maxLat'],
        'minLng' => isset($params['minLng']) ? (float)$params['minLng'] : $defaultBounds['minLng'],
        'maxLng' => isset($params['maxLng']) ? (float)$params['maxLng'] : $defaultBounds['maxLng']
    ];
    
    $gridSize = isset($params['gridSize']) ? (int)$params['gridSize'] : 50;
    $bandwidth = isset($params['bandwidth']) ? (float)$params['bandwidth'] : null;
    $kernelType = isset($params['kernel']) ? $params['kernel'] : 'gaussian';
    
    // Query dengan FORCE INDEX untuk performa optimal
    $sql = "SELECT latitude, longitude, id, kecamatan, waktu 
            FROM kejadian_kebakaran 
            FORCE INDEX (idx_location)
            WHERE latitude IS NOT NULL 
            AND longitude IS NOT NULL 
            AND latitude BETWEEN ? AND ?
            AND longitude BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [
            'status' => 'error',
            'message' => 'Database error: ' . $conn->error
        ];
    }
    
    $stmt->bind_param("dddd", 
        $bounds['minLat'], $bounds['maxLat'],
        $bounds['minLng'], $bounds['maxLng']
    );
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'latitude' => (float)$row['latitude'],
            'longitude' => (float)$row['longitude'],
            'id' => (int)$row['id'],
            'kecamatan' => $row['kecamatan'],
            'waktu' => $row['waktu']
        ];
    }
    $stmt->close();
    
    if (count($data) < 2) {
        return [
            'status' => 'error',
            'message' => 'Data tidak mencukupi untuk KDE (minimal 2 titik)',
            'total_points' => count($data)
        ];
    }
    
    // Hitung KDE
    $kde = new KDECalculator($data, $bandwidth, $kernelType);
    $gridData = $kde->estimateGridOptimized($bounds, $gridSize);
    
    // Normalisasi data
    $minVal = min(array_map('min', $gridData));
    $maxVal = max(array_map('max', $gridData));
    
    $normalizedData = [];
    foreach ($gridData as $row) {
        $normalizedRow = [];
        foreach ($row as $value) {
            if ($maxVal > $minVal) {
                $normalizedRow[] = ($value - $minVal) / ($maxVal - $minVal);
            } else {
                $normalizedRow[] = 0;
            }
        }
        $normalizedData[] = $normalizedRow;
    }
    
    return [
        'status' => 'success',
        'grid_data' => $normalizedData,
        'raw_data' => $gridData,
        'bounds' => $bounds,
        'grid_size' => $gridSize,
        'total_points' => count($data),
        'bandwidth' => $kde->bandwidth,
        'kernel_type' => $kernelType,
        'statistics' => $kde->getStatistics(),
        'index_used' => 'idx_location'
    ];
}
?>