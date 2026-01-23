<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\FinalVote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FinalVoteImageController extends Controller
{
    public function generate()
    {
        // Check if GD is available
        if (!extension_loaded('gd')) {
            \Log::error('GD extension not loaded');
            abort(500, 'Image generation is not available. GD extension is missing.');
        }
        
        // Start output buffering to prevent any output before headers
        ob_start();
        
        try {
            $user = Auth::user();
            
            if (!$user) {
                abort(403, 'Please log in to generate your vote image.');
            }
            
            $selections = FinalVote::where('user_id', $user->id)
                ->with('entry.parent.parent')
                ->with('category')
                ->get()
                ->groupBy('category_id');
            
            if ($selections->isEmpty()) {
                abort(404, 'No votes found.');
            }
            
            $categories = Category::where('year', app('current-year'))
                ->whereIn('id', $selections->keys())
                ->orderBy('order')
                ->get();
            
            // Get username
            $username = $user->reddit_user ?? $user->name ?? 'user';
            $headerText = "u/{$username}'s votes";
            
            // Image dimensions
            $imageWidth = 2000;
            $padding = 40;
            $cardWidth = 320;
            $cardImageHeight = 300;
            $cardTextHeight = 100;
            $cardHeight = $cardImageHeight + $cardTextHeight;
            $imagePadding = 30;
            $categoryHeaderHeight = 50;
            $textPadding = 20;
            $topHeaderHeight = 60;
            
            // Calculate rows and columns - use 4 or 5 per row based on total count
            $totalVotes = $selections->sum(fn($votes) => count($votes));
            $maxPerRow = $totalVotes >= 8 ? 5 : 4;
            
            // Calculate total height needed
            $totalHeight = $padding + $topHeaderHeight + $padding;
            $currentY = $padding;
            
            foreach ($categories as $category) {
                $categoryVotes = $selections->get($category->id, []);
                if (empty($categoryVotes)) {
                    continue;
                }
                
                // Add category header
                $totalHeight += $categoryHeaderHeight;
                
                // Calculate rows for this category
                $rows = ceil(count($categoryVotes) / $maxPerRow);
                $totalHeight += ($rows * $cardHeight) + (($rows - 1) * $imagePadding);
            }
            
            // Create image
            $image = @imagecreatetruecolor($imageWidth, $totalHeight);
            if ($image === false) {
                \Log::error('Failed to create image', [
                    'width' => $imageWidth,
                    'height' => $totalHeight,
                    'memory_limit' => ini_get('memory_limit'),
                    'memory_usage' => memory_get_usage(true),
                ]);
                abort(500, 'Failed to create image. Please try again later.');
            }
        
            // Colors
            $backgroundColorStart = imagecolorallocate($image, 27, 30, 37); // #1B1E25
            $backgroundColorEnd = imagecolorallocate($image, 35, 40, 50); // Slightly lighter for gradient
            $textColor = imagecolorallocate($image, 255, 255, 255);
            $goldColor = imagecolorallocate($image, 231, 169, 36); // #E7A924
            
            if ($textColor === false || $goldColor === false) {
                imagedestroy($image);
                \Log::error('Failed to allocate colors');
                abort(500, 'Failed to generate image. Please try again later.');
            }
        
            // Create gradient background
            for ($y = 0; $y < $totalHeight; $y++) {
                $ratio = $y / $totalHeight;
                $r = 27 + (35 - 27) * $ratio;
                $g = 30 + (40 - 30) * $ratio;
                $b = 37 + (50 - 37) * $ratio;
                $color = imagecolorallocate($image, (int)$r, (int)$g, (int)$b);
                if ($color !== false) {
                    imageline($image, 0, $y, $imageWidth, $y, $color);
                }
            }
            
            // Try to use TTF fonts if available, otherwise use built-in
            $fontPath = null;
            $headerFontSize = 24; // Reduced from 28
            $categoryFontSize = 24;
            $entryFontSize = 18;
            
            // Try common system fonts
            $possibleFonts = [
                base_path('resources/fonts/arial.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/System/Library/Fonts/Helvetica.ttc',
                'C:/Windows/Fonts/arial.ttf',
            ];
            
            foreach ($possibleFonts as $font) {
                if (file_exists($font)) {
                    $fontPath = $font;
                    break;
                }
            }
            
            // Fallback to built-in fonts if no TTF found
            $useTTF = $fontPath !== null;
            if (!$useTTF) {
                $headerFontSize = 5; // Built-in font size
                $categoryFontSize = 5;
                $entryFontSize = 4;
            }
            
            $currentY = $padding;
            
            // Load and draw logo
            $logoPath = public_path('images/awardslogo.png');
            $logoHeight = 60; // Height for logo (increased from 50)
            $logoSpacing = 20; // Space between logo and text
            $logoX = $padding;
            $logoY = $currentY;
            
            if (file_exists($logoPath)) {
                // Suppress PNG sRGB profile warnings
                $logoImage = @imagecreatefrompng($logoPath);
                if ($logoImage !== false) {
                    $logoWidth = imagesx($logoImage);
                    $logoOriginalHeight = imagesy($logoImage);
                    
                    // Calculate proportional width
                    $logoNewWidth = (int)(($logoWidth / $logoOriginalHeight) * $logoHeight);
                    
                    // Resize logo
                    $resizedLogo = @imagecreatetruecolor($logoNewWidth, $logoHeight);
                    if ($resizedLogo !== false) {
                        imagealphablending($resizedLogo, false);
                        imagesavealpha($resizedLogo, true);
                        imagecopyresampled(
                            $resizedLogo, $logoImage,
                            0, 0, 0, 0,
                            $logoNewWidth, $logoHeight,
                            $logoWidth, $logoOriginalHeight
                        );
                        
                        // Copy logo to main image
                        imagealphablending($image, true);
                        imagecopy($image, $resizedLogo, $logoX, $logoY, 0, 0, $logoNewWidth, $logoHeight);
                        
                        imagedestroy($resizedLogo);
                    }
                    imagedestroy($logoImage);
                
                    // Adjust text position to account for logo
                    $textX = $logoX + $logoNewWidth + $logoSpacing;
                } else {
                    $textX = $padding;
                }
            } else {
                $textX = $padding;
            }
            
            // Draw header text (left-aligned after logo) - entire title in gold
            // Vertically center text with logo
            if ($useTTF) {
                // Calculate text bounding box to center it vertically with logo
                $bbox = imagettfbbox($headerFontSize, 0, $fontPath, $headerText);
                if ($bbox !== false) {
                    $textHeight = $bbox[1] - $bbox[7]; // Height of text
                    $logoCenterY = $logoY + ($logoHeight / 2);
                    $textY = $logoCenterY + ($textHeight / 2); // Baseline position for vertical centering
                    imagettftext($image, $headerFontSize, 0, $textX, $textY, $goldColor, $fontPath, $headerText);
                }
            } else {
                // For built-in fonts, center based on font height
                $fontHeight = imagefontheight($headerFontSize);
                $logoCenterY = $logoY + ($logoHeight / 2);
                $textY = $logoCenterY - ($fontHeight / 2);
                imagestring($image, $headerFontSize, $textX, $textY, $headerText, $goldColor);
            }
            $currentY += $topHeaderHeight;
            
            foreach ($categories as $category) {
            $categoryVotes = $selections->get($category->id, []);
            if (empty($categoryVotes)) {
                continue;
            }
            
                // Draw category name
                $categoryName = $category->name;
                $underlineOffset = 3; // Offset below text (reduced to move up)
                $underlineThickness = 4; // Thickness of underline (increased)
                $underlineRightOffset = 40; // Offset to the right from text start
                
                if ($useTTF) {
                    $bbox = imagettfbbox($categoryFontSize, 0, $fontPath, $categoryName);
                    if ($bbox !== false) {
                        $textWidth = $bbox[4] - $bbox[0];
                        $textX = ($imageWidth - $textWidth) / 2;
                        $textY = $currentY + $categoryFontSize;
                        imagettftext($image, $categoryFontSize, 0, $textX, $textY, $textColor, $fontPath, $categoryName);
                
                        // Draw gold underline (offset to the right, shorter width)
                        $underlineY = $textY + $underlineOffset;
                        $underlineStartX = $textX + $underlineRightOffset;
                        $underlineWidth = $textWidth - $underlineRightOffset; // Shorter width due to right offset
                        for ($i = 0; $i < $underlineThickness; $i++) {
                            imageline($image, $underlineStartX, $underlineY + $i, $underlineStartX + $underlineWidth, $underlineY + $i, $goldColor);
                        }
                    }
                } else {
                    $textWidth = imagefontwidth($categoryFontSize) * strlen($categoryName);
                    $textX = ($imageWidth - $textWidth) / 2;
                    $fontHeight = imagefontheight($categoryFontSize);
                    imagestring($image, $categoryFontSize, $textX, $currentY, $categoryName, $textColor);
                    
                    // Draw gold underline (offset to the right, shorter width)
                    $underlineY = $currentY + $fontHeight + $underlineOffset;
                    $underlineStartX = $textX + $underlineRightOffset;
                    $underlineWidth = $textWidth - $underlineRightOffset; // Shorter width due to right offset
                    for ($i = 0; $i < $underlineThickness; $i++) {
                        imageline($image, $underlineStartX, $underlineY + $i, $underlineStartX + $underlineWidth, $underlineY + $i, $goldColor);
                    }
                }
                $currentY += $categoryHeaderHeight;
            
                // Calculate starting X for centering
                $itemsInRow = min($maxPerRow, count($categoryVotes));
                $rowWidth = ($itemsInRow * $cardWidth) + (($itemsInRow - 1) * $imagePadding);
                $startX = ($imageWidth - $rowWidth) / 2;
                
                $currentX = $startX;
                $rowStartY = $currentY;
                $rowIndex = 0;
                
                foreach ($categoryVotes as $index => $vote) {
                // Start new row if needed
                if ($index > 0 && $index % $maxPerRow == 0) {
                    $currentY = $rowStartY + $cardHeight + $imagePadding;
                    $rowStartY = $currentY;
                    $currentX = $startX;
                    $rowIndex++;
                    
                    // Recalculate items in this row
                    $remaining = count($categoryVotes) - $index;
                    $itemsInRow = min($maxPerRow, $remaining);
                    $rowWidth = ($itemsInRow * $cardWidth) + (($itemsInRow - 1) * $imagePadding);
                    $startX = ($imageWidth - $rowWidth) / 2;
                    $currentX = $startX;
                }
                
                $entry = $vote->entry;
                $imagePath = $entry->image ? storage_path('app/public/' . $entry->image) : null;
                
                // Load entry image
                $entryImage = null;
                if ($imagePath && file_exists($imagePath)) {
                    $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
                    switch ($extension) {
                        case 'jpg':
                        case 'jpeg':
                            $entryImage = @imagecreatefromjpeg($imagePath);
                            break;
                        case 'png':
                            // Suppress PNG sRGB profile warnings
                            $entryImage = @imagecreatefrompng($imagePath);
                            break;
                        case 'gif':
                            $entryImage = @imagecreatefromgif($imagePath);
                            break;
                        case 'webp':
                            $entryImage = @imagecreatefromwebp($imagePath);
                            break;
                    }
                }
                
                // If no image, create placeholder
                if (!$entryImage) {
                    $entryImage = imagecreatetruecolor($cardWidth, 300);
                    $placeholderColor = imagecolorallocate($entryImage, 45, 56, 83); // #2d3853
                    imagefill($entryImage, 0, 0, $placeholderColor);
                }
                
                    // Resize entry image to fit card
                    $entryImageWidth = imagesx($entryImage);
                    $entryImageHeight = imagesy($entryImage);
                    $targetWidth = $cardWidth;
                    $targetHeight = $cardImageHeight;
                    
                    $resizedImage = @imagecreatetruecolor($targetWidth, $targetHeight);
                    if ($resizedImage !== false) {
                        imagecopyresampled(
                            $resizedImage, $entryImage,
                            0, 0, 0, 0,
                            $targetWidth, $targetHeight,
                            $entryImageWidth, $entryImageHeight
                        );
                        
                        // Copy resized image to main canvas
                        imagecopy($image, $resizedImage, $currentX, $currentY, 0, 0, $targetWidth, $targetHeight);
                        
                        imagedestroy($resizedImage);
                    }
                
                // Draw entry name below image
                $entryName = $entry->name;
                if ($entry->parent) {
                    $entryName .= ' (' . $entry->parent->name . ')';
                }
                
                // Wrap text if needed
                $textY = $currentY + $targetHeight + $textPadding;
                $maxTextWidth = $cardWidth - ($textPadding * 2);
                
                if ($useTTF) {
                    // Use TTF text wrapping
                    $lines = $this->wrapText($entryName, $entryFontSize, $fontPath, $maxTextWidth);
                    $lineHeight = $entryFontSize * 1.3;
                    
                    foreach ($lines as $line) {
                        $bbox = imagettfbbox($entryFontSize, 0, $fontPath, $line);
                        $lineWidth = $bbox[4] - $bbox[0];
                        $lineX = $currentX + ($cardWidth - $lineWidth) / 2;
                        imagettftext($image, $entryFontSize, 0, $lineX, $textY, $textColor, $fontPath, $line);
                        $textY += $lineHeight;
                    }
                } else {
                    // Use built-in font wrapping
                    $maxCharsPerLine = floor($maxTextWidth / imagefontwidth($entryFontSize));
                    $wrappedText = wordwrap($entryName, $maxCharsPerLine, "\n", true);
                    $lines = explode("\n", $wrappedText);
                    
                    foreach ($lines as $line) {
                        $lineWidth = imagefontwidth($entryFontSize) * strlen($line);
                        $lineX = $currentX + ($cardWidth - $lineWidth) / 2;
                        imagestring($image, $entryFontSize, $lineX, $textY, $line, $textColor);
                        $textY += imagefontheight($entryFontSize) + 5;
                    }
                }
                
                    // Clean up
                    if ($entryImage && $imagePath && file_exists($imagePath)) {
                        imagedestroy($entryImage);
                    }
                    
                    $currentX += $cardWidth + $imagePadding;
                }
                
                // Move to next category
                $currentY = $rowStartY + $cardHeight + ($padding * 2);
            }
        
            // Clear any output before sending headers
            ob_clean();
            
            // Output image
            header('Content-Type: image/png');
            header('Content-Disposition: inline; filename="final-votes.png"');
            
            $result = @imagepng($image);
            imagedestroy($image);
            
            if ($result === false) {
                \Log::error('Failed to output PNG image');
                abort(500, 'Failed to generate image. Please try again later.');
            }
            
            // End output buffering and send output
            ob_end_flush();
            exit;
            
        } catch (\Exception $e) {
            // Clean output buffer
            ob_clean();
            
            \Log::error('Image generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);
            
            abort(500, 'Failed to generate image: ' . $e->getMessage());
        }
    }
    
    /**
     * Wrap text to fit within a maximum width using TTF fonts
     */
    private function wrapText($text, $fontSize, $fontPath, $maxWidth)
    {
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';
        
        foreach ($words as $word) {
            $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $testLine);
            $testWidth = $bbox[4] - $bbox[0];
            
            if ($testWidth > $maxWidth && $currentLine !== '') {
                $lines[] = $currentLine;
                $currentLine = $word;
            } else {
                $currentLine = $testLine;
            }
        }
        
        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }
        
        return $lines;
    }
}
