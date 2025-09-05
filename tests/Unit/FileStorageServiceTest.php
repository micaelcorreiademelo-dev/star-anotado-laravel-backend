<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;

class FileStorageServiceTest extends TestCase
{
    protected FileStorageService $fileStorageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileStorageService = new FileStorageService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_available_categories()
    {
        $categories = FileStorageService::getAvailableCategories();
        
        $this->assertIsArray($categories);
        $this->assertContains('image', $categories);
        $this->assertContains('document', $categories);
        $this->assertContains('avatar', $categories);
    }

    /** @test */
    public function it_can_get_allowed_types_for_category()
    {
        $imageTypes = FileStorageService::getAllowedTypes('image');
        $documentTypes = FileStorageService::getAllowedTypes('document');
        $avatarTypes = FileStorageService::getAllowedTypes('avatar');
        
        $this->assertIsArray($imageTypes);
        $this->assertContains('image/jpeg', $imageTypes);
        $this->assertContains('image/png', $imageTypes);
        
        $this->assertIsArray($documentTypes);
        $this->assertContains('application/pdf', $documentTypes);
        
        $this->assertIsArray($avatarTypes);
        $this->assertContains('image/jpeg', $avatarTypes);
    }

    /** @test */
    public function it_can_get_max_size_for_category()
    {
        $imageMaxSize = FileStorageService::getMaxSize('image');
        $documentMaxSize = FileStorageService::getMaxSize('document');
        $avatarMaxSize = FileStorageService::getMaxSize('avatar');
        
        $this->assertIsInt($imageMaxSize);
        $this->assertEquals(5 * 1024 * 1024, $imageMaxSize); // 5MB
        
        $this->assertIsInt($documentMaxSize);
        $this->assertEquals(10 * 1024 * 1024, $documentMaxSize); // 10MB
        
        $this->assertIsInt($avatarMaxSize);
        $this->assertEquals(2 * 1024 * 1024, $avatarMaxSize); // 2MB
    }

    /** @test */
    public function it_validates_file_category()
    {
        $this->assertTrue($this->fileStorageService->isValidCategory('image'));
        $this->assertTrue($this->fileStorageService->isValidCategory('document'));
        $this->assertTrue($this->fileStorageService->isValidCategory('avatar'));
        $this->assertFalse($this->fileStorageService->isValidCategory('invalid'));
    }

    /** @test */
    public function it_validates_file_type_for_category()
    {
        // Teste para categoria image
        $this->assertTrue($this->fileStorageService->isValidFileType('image/jpeg', 'image'));
        $this->assertTrue($this->fileStorageService->isValidFileType('image/png', 'image'));
        $this->assertFalse($this->fileStorageService->isValidFileType('application/pdf', 'image'));
        
        // Teste para categoria document
        $this->assertTrue($this->fileStorageService->isValidFileType('application/pdf', 'document'));
        $this->assertFalse($this->fileStorageService->isValidFileType('image/jpeg', 'document'));
        
        // Teste para categoria avatar
        $this->assertTrue($this->fileStorageService->isValidFileType('image/jpeg', 'avatar'));
        $this->assertFalse($this->fileStorageService->isValidFileType('image/gif', 'avatar'));
    }

    /** @test */
    public function it_validates_file_size_for_category()
    {
        // Teste para categoria image (5MB max)
        $this->assertTrue($this->fileStorageService->isValidFileSize(1024 * 1024, 'image')); // 1MB
        $this->assertTrue($this->fileStorageService->isValidFileSize(5 * 1024 * 1024, 'image')); // 5MB
        $this->assertFalse($this->fileStorageService->isValidFileSize(6 * 1024 * 1024, 'image')); // 6MB
        
        // Teste para categoria document (10MB max)
        $this->assertTrue($this->fileStorageService->isValidFileSize(8 * 1024 * 1024, 'document')); // 8MB
        $this->assertFalse($this->fileStorageService->isValidFileSize(12 * 1024 * 1024, 'document')); // 12MB
        
        // Teste para categoria avatar (2MB max)
        $this->assertTrue($this->fileStorageService->isValidFileSize(1024 * 1024, 'avatar')); // 1MB
        $this->assertFalse($this->fileStorageService->isValidFileSize(3 * 1024 * 1024, 'avatar')); // 3MB
    }

