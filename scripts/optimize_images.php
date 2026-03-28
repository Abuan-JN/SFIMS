<?php

/**
 * Image Optimization Script for SFIMS
 * 
 * This script optimizes images in the assets/img directory:
 * 1. Compresses PNG and JPEG images
 * 2. Generates WebP versions for modern browsers
 * 3. Creates responsive image sizes
 * 
 * Usage: php scripts/optimize_images.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

$imageDir = dirname(__DIR__) . '/assets/img';
$supportedFormats = ['png', 'jpg', 'jpeg'];

echo "Starting image optimization...\n";

// Get all images in the directory
$images = [];
foreach ($supportedFormats as $format) {
    $images = array_merge($images, glob($imageDir . '/*.' . $format));
    $images = array_merge($images, glob($imageDir . '/*.' . strtoupper($format)));
}

if (empty($images)) {
    echo "No images found to optimize.\n";
    exit(0);
}

foreach ($images as $imagePath) {
    $filename = pathinfo($imagePath, PATHINFO_FILENAME);
    $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
    
    echo "Processing: {$filename}.{$extension}\n";
    
    // Get original file size
    $originalSize = filesize($imagePath);
    echo "  Original size: " . formatBytes($originalSize) . "\n";
    
    // Create optimized version
    $optimizedPath = $imageDir . '/' . $filename . '_optimized.' . $extension;
    
    if (optimizeImage($imagePath, $optimizedPath, $extension)) {
        $optimizedSize = filesize($optimizedPath);
        $savings = $originalSize - $optimizedSize;
        $savingsPercent = round(($savings / $originalSize) * 100, 2);
        
        echo "  Optimized size: " . formatBytes($optimizedSize) . "\n";
        echo "  Savings: " . formatBytes($savings) . " ({$savingsPercent}%)\n";
        
        // Replace original with optimized version
        unlink($imagePath);
        rename($optimizedPath, $imagePath);
        
        // Generate WebP version
        $webpPath = $imageDir . '/' . $filename . '.webp';
        if (generateWebP($imagePath, $webpPath)) {
            echo "  WebP version created: " . formatBytes(filesize($webpPath)) . "\n";
        }
        
        // Generate responsive sizes
        generateResponsiveSizes($imagePath, $filename, $extension);
    } else {
        echo "  Failed to optimize image.\n";
    }
    
    echo "\n";
}

echo "Image optimization complete!\n";

function optimizeImage(string $source, string $destination, string $format): bool
{
    $image = null;
    
    switch ($format) {
        case 'jpg':
        case 'jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'png':
            $image = imagecreatefrompng($source);
            break;
        default:
            return false;
    }
    
    if ($image === null) {
        return false;
    }
    
    // Optimize based on format
    switch ($format) {
        case 'jpg':
        case 'jpeg':
            $result = imagejpeg($image, $destination, 85); // 85% quality
            break;
        case 'png':
            $result = imagepng($image, $destination, 6); // Compression level 6
            break;
    }
    
    imagedestroy($image);
    return $result;
}

function generateWebP(string $source, string $destination): bool
{
    $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    $image = null;
    
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'png':
            $image = imagecreatefrompng($source);
            break;
        default:
            return false;
    }
    
    if ($image === null) {
        return false;
    }
    
    $result = imagewebp($image, $destination, 85);
    imagedestroy($image);
    
    return $result;
}

function generateResponsiveSizes(string $source, string $filename, string $extension): void
{
    $imageDir = dirname($source);
    $image = null;
    
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'png':
            $image = imagecreatefrompng($source);
            break;
        default:
            return;
    }
    
    if ($image === null) {
        return;
    }
    
    $originalWidth = imagesx($image);
    $originalHeight = imagesy($image);
    
    // Define responsive sizes
    $sizes = [
        'small' => 400,
        'medium' => 800,
        'large' => 1200
    ];
    
    foreach ($sizes as $sizeName => $maxWidth) {
        if ($originalWidth <= $maxWidth) {
            continue;
        }
        
        $ratio = $maxWidth / $originalWidth;
        $newWidth = $maxWidth;
        $newHeight = intval($originalHeight * $ratio);
        
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG
        if ($extension === 'png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
        }
        
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        
        $resizedPath = $imageDir . '/' . $filename . '_' . $sizeName . '.' . $extension;
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($resized, $resizedPath, 85);
                break;
            case 'png':
                imagepng($resized, $resizedPath, 6);
                break;
        }
        
        imagedestroy($resized);
        
        echo "  Created {$sizeName} version: " . formatBytes(filesize($resizedPath)) . "\n";
    }
    
    imagedestroy($image);
}

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}
