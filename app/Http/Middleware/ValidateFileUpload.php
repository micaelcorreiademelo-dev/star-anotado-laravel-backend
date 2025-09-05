<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ValidateFileUpload
{
    /**
     * Tipos de arquivo permitidos por categoria
     */
    private const ALLOWED_TYPES = [
        'image' => [
            'jpeg', 'jpg', 'png', 'gif', 'webp', 'svg'
        ],
        'document' => [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'
        ],
        'audio' => [
            'mp3', 'wav', 'ogg', 'm4a'
        ],
        'video' => [
            'mp4', 'avi', 'mov', 'wmv', 'flv'
        ]
    ];

    /**
     * Tamanhos máximos por tipo (em bytes)
     */
    private const MAX_SIZES = [
        'image' => 5 * 1024 * 1024,      // 5MB
        'document' => 10 * 1024 * 1024,  // 10MB
        'audio' => 20 * 1024 * 1024,     // 20MB
        'video' => 100 * 1024 * 1024,    // 100MB
    ];

    /**
     * Extensões perigosas que devem ser bloqueadas
     */
    private const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar', 'sh'
    ];

    /**
     * MIME types suspeitos
     */
    private const SUSPICIOUS_MIME_TYPES = [
        'application/x-php',
        'application/x-httpd-php',
        'text/x-php',
        'application/x-executable',
        'application/x-msdownload'
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar se há arquivos na requisição
        if (!$request->hasFile('file') && !$request->hasFile('files')) {
            return $next($request);
        }

        $files = [];
        
        // Coletar todos os arquivos
        if ($request->hasFile('file')) {
            $files[] = $request->file('file');
        }
        
        if ($request->hasFile('files')) {
            $uploadedFiles = $request->file('files');
            if (is_array($uploadedFiles)) {
                $files = array_merge($files, $uploadedFiles);
            } else {
                $files[] = $uploadedFiles;
            }
        }

        // Validar cada arquivo
        foreach ($files as $file) {
            $validation = $this->validateFile($file, $request);
            
            if (!$validation['valid']) {
                Log::warning('Upload de arquivo rejeitado', [
                    'reason' => $validation['reason'],
                    'filename' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->header('User-Agent')
                ]);
                
                return response()->json([
                    'error' => $validation['reason'],
                    'file' => $file->getClientOriginalName()
                ], 400);
            }
        }

        // Log de upload válido
        Log::info('Upload de arquivo(s) validado com sucesso', [
            'files_count' => count($files),
            'files' => array_map(function($file) {
                return [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'type' => $file->getMimeType()
                ];
            }, $files),
            'ip' => $request->ip()
        ]);

        return $next($request);
    }

    /**
     * Validar um arquivo individual
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    private function validateFile($file, Request $request): array
    {
        // Verificar se o arquivo foi enviado corretamente
        if (!$file->isValid()) {
            return [
                'valid' => false,
                'reason' => 'Arquivo corrompido ou não foi enviado corretamente'
            ];
        }

        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Verificar extensões perigosas
        if (in_array($extension, self::DANGEROUS_EXTENSIONS)) {
            return [
                'valid' => false,
                'reason' => 'Tipo de arquivo não permitido por questões de segurança'
            ];
        }

        // Verificar MIME types suspeitos
        if (in_array($mimeType, self::SUSPICIOUS_MIME_TYPES)) {
            return [
                'valid' => false,
                'reason' => 'Tipo de arquivo suspeito detectado'
            ];
        }

        // Determinar categoria do arquivo
        $category = $this->determineFileCategory($extension, $mimeType);
        
        if (!$category) {
            return [
                'valid' => false,
                'reason' => 'Tipo de arquivo não suportado'
            ];
        }

        // Verificar tamanho do arquivo
        if ($size > self::MAX_SIZES[$category]) {
            $maxSizeMB = self::MAX_SIZES[$category] / (1024 * 1024);
            return [
                'valid' => false,
                'reason' => "Arquivo muito grande. Tamanho máximo permitido: {$maxSizeMB}MB"
            ];
        }

        // Verificações específicas por categoria
        switch ($category) {
            case 'image':
                return $this->validateImage($file);
            case 'document':
                return $this->validateDocument($file);
            default:
                break;
        }

        // Verificar nome do arquivo
        if (!$this->isValidFilename($originalName)) {
            return [
                'valid' => false,
                'reason' => 'Nome do arquivo contém caracteres inválidos'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Determinar categoria do arquivo
     *
     * @param string $extension
     * @param string $mimeType
     * @return string|null
     */
    private function determineFileCategory(string $extension, string $mimeType): ?string
    {
        foreach (self::ALLOWED_TYPES as $category => $extensions) {
            if (in_array($extension, $extensions)) {
                return $category;
            }
        }

        // Verificar por MIME type como fallback
        if (Str::startsWith($mimeType, 'image/')) {
            return 'image';
        }
        
        if (Str::startsWith($mimeType, 'audio/')) {
            return 'audio';
        }
        
        if (Str::startsWith($mimeType, 'video/')) {
            return 'video';
        }

        return null;
    }

    /**
     * Validar arquivo de imagem
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array
     */
    private function validateImage($file): array
    {
        // Verificar se é realmente uma imagem
        $imageInfo = @getimagesize($file->getPathname());
        
        if ($imageInfo === false) {
            return [
                'valid' => false,
                'reason' => 'Arquivo não é uma imagem válida'
            ];
        }

        // Verificar dimensões mínimas e máximas
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        if ($width < 10 || $height < 10) {
            return [
                'valid' => false,
                'reason' => 'Imagem muito pequena (mínimo 10x10 pixels)'
            ];
        }
        
        if ($width > 4000 || $height > 4000) {
            return [
                'valid' => false,
                'reason' => 'Imagem muito grande (máximo 4000x4000 pixels)'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validar documento
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array
     */
    private function validateDocument($file): array
    {
        // Verificações básicas de segurança para documentos
        $content = file_get_contents($file->getPathname());
        
        // Verificar se contém código suspeito
        $suspiciousPatterns = [
            '<?php',
            '<script',
            'javascript:',
            'vbscript:',
            'onload=',
            'onerror='
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                return [
                    'valid' => false,
                    'reason' => 'Documento contém código suspeito'
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Verificar se o nome do arquivo é válido
     *
     * @param string $filename
     * @return bool
     */
    private function isValidFilename(string $filename): bool
    {
        // Verificar caracteres perigosos
        $dangerousChars = ['..', '/', '\\', '<', '>', ':', '"', '|', '?', '*'];
        
        foreach ($dangerousChars as $char) {
            if (strpos($filename, $char) !== false) {
                return false;
            }
        }

        // Verificar se não é muito longo
        if (strlen($filename) > 255) {
            return false;
        }

        // Verificar se não está vazio
        if (empty(trim($filename))) {
            return false;
        }

        return true;
    }
}