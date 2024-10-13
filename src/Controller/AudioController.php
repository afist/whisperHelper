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
            // Генерация уникального имени для директории, в которую будет загружен файл
//            $uniqueFolder = uniqid('upload_', true);
            $uniqueFolder = 123;
            $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/'.$uniqueFolder;

            // Создаем папку для каждого загруженного файла
            if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
//                return new JsonResponse(
//                    ['status' => 'error', 'message' => 'Не удалось создать директорию для загрузки'], 500
//                );
            }

            // Генерация уникального имени файла
            $newFilename = uniqid('', true).'.'.$uploadedFile->guessExtension();

            try {
                // Перемещение файла в созданную директорию
                $uploadedFile->move($uploadDir, $newFilename);

                // Запуск процесса транскрипции с Whisper
                $transcript = $this->processWithWhisper($uploadDir.'/'.$newFilename);

                return new JsonResponse(
                    ['status' => 'success', 'transcript' => $transcript],
                    Response::HTTP_OK,
                    ['Content-Type' => 'application/json;charset=UTF-8']
                );
            } catch (FileException $e) {
                return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }

        return new JsonResponse(['status' => 'error', 'message' => 'Ошибка загрузки файла'], 400);
    }

    private function processWithWhisper(string $filePath): string
    {
        // Получаем директорию, где будет сохранен транскрипт
        $outputDir = dirname($filePath);

        // Формируем команду для вызова Whisper с нужными параметрами
        $command = escapeshellcmd("whisper ".escapeshellarg($filePath)." --model small");

        // Выполняем команду
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            // Возвращаем сообщение об ошибке, если команда не выполнилась успешно
            return "Ошибка выполнения команды: ".implode("\n", $output);
        }

        // Определяем путь к файлу транскрипта
        $transcriptFile = $outputDir.'/'.pathinfo($filePath, PATHINFO_FILENAME).'.txt';
//        $transcriptFile = "/var/www/html/public/uploads/123/670b6e96124d31.28850621.txt";

        // Проверяем, существует ли файл транскрипции, и возвращаем его содержимое
        if (file_exists($transcriptFile)) {
            return file_get_contents($transcriptFile);
        }

        // Возвращаем сообщение об ошибке, если транскрипт не найден
        return "Ошибка обработки файла.";
    }
}
