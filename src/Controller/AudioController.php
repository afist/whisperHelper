<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AudioController extends AbstractController
{
    #[Route('/', name: 'app_upload', methods: ['GET'])]
    public function showUploadForm(): Response
    {
        return $this->render('upload_form.html.twig');
    }

    #[Route('/upload-file', name: 'app_upload_file', methods: ['POST'])]
    public function handleFileUpload(Request $request): JsonResponse
    {
        $uploadedFile = $request->files->get('audio_file');

        if ($uploadedFile) {
            $uniqueFolder = uniqid('upload_', true);
            $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/'.$uniqueFolder;

            if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                return new JsonResponse(
                    ['status' => 'error', 'message' => 'Failed to create download directory'], 500
                );
            }

            $newFilename = uniqid('', true).'.'.$uploadedFile->guessExtension();

            try {
                $uploadedFile->move($uploadDir, $newFilename);

                $transcript = $this->processWithWhisper($uploadDir.'/'.$newFilename);
                $jsonData = json_encode(['status' => 'success', 'transcript' => $transcript], JSON_UNESCAPED_UNICODE);
                return new JsonResponse(
                    $jsonData,
                    Response::HTTP_OK,
                    ['Content-Type' => 'application/json; charset=UTF-8'],
                    true
                );
            } catch (FileException $e) {
                return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }

        return new JsonResponse(['status' => 'error', 'message' => 'Ошибка загрузки файла'], 400);
    }

    private function processWithWhisper(string $filePath): string
    {
        $outputDir = dirname($filePath);

        $command = escapeshellcmd("whisper " . escapeshellarg($filePath) . " --model small --output_dir " . escapeshellarg($outputDir));
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            return "Command execution error: ".implode("\n", $output);
        }

        $transcriptFile = $outputDir.'/'.pathinfo($filePath, PATHINFO_FILENAME).'.txt';

        if (file_exists($transcriptFile)) {
            return file_get_contents($transcriptFile);
        }

        return "Error processing file.";
    }
}