    /** @test */
    public function it_generates_unique_filename()
    {
        $filename1 = $this->fileStorageService->generateUniqueFilename('test.jpg');
        $filename2 = $this->fileStorageService->generateUniqueFilename('test.jpg');
        
        $this->assertNotEquals($filename1, $filename2);
        $this->assertStringEndsWith('.jpg', $filename1);
        $this->assertStringEndsWith('.jpg', $filename2);
        $this->assertMatchesRegularExpression('/^\d{14}_[a-f0-9]{8}\.jpg$/', $filename1);
    }

    /** @test */
    public function it_generates_file_path()
    {
        $path = $this->fileStorageService->generateFilePath('image', 'products', 'test.jpg');
        
        $this->assertEquals('image/products/test.jpg', $path);
        
        $pathWithoutFolder = $this->fileStorageService->generateFilePath('document', null, 'doc.pdf');
        
        $this->assertEquals('document/doc.pdf', $pathWithoutFolder);
    }

    /** @test */
    public function it_generates_public_url()
    {
        $url = $this->fileStorageService->getPublicUrl('image/test.jpg');
        
        $this->assertStringContainsString('supabase', $url);
        $this->assertStringContainsString('storage/v1/object/public', $url);
        $this->assertStringEndsWith('image/test.jpg', $url);
    }

    /** @test */
    public function it_validates_file_before_upload()
    {
        // Criar um arquivo fake para teste
        $file = UploadedFile::fake()->image('test.jpg', 100, 100)->size(1024); // 1MB
        
        $validation = $this->fileStorageService->validateFile($file, 'image');
        
        $this->assertTrue($validation['valid']);
        $this->assertArrayNotHasKey('message', $validation);
    }

    /** @test */
    public function it_rejects_invalid_file_type()
    {
        // Criar um arquivo fake com tipo inválido
        $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');
        
        $validation = $this->fileStorageService->validateFile($file, 'image');
        
        $this->assertFalse($validation['valid']);
        $this->assertArrayHasKey('message', $validation);
        $this->assertStringContainsString('Tipo de arquivo não permitido', $validation['message']);
    }

    /** @test */
    public function it_rejects_oversized_file()
    {
        // Criar um arquivo fake muito grande
        $file = UploadedFile::fake()->image('test.jpg')->size(6 * 1024); // 6MB
        
        $validation = $this->fileStorageService->validateFile($file, 'image');
        
        $this->assertFalse($validation['valid']);
        $this->assertArrayHasKey('message', $validation);
        $this->assertStringContainsString('muito grande', $validation['message']);
    }

    /** @test */
    public function it_handles_multiple_files_validation()
    {
        $files = [
            UploadedFile::fake()->image('test1.jpg', 100, 100)->size(1024),
            UploadedFile::fake()->image('test2.png', 100, 100)->size(1024),
            UploadedFile::fake()->create('invalid.txt', 100, 'text/plain')
        ];
        
        $results = $this->fileStorageService->validateMultipleFiles($files, 'image');
        
        $this->assertCount(3, $results);
        $this->assertTrue($results[0]['valid']);
        $this->assertTrue($results[1]['valid']);
        $this->assertFalse($results[2]['valid']);
    }

    /** @test */
    public function it_sanitizes_filename()
    {
        $sanitized = $this->fileStorageService->sanitizeFilename('test file with spaces & special chars!.jpg');
        
        $this->assertEquals('test_file_with_spaces_special_chars.jpg', $sanitized);
        
        $sanitizedUnicode = $this->fileStorageService->sanitizeFilename('arquivo_com_acentos_ção.pdf');
        
        $this->assertEquals('arquivo_com_acentos_cao.pdf', $sanitizedUnicode);
    }

    /** @test */
    public function it_gets_file_extension()
    {
        $this->assertEquals('jpg', $this->fileStorageService->getFileExtension('test.jpg'));
        $this->assertEquals('pdf', $this->fileStorageService->getFileExtension('document.PDF'));
        $this->assertEquals('', $this->fileStorageService->getFileExtension('noextension'));
    }

    /** @test */
    public function it_checks_if_file_exists()
    {
        // Mock do Storage
        Storage::shouldReceive('disk')
            ->with('supabase')
            ->andReturnSelf();
            
        Storage::shouldReceive('exists')
            ->with('image/test.jpg')
            ->andReturn(true);
            
        Storage::shouldReceive('exists')
            ->with('image/nonexistent.jpg')
            ->andReturn(false);
        
        $this->assertTrue($this->fileStorageService->fileExists('image/test.jpg'));
        $this->assertFalse($this->fileStorageService->fileExists('image/nonexistent.jpg'));
    }
}