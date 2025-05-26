<?php
// Create assets/js directory if it doesn't exist
if (!file_exists('assets/js')) {
    mkdir('assets/js', 0777, true);
}

// URLs for the libraries
$files = [
    'chart.min.js' => 'https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js',
    'jquery.min.js' => 'https://code.jquery.com/jquery-3.6.0.min.js'
];

// Download files
foreach ($files as $filename => $url) {
    $destination = 'assets/js/' . $filename;
    if (!file_exists($destination)) {
        echo "Downloading {$filename}...\n";
        $content = file_get_contents($url);
        if ($content !== false) {
            file_put_contents($destination, $content);
            echo "Successfully downloaded {$filename}\n";
        } else {
            echo "Failed to download {$filename}\n";
        }
    } else {
        echo "{$filename} already exists\n";
    }
} 