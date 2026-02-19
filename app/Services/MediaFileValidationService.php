<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;

class MediaFileValidationService
{
    private $lang;

    // 許可されたファイル形式
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const ALLOWED_VIDEO_EXTENSIONS = ['mp4', 'webm'];
    private const ALLOWED_AUDIO_EXTENSIONS = ['mp3', 'm4a', 'webm'];
    
    // 最大ファイルサイズ（バイト）
    private const MAX_IMAGE_SIZE = 1.5 * 1024 * 1024; // 1.5MB
    private const MAX_VIDEO_SIZE = 10 * 1024 * 1024; // 10MB
    private const MAX_AUDIO_SIZE = 5 * 1024 * 1024; // 5MB
    
    // MIMEタイプマッピング
    private const MIME_TYPE_MAP = [
        'image' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ],
        'video' => [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
        ],
        'audio' => [
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/webm' => 'webm',
        ],
    ];

    public function __construct($lang = null)
    {
        $this->lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
        // 言語コードを正規化（'JA'/'EN' -> 'ja'/'en'）
        $this->lang = strtolower($this->lang);
        if ($this->lang !== 'ja' && $this->lang !== 'en') {
            $this->lang = 'ja'; // デフォルト
        }
    }

    /**
     * 開発環境でツールチェックをスキップするかどうか
     */
    private function shouldSkipToolCheck(): bool
    {
        // 環境変数でスキップ可能（開発環境でのテスト用）
        return env('SKIP_MEDIA_VALIDATION_TOOLS', false) === true;
    }

    /**
     * 日本語かどうかを判定
     */
    private function isJapanese(): bool
    {
        return $this->lang === 'ja';
    }

    /**
     * ファイルを検証する
     *
     * @param UploadedFile $file
     * @return array ['valid' => bool, 'error' => string|null, 'media_type' => string|null]
     */
    public function validateFile(UploadedFile $file): array
    {
        try {
            Log::info('MediaFileValidationService: Starting file validation', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
            ]);

            // 1. 拡張子とMIMEタイプからメディアタイプを判定
            $extension = strtolower($file->getClientOriginalExtension());
            $mimeType = strtolower($file->getMimeType());
            
            // MIMEタイプからも判定を試みる（拡張子だけでは不十分な場合がある）
            $mediaType = $this->detectMediaType($extension, $mimeType);
            
            if (!$mediaType) {
                $errorMsg = $this->isJapanese()
                    ? '許可されていないファイル形式です。画像：JPG/PNG/WebP（最大1.5MB）、動画：MP4/WebM（最大10MB）、音声：MP3/M4A/WebM（最大5MB）のみ対応しています。'
                    : 'File format not allowed. Only images (JPG/PNG/WebP, max 1.5MB), videos (MP4/WebM, max 10MB), and audio (MP3/M4A/WebM, max 5MB) are supported.';
                return [
                    'valid' => false,
                    'error' => $errorMsg,
                    'media_type' => null,
                ];
            }

            // 2. ファイルサイズチェック
            $maxSize = $this->getMaxSizeForMediaType($mediaType);
            if ($file->getSize() > $maxSize) {
                $maxSizeMB = round($maxSize / 1024 / 1024, 1);
                $fileSizeMB = round($file->getSize() / 1024 / 1024, 2);
                
                // ファイルタイプ名を取得
                $fileTypeName = '';
                if ($mediaType === 'image') {
                    $fileTypeName = $this->isJapanese() ? '画像' : 'Image';
                } elseif ($mediaType === 'video') {
                    $fileTypeName = $this->isJapanese() ? '動画' : 'Video';
                } elseif ($mediaType === 'audio') {
                    $fileTypeName = $this->isJapanese() ? '音声' : 'Audio';
                }
                
                if ($this->isJapanese()) {
                    $errorMsg = "{$fileTypeName}ファイルのサイズが大きすぎます。選択されたファイル：{$fileSizeMB}MB、最大サイズ：{$maxSizeMB}MB";
                } else {
                    $errorMsg = "{$fileTypeName} file size is too large. Selected file: {$fileSizeMB}MB, Maximum size: {$maxSizeMB}MB";
                }
                return [
                    'valid' => false,
                    'error' => $errorMsg,
                    'media_type' => null,
                ];
            }

            // 3. ffprobeでファイル形式とサイズを確認
            $ffprobeResult = $this->validateWithFfprobe($file, $mediaType, $extension);
            if (!$ffprobeResult['valid']) {
                return [
                    'valid' => false,
                    'error' => $ffprobeResult['error'],
                    'media_type' => null,
                ];
            }

            // 4. ClamAVでウイルススキャン
            $clamavResult = $this->scanWithClamAV($file);
            if (!$clamavResult['valid']) {
                return [
                    'valid' => false,
                    'error' => $clamavResult['error'],
                    'media_type' => null,
                ];
            }

            Log::info('MediaFileValidationService: File validation successful', [
                'filename' => $file->getClientOriginalName(),
                'media_type' => $mediaType,
            ]);

            return [
                'valid' => true,
                'error' => null,
                'media_type' => $mediaType,
            ];

        } catch (\Exception $e) {
            Log::error('MediaFileValidationService: Exception during validation', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMsg = $this->isJapanese() 
                ? 'ファイル検証中にエラーが発生しました。'
                : 'An error occurred during file validation.';
            
            return [
                'valid' => false,
                'error' => $errorMsg,
                'media_type' => null,
            ];
        }
    }

    /**
     * MIMEタイプが許可されているかチェック
     *
     * @param string $mimeType
     * @return bool
     */
    private function isValidMimeType(string $mimeType): bool
    {
        $allowedMimeTypes = [
            // 画像
            'image/jpeg',
            'image/png',
            'image/webp',
            // 動画
            'video/mp4',
            'video/webm',
            // 音声
            'audio/mpeg',
            'audio/mp4',
            'audio/webm',
            'audio/webm;codecs=opus',
        ];
        
        return in_array($mimeType, $allowedMimeTypes);
    }

    /**
     * 拡張子とMIMEタイプからメディアタイプを判定
     *
     * @param string $extension
     * @param string|null $mimeType
     * @return string|null 'image', 'video', 'audio', or null
     */
    private function detectMediaType(string $extension, ?string $mimeType = null): ?string
    {
        // MIMEタイプから判定を試みる（より正確）
        if ($mimeType) {
            foreach (self::MIME_TYPE_MAP as $type => $mimeMap) {
                if (isset($mimeMap[$mimeType])) {
                    // MIMEタイプが一致し、拡張子も許可されているか確認
                    $expectedExtension = $mimeMap[$mimeType];
                    if ($extension === $expectedExtension || 
                        ($type === 'audio' && in_array($extension, self::ALLOWED_AUDIO_EXTENSIONS)) ||
                        ($type === 'video' && in_array($extension, self::ALLOWED_VIDEO_EXTENSIONS)) ||
                        ($type === 'image' && in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS))) {
                        return $type;
                    }
                }
            }
        }
        
        // MIMEタイプで判定できない場合は拡張子から判定
        if (in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS)) {
            return 'image';
        }
        if (in_array($extension, self::ALLOWED_VIDEO_EXTENSIONS)) {
            return 'video';
        }
        if (in_array($extension, self::ALLOWED_AUDIO_EXTENSIONS)) {
            return 'audio';
        }
        return null;
    }

    /**
     * メディアタイプに応じた最大サイズを取得
     *
     * @param string $mediaType
     * @return int
     */
    private function getMaxSizeForMediaType(string $mediaType): int
    {
        return match($mediaType) {
            'image' => self::MAX_IMAGE_SIZE,
            'video' => self::MAX_VIDEO_SIZE,
            'audio' => self::MAX_AUDIO_SIZE,
            default => 0,
        };
    }

    /**
     * ffprobeでファイルを検証
     *
     * @param UploadedFile $file
     * @param string $mediaType
     * @param string $extension
     * @return array ['valid' => bool, 'error' => string|null]
     */
    private function validateWithFfprobe(UploadedFile $file, string $mediaType, string $extension): array
    {
        try {
            $filePath = $file->getRealPath();
            
            // ffprobeコマンドを実行
            $process = new Process([
                'ffprobe',
                '-v', 'error',
                '-show_entries', 'format=format_name,size',
                '-of', 'json',
                $filePath,
            ]);
            
            $process->setTimeout(30);
            $process->run();

            if (!$process->isSuccessful()) {
                // ffprobeが利用できない場合や、ファイルが破損している場合
                $errorOutput = $process->getErrorOutput();
                Log::warning('MediaFileValidationService: ffprobe failed', [
                    'error' => $errorOutput,
                    'exit_code' => $process->getExitCode(),
                    'media_type' => $mediaType,
                ]);
                
                // ffprobeがインストールされていない場合
                if ($process->getExitCode() === 127) {
                    // 開発環境でスキップ可能な場合
                    if ($this->shouldSkipToolCheck()) {
                        Log::warning('MediaFileValidationService: ffprobe not found, skipping validation (development mode)');
                        return [
                            'valid' => true,
                            'error' => null,
                        ];
                    }
                    
                    $errorMsg = \App\Services\LanguageService::trans('file_validation_tool_unavailable', $this->lang);
                    return [
                        'valid' => false,
                        'error' => $errorMsg,
                    ];
                }
                
                $errorMsg = $this->isJapanese()
                    ? 'ファイル形式の確認に失敗しました。ファイルが破損している可能性があります。'
                    : 'Failed to verify file format. The file may be corrupted.';
                return [
                    'valid' => false,
                    'error' => $errorMsg,
                ];
            }

            $output = json_decode($process->getOutput(), true);
            
            if (!isset($output['format'])) {
                $errorMsg = $this->isJapanese()
                    ? 'ファイル形式を確認できませんでした。'
                    : 'Could not verify file format.';
                return [
                    'valid' => false,
                    'error' => $errorMsg,
                ];
            }

            $format = $output['format'];
            $formatName = strtolower($format['format_name'] ?? '');
            $fileSize = (int)($format['size'] ?? 0);

            // ファイルサイズの確認
            $maxSize = $this->getMaxSizeForMediaType($mediaType);
            if ($fileSize > $maxSize) {
                $maxSizeMB = round($maxSize / 1024 / 1024, 1);
                $errorMsg = $this->isJapanese()
                    ? "ファイルサイズが大きすぎます。最大{$maxSizeMB}MBまでです。"
                    : "File size is too large. Maximum {$maxSizeMB}MB allowed.";
                return [
                    'valid' => false,
                    'error' => $errorMsg,
                ];
            }

            // 拡張子と内部データの一致確認
            $expectedFormats = $this->getExpectedFormats($mediaType, $extension);
            $formatMatches = false;
            
            Log::info('MediaFileValidationService: Format validation', [
                'extension' => $extension,
                'media_type' => $mediaType,
                'detected_format' => $formatName,
                'expected_formats' => $expectedFormats,
            ]);
            
            foreach ($expectedFormats as $expectedFormat) {
                if (strpos($formatName, $expectedFormat) !== false) {
                    $formatMatches = true;
                    break;
                }
            }

            if (!$formatMatches) {
                Log::warning('MediaFileValidationService: Format mismatch', [
                    'extension' => $extension,
                    'detected_format' => $formatName,
                    'media_type' => $mediaType,
                    'expected_formats' => $expectedFormats,
                ]);
                
                // 音声ファイルの場合、より柔軟な判定を行う
                if ($mediaType === 'audio') {
                    // 音声ファイルの場合、フォーマット名に音声関連のキーワードが含まれていれば許可
                    $audioKeywords = ['audio', 'mp3', 'mpeg', 'aac', 'm4a', 'opus', 'vorbis'];
                    $hasAudioKeyword = false;
                    foreach ($audioKeywords as $keyword) {
                        if (stripos($formatName, $keyword) !== false) {
                            $hasAudioKeyword = true;
                            break;
                        }
                    }
                    
                    if ($hasAudioKeyword) {
                        Log::info('MediaFileValidationService: Audio format accepted (flexible validation)', [
                            'format' => $formatName,
                        ]);
                        $formatMatches = true;
                    }
                }
                
                if (!$formatMatches) {
                    $errorMsg = $this->isJapanese()
                        ? 'ファイルの拡張子と内部データが一致しません。'
                        : 'File extension does not match the internal data.';
                    return [
                        'valid' => false,
                        'error' => $errorMsg,
                    ];
                }
            }

            return [
                'valid' => true,
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::error('MediaFileValidationService: Exception in ffprobe validation', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMsg = $this->isJapanese()
                ? 'ファイル形式の確認中にエラーが発生しました。'
                : 'An error occurred while verifying file format.';
            return [
                'valid' => false,
                'error' => $errorMsg,
            ];
        }
    }

    /**
     * メディアタイプと拡張子から期待されるフォーマット名を取得
     *
     * @param string $mediaType
     * @param string $extension
     * @return array
     */
    private function getExpectedFormats(string $mediaType, string $extension): array
    {
        $formatMap = [
            'image' => [
                'jpg' => ['mjpeg', 'jpeg'],
                'jpeg' => ['mjpeg', 'jpeg'],
                'png' => ['png'],
                'webp' => ['webp'],
            ],
            'video' => [
                'mp4' => ['mp4', 'mov', 'm4a'],
                'webm' => ['webm', 'matroska'],
            ],
            'audio' => [
                'mp3' => ['mp3', 'mpeg'],
                'm4a' => ['mp4', 'm4a', 'aac'],
                'webm' => ['webm', 'matroska'],
            ],
        ];

        return $formatMap[$mediaType][$extension] ?? [];
    }

    /**
     * ClamAVでウイルススキャン
     *
     * @param UploadedFile $file
     * @return array ['valid' => bool, 'error' => string|null]
     */
    private function scanWithClamAV(UploadedFile $file): array
    {
        try {
            $filePath = $file->getRealPath();
            $useClamscan = false;
            
            // まずclamdscanを試す（clamdデーモンを使用、高速）
            $process = new Process([
                'clamdscan',
                '--no-summary',
                $filePath,
            ]);
            
            $process->setTimeout(60);
            $process->run();

            // clamdscanが失敗した場合（デーモンに接続できない、コマンドが見つからないなど）
            // clamscanにフォールバック
            if (!$process->isSuccessful()) {
                $exitCode = $process->getExitCode();
                $errorOutput = $process->getErrorOutput();
                
                // デーモンエラーの詳細をログに記録
                $isDaemonError = (strpos($errorOutput, 'Connection refused') !== false || 
                                 strpos($errorOutput, 'Can\'t connect') !== false ||
                                 strpos($errorOutput, 'ERROR: Can\'t connect') !== false);
                
                Log::info('MediaFileValidationService: clamdscan failed, trying clamscan', [
                    'exit_code' => $exitCode,
                    'error' => $errorOutput,
                    'filename' => $file->getClientOriginalName(),
                    'is_daemon_error' => $isDaemonError,
                ]);
                
                // exit code 127（コマンドが見つからない）または2（デーモンエラー）の場合、clamscanを試す
                // デーモンエラーの場合もclamscanにフォールバック
                if ($exitCode === 127 || $exitCode === 2 || $isDaemonError) {
                    $useClamscan = true;
                    $process = new Process([
                        'clamscan',
                        '--no-summary',
                        '--quiet',
                        $filePath,
                    ]);
                    
                    $process->setTimeout(60);
                    $process->run();
                } else {
                    // その他のエラー（exit code 1 = ウイルス検出など）はそのまま処理
                }
            }

            // 終了コードを確認
            // 0: 問題なし
            // 1: ウイルス検出
            // 2: エラー
            $exitCode = $process->getExitCode();
            
            if ($exitCode === 1) {
                $output = $process->getOutput() . $process->getErrorOutput();
                Log::warning('MediaFileValidationService: Virus detected', [
                    'filename' => $file->getClientOriginalName(),
                    'output' => $output,
                    'scanner' => $useClamscan ? 'clamscan' : 'clamdscan',
                ]);
                $errorMsg = $this->isJapanese()
                    ? 'ウイルスが検出されました。ファイルを送信できません。'
                    : 'Virus detected. File cannot be uploaded.';
                return [
                    'valid' => false,
                    'error' => $errorMsg,
                ];
            }

            if ($exitCode === 2) {
                $errorOutput = $process->getErrorOutput();
                Log::warning('MediaFileValidationService: ClamAV error', [
                    'error' => $errorOutput,
                    'scanner' => $useClamscan ? 'clamscan' : 'clamdscan',
                    'filename' => $file->getClientOriginalName(),
                ]);
                
                // 開発環境でスキップ可能な場合
                if ($this->shouldSkipToolCheck()) {
                    Log::warning('MediaFileValidationService: ClamAV error, skipping scan (development mode)');
                    return [
                        'valid' => true,
                        'error' => null,
                    ];
                }
                
                // ClamAVのエラーが発生した場合は拒否
                $errorMsg = $this->isJapanese()
                    ? 'ウイルススキャン中にエラーが発生しました。ファイルを送信できません。'
                    : 'An error occurred during virus scan. File cannot be uploaded.';
                return [
                    'valid' => false,
                    'error' => $errorMsg,
                ];
            }

            if ($exitCode === 127) {
                // ClamAVがインストールされていない場合
                Log::error('MediaFileValidationService: ClamAV not found', [
                    'filename' => $file->getClientOriginalName(),
                ]);
                
                // 開発環境でスキップ可能な場合
                if ($this->shouldSkipToolCheck()) {
                    Log::warning('MediaFileValidationService: ClamAV not found, skipping scan (development mode)');
                    return [
                        'valid' => true,
                        'error' => null,
                    ];
                }
                
                $errorMsg = $this->isJapanese()
                    ? 'ウイルススキャン機能が利用できません。'
                    : 'Virus scan feature is not available.';
                return [
                    'valid' => false,
                    'error' => $errorMsg,
                ];
            }

            // 成功（exit code 0）
            Log::info('MediaFileValidationService: ClamAV scan successful', [
                'filename' => $file->getClientOriginalName(),
                'scanner' => $useClamscan ? 'clamscan' : 'clamdscan',
            ]);
            
            return [
                'valid' => true,
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::error('MediaFileValidationService: Exception in ClamAV scan', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 例外が発生した場合は拒否
            $errorMsg = $this->isJapanese()
                ? 'ウイルススキャン中にエラーが発生しました。ファイルを送信できません。'
                : 'An error occurred during virus scan. File cannot be uploaded.';
            return [
                'valid' => false,
                'error' => $errorMsg,
            ];
        }
    }
}

