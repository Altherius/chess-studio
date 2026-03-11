<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OcrService
{
    private const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20 MB
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'tiff', 'tif'];
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/bmp', 'image/tiff'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $ocrApiUrl = 'http://ocr:5000',
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function validateImage(\Symfony\Component\HttpFoundation\File\UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException(
                'Format de fichier non supporté. Formats acceptés : ' . implode(', ', self::ALLOWED_EXTENSIONS)
            );
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Le fichier ne semble pas être une image valide.');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('L\'image est trop volumineuse (maximum 20 Mo).');
        }
    }

    /**
     * @throws \RuntimeException
     */
    public function recognizeMoves(\Symfony\Component\HttpFoundation\File\UploadedFile $file): string
    {
        $response = $this->httpClient->request('POST', $this->ocrApiUrl . '/predict', [
            'body' => [
                'image' => fopen($file->getPathname(), 'r'),
            ],
            'headers' => [
                'Content-Type' => 'multipart/form-data',
            ],
            'timeout' => 120,
        ]);

        $statusCode = $response->getStatusCode();
        $data = $response->toArray(false);

        if ($statusCode !== 200) {
            throw new \RuntimeException($data['error'] ?? 'Erreur lors de la reconnaissance des coups.');
        }

        return $data['moves'] ?? '';
    }
}
