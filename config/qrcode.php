<?php

return [
    'writer' => Endroid\QrCode\Writer\PngWriter::class,
    'encoding' => 'UTF-8',
    'error_correction_level' => Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh::class,
    'size' => 300,
    'margin' => 10,
    'round_block_size_mode' => Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin::class,
    'label_text' => null,
    'label_font_size' => 16,
];
