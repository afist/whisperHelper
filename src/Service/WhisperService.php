<?php

declare(strict_types=1);

namespace App\Service;

class WhisperService
{
    public function processAudio($filePath)
    {
        $outputDir = dirname($filePath);
        $command = escapeshellcmd("whisper " . escapeshellarg($filePath) . " --model small --output_dir " . escapeshellarg($outputDir));
        exec($command, $output, $return_var);

        if ($return_var === 0) {
            $transcriptFile = $outputDir . '/' . pathinfo($filePath, PATHINFO_FILENAME) . '.txt';
            if (file_exists($transcriptFile)) {
                return file_get_contents($transcriptFile);
            }
        }

        return "Ошибка обработки аудиофайла.";
    }
}