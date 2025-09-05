<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class FileStorageService
{
    /**
     * Tipos de arquivo permitidos por categoria
     */
    private const ALLOWED_TYPES = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'document' => ['pdf', 'doc', 'docx', 'txt'],
        'avatar' => ['jpg', 'jpeg', 'png', 'webp']
    ];

    /**
     * Tamanhos máximos por categoria (em bytes)
     */
    private const MAX_SIZES = [
        'image' => 5 * 1024 * 1024, // 5MB
        'document' => 10 * 1024 * 1024, // 10MB
        'avatar' => 2 * 1024 * 1024 // 2MB
    ];

    /**
     * Faz upload de um arquivo para o Supabase Storage
     *
     * @param UploadedFile $file
     * @param string $category
     * @param string|null $folder
     * @param string|null $customName
     * @return array
     * @throws Exception
     */
    public function uploadFile(
        UploadedFile $file,
        string $category = 'image',
        ?string $folder = null,
        ?string $customName = null
    ): array {
        // Validar categoria
        if (!array_key_exists($category, self::ALLOWED_TYPES)) {
            throw new Exception("Categoria '{$category}' não é válida.");
        }

        // Validar tipo de arquivo
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_TYPES[$category])) {
            throw new Exception(
                "Tipo de arquivo '{$extension}' não é permitido para a categoria '{$category}'."
            );
        }

        // Validar tamanho
        if ($file->getSize() > self::MAX_SIZES[$category]) {
            $maxSizeMB = self::MAX_SIZES[$category] / (1024 * 1024);
            throw new Exception(
                "Arquivo muito grande. Tamanho máximo permitido: {$maxSizeMB}MB."
            );
        }

        // Gerar nome único para o arquivo
        $fileName = $customName ?: $this->generateUniqueFileName($file);
        
        // Construir caminho do arquivo
        $path = $this->buildFilePath($category, $folder, $fileName);

        try {
            // Fazer upload para o Supabase Storage
            $uploaded = Storage::disk('supabase')->put($path, file_get_contents($file->getRealPath()));
            
            if (!$uploaded) {
                throw new Exception('Falha ao fazer upload do arquivo.');
            }

            // Gerar URL pública
            $publicUrl = $this->getPublicUrl($path);

            return [
                'success' => true,
                'path' => $path,
                'url' => $publicUrl,
                'filename' => $fileName,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'category' => $category
            ];
        } catch (Exception $e) {
            throw new Exception('Erro no upload: ' . $e->getMessage());
        }
    }

    /**
     * Faz upload de múltiplos arquivos
     *
     * @param array $files
     * @param string $category
     * @param string|null $folder
     * @return array
     */
    public function uploadMultipleFiles(
        array $files,
        string $category = 'image',
        ?string $folder = null
    ): array {
        $results = [];
        $errors = [];

        foreach ($files as $index => $file) {
            try {
                $result = $this->uploadFile($file, $category, $folder);
                $results[] = $result;
            } catch (Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success' => empty($errors),
            'uploaded' => $results,
            'errors' => $errors,
            'total_files' => count($files),
            'uploaded_count' => count($results),
            'error_count' => count($errors)
        ];
    }

    /**
     * Faz upload de imagem com redimensionamento
     *
     * @param UploadedFile $file
     * @param string|null $folder
     * @param array $sizes
     * @return array
     */
    public function uploadImageWithSizes(
        UploadedFile $file,
        ?string $folder = null,
        array $sizes = ['original', 'thumbnail']
    ): array {
        $results = [];
        
        foreach ($sizes as $size) {
            $processedFile = $this->processImageSize($file, $size);
            $customName = $this->generateSizedFileName($file, $size);
            
            $result = $this->uploadFile($processedFile, 'image', $folder, $customName);
            $results[$size] = $result;
        }

        return $results;
    }

    /**
     * Deleta um arquivo do storage
     *
     * @param string $path
     * @return bool
     */
    public function deleteFile(string $path): bool
    {
        try {
            return Storage::disk('supabase')->delete($path);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Deleta múltiplos arquivos
     *
     * @param array $paths
     * @return array
     */
    public function deleteMultipleFiles(array $paths): array
    {
        $deleted = [];
        $errors = [];

        foreach ($paths as $path) {
            if ($this->deleteFile($path)) {
                $deleted[] = $path;
            } else {
                $errors[] = $path;
            }
        }

        return [
            'success' => empty($errors),
            'deleted' => $deleted,
            'errors' => $errors,
            'total_files' => count($paths),
            'deleted_count' => count($deleted),
            'error_count' => count($errors)
        ];
    }

    /**
     * Verifica se um arquivo existe
     *
     * @param string $path
     * @return bool
     */
    public function fileExists(string $path): bool
    {
        try {
            return Storage::disk('supabase')->exists($path);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obtém informações de um arquivo
     *
     * @param string $path
     * @return array|null
     */
    public function getFileInfo(string $path): ?array
    {
        try {
            if (!$this->fileExists($path)) {
                return null;
            }

            $size = Storage::disk('supabase')->size($path);
            $lastModified = Storage::disk('supabase')->lastModified($path);
            $url = $this->getPublicUrl($path);

            return [
                'path' => $path,
                'url' => $url,
                'size' => $size,
                'last_modified' => date('Y-m-d H:i:s', $lastModified),
                'exists' => true
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Gera um nome único para o arquivo
     *
     * @param UploadedFile $file
     * @return string
     */
    private function generateUniqueFileName(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('YmdHis');
        $random = Str::random(8);
        
        return "{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Constrói o caminho completo do arquivo
     *
     * @param string $category
     * @param string|null $folder
     * @param string $fileName
     * @return string
     */
    private function buildFilePath(string $category, ?string $folder, string $fileName): string
    {
        $basePath = $category;
        
        if ($folder) {
            $basePath .= '/' . trim($folder, '/');
        }
        
        return $basePath . '/' . $fileName;
    }

    /**
     * Gera URL pública para o arquivo
     *
     * @param string $path
     * @return string
     */
    private function getPublicUrl(string $path): string
    {
        // Para Supabase Storage, a URL pública segue o padrão:
        // https://[project-ref].supabase.co/storage/v1/object/public/[bucket]/[path]
        $supabaseUrl = config('filesystems.disks.supabase.url');
        $bucket = config('filesystems.disks.supabase.bucket');
        
        return "{$supabaseUrl}/storage/v1/object/public/{$bucket}/{$path}";
    }

    /**
     * Redimensiona uma imagem (se necessário)
     *
     * @param UploadedFile $file
     * @param int $maxWidth
     * @param int $maxHeight
     * @return UploadedFile
     */
    public function resizeImage(
        UploadedFile $file,
        int $maxWidth = 1920,
        int $maxHeight = 1080
    ): UploadedFile {
        // Esta funcionalidade pode ser implementada usando a biblioteca Intervention Image
        // Por enquanto, retorna o arquivo original
        return $file;
    }

    /**
     * Processa imagem para diferentes tamanhos
     *
     * @param UploadedFile $file
     * @param string $size
     * @return UploadedFile
     */
    private function processImageSize(UploadedFile $file, string $size): UploadedFile
    {
        switch ($size) {
            case 'thumbnail':
                return $this->resizeImage($file, 300, 300);
            case 'medium':
                return $this->resizeImage($file, 800, 600);
            case 'large':
                return $this->resizeImage($file, 1920, 1080);
            default:
                return $file;
        }
    }

    /**
     * Gera nome de arquivo com sufixo de tamanho
     *
     * @param UploadedFile $file
     * @param string $size
     * @return string
     */
    private function generateSizedFileName(UploadedFile $file, string $size): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('YmdHis');
        $random = Str::random(8);
        
        if ($size === 'original') {
            return "{$timestamp}_{$random}.{$extension}";
        }
        
        return "{$timestamp}_{$random}_{$size}.{$extension}";
    }

    /**
     * Obtém tipos de arquivo permitidos para uma categoria
     *
     * @param string $category
     * @return array
     */
    public static function getAllowedTypes(string $category): array
    {
        return self::ALLOWED_TYPES[$category] ?? [];
    }

    /**
     * Obtém tamanho máximo permitido para uma categoria
     *
     * @param string $category
     * @return int
     */
    public static function getMaxSize(string $category): int
    {
        return self::MAX_SIZES[$category] ?? 0;
    }

    /**
     * Obtém todas as categorias disponíveis
     *
     * @return array
     */
    public static function getAvailableCategories(): array
    {
        return array_keys(self::ALLOWED_TYPES);
    }
}