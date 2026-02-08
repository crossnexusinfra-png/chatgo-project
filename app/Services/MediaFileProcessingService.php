<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class MediaFileProcessingService
{
    /**
     * 画像ファイルを再エンコード（Intervention Image使用）
     * S3対応: ファイルパスではなくストレージパスとディスク名を受け取る
     *
     * @param string $storagePath ストレージ内のファイルパス（例: 'response_media/xxx.jpg'）
     * @param string $mediaType メディアタイプ（'image'）
     * @param string $disk ディスク名（'public' など）
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function reencodeImage(string $storagePath, string $mediaType = 'image', string $disk = 'public'): array
    {
        $diskInstance = Storage::disk($disk);
        $tempFilePath = null;
        
        try {
            // ファイルの存在確認
            if (!$diskInstance->exists($storagePath)) {
                Log::error('MediaFileProcessingService: File not found', [
                    'storage_path' => $storagePath,
                    'disk' => $disk,
                ]);
                return [
                    'success' => false,
                    'error' => 'File not found.',
                ];
            }

            // Intervention Imageが利用可能か確認
            if (!class_exists(\Intervention\Image\Laravel\Facades\Image::class)) {
                Log::error('MediaFileProcessingService: Intervention Image not available');
                return [
                    'success' => false,
                    'error' => 'Image processing library not available.',
                ];
            }

            Log::info('MediaFileProcessingService: Starting image re-encoding', [
                'storage_path' => $storagePath,
                'disk' => $disk,
            ]);

            // ストレージドライバーの種類を確認
            $driver = config("filesystems.disks.{$disk}.driver", 'local');
            $isLocal = $driver === 'local';
            
            // S3などのリモートストレージの場合は一時ファイルにダウンロード
            if (!$isLocal) {
                $tempFilePath = tempnam(sys_get_temp_dir(), 'img_process_');
                $tempFilePath .= '.' . pathinfo($storagePath, PATHINFO_EXTENSION);
                $diskInstance->get($storagePath, $tempFilePath);
            } else {
                // ローカルストレージの場合は直接パスを使用
                $tempFilePath = $diskInstance->path($storagePath);
            }

            // 元のファイルを読み込んで再エンコード
            $image = \Intervention\Image\Laravel\Facades\Image::read($tempFilePath);
            
            // 元の拡張子を取得
            $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
            
            // 拡張子に応じて適切な形式で再エンコードして保存
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $image->toJpeg(90)->save($tempFilePath); // JPEG品質90%
                    break;
                case 'png':
                    $image->toPng()->save($tempFilePath);
                    break;
                case 'webp':
                    $image->toWebp(90)->save($tempFilePath); // WebP品質90%
                    break;
                default:
                    // デフォルトはJPEG
                    $image->toJpeg(90)->save($tempFilePath);
                    break;
            }

            // リモートストレージの場合は処理済みファイルをアップロード
            if (!$isLocal) {
                $diskInstance->put($storagePath, file_get_contents($tempFilePath));
                // 一時ファイルを削除
                if (file_exists($tempFilePath)) {
                    unlink($tempFilePath);
                }
            }

            $newSize = $diskInstance->size($storagePath);
            Log::info('MediaFileProcessingService: Image re-encoded successfully', [
                'storage_path' => $storagePath,
                'new_size' => $newSize,
            ]);

            return [
                'success' => true,
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::error('MediaFileProcessingService: Exception during image re-encoding', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'storage_path' => $storagePath,
                'disk' => $disk,
            ]);

            // 一時ファイルをクリーンアップ
            if ($tempFilePath && file_exists($tempFilePath) && !$isLocal) {
                @unlink($tempFilePath);
            }

            return [
                'success' => false,
                'error' => 'Failed to re-encode image: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 動画・音声ファイルからメタデータを削除（ffmpeg使用）
     * S3対応: ファイルパスではなくストレージパスとディスク名を受け取る
     *
     * @param string $storagePath ストレージ内のファイルパス（例: 'response_media/xxx.mp4'）
     * @param string $mediaType メディアタイプ（'video' or 'audio'）
     * @param string $disk ディスク名（'public' など）
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function removeMetadata(string $storagePath, string $mediaType, string $disk = 'public'): array
    {
        $diskInstance = Storage::disk($disk);
        $tempFilePath = null;
        $tempOutputPath = null;
        
        try {
            // ファイルの存在確認
            if (!$diskInstance->exists($storagePath)) {
                Log::error('MediaFileProcessingService: File not found', [
                    'storage_path' => $storagePath,
                    'disk' => $disk,
                ]);
                return [
                    'success' => false,
                    'error' => 'File not found.',
                ];
            }

            Log::info('MediaFileProcessingService: Starting metadata removal', [
                'storage_path' => $storagePath,
                'media_type' => $mediaType,
                'disk' => $disk,
            ]);

            // ストレージドライバーの種類を確認
            $driver = config("filesystems.disks.{$disk}.driver", 'local');
            $isLocal = $driver === 'local';
            
            // S3などのリモートストレージの場合は一時ファイルにダウンロード
            if (!$isLocal) {
                $tempFilePath = tempnam(sys_get_temp_dir(), 'media_input_');
                $tempFilePath .= '.' . pathinfo($storagePath, PATHINFO_EXTENSION);
                $diskInstance->get($storagePath, $tempFilePath);
            } else {
                // ローカルストレージの場合は直接パスを使用
                $tempFilePath = $diskInstance->path($storagePath);
            }

            // 一時出力ファイル名を生成
            $extension = pathinfo($storagePath, PATHINFO_EXTENSION);
            $tempOutputPath = tempnam(sys_get_temp_dir(), 'media_output_');
            $tempOutputPath .= '.' . $extension;
            
            // ffmpegコマンドを実行してメタデータを削除
            $process = new Process([
                'ffmpeg',
                '-i', $tempFilePath,
                '-map_metadata', '-1', // すべてのメタデータを削除
                '-c', 'copy', // コーデックをコピー（再エンコードしない）
                '-y', // 上書き確認なし
                $tempOutputPath,
            ]);

            $process->setTimeout(300); // 5分のタイムアウト
            $process->run();

            if (!$process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                Log::warning('MediaFileProcessingService: ffmpeg failed', [
                    'error' => $errorOutput,
                    'exit_code' => $process->getExitCode(),
                    'storage_path' => $storagePath,
                ]);

                // 一時ファイルをクリーンアップ
                if ($tempOutputPath && file_exists($tempOutputPath)) {
                    @unlink($tempOutputPath);
                }
                if ($tempFilePath && file_exists($tempFilePath) && !$isLocal) {
                    @unlink($tempFilePath);
                }

                // ffmpegがインストールされていない場合
                if ($process->getExitCode() === 127) {
                    Log::warning('MediaFileProcessingService: ffmpeg not found, skipping metadata removal');
                    // 開発環境ではスキップ可能
                    if (env('SKIP_MEDIA_VALIDATION_TOOLS', false) === true) {
                        return [
                            'success' => true,
                            'error' => null,
                        ];
                    }
                    return [
                        'success' => false,
                        'error' => 'ffmpeg is not available.',
                    ];
                }

                return [
                    'success' => false,
                    'error' => 'Failed to remove metadata: ' . $errorOutput,
                ];
            }

            // 処理済みファイルをストレージにアップロード
            if (file_exists($tempOutputPath)) {
                if (!$isLocal) {
                    // リモートストレージの場合はアップロード
                    $diskInstance->put($storagePath, file_get_contents($tempOutputPath));
                } else {
                    // ローカルストレージの場合は直接置き換え
                    if (file_exists($tempFilePath)) {
                        @unlink($tempFilePath);
                    }
                    rename($tempOutputPath, $tempFilePath);
                }
                
                // 一時出力ファイルを削除（既にアップロード/移動済み）
                if (file_exists($tempOutputPath)) {
                    @unlink($tempOutputPath);
                }
            } else {
                Log::warning('MediaFileProcessingService: Temporary output file not created', [
                    'storage_path' => $storagePath,
                    'temp_output_path' => $tempOutputPath,
                ]);
                
                // 一時ファイルをクリーンアップ
                if ($tempFilePath && file_exists($tempFilePath) && !$isLocal) {
                    @unlink($tempFilePath);
                }
                
                return [
                    'success' => false,
                    'error' => 'Temporary file was not created.',
                ];
            }

            // 一時入力ファイルをクリーンアップ（リモートストレージの場合のみ）
            if ($tempFilePath && file_exists($tempFilePath) && !$isLocal) {
                @unlink($tempFilePath);
            }

            $newSize = $diskInstance->size($storagePath);
            Log::info('MediaFileProcessingService: Metadata removed successfully', [
                'storage_path' => $storagePath,
                'new_size' => $newSize,
            ]);

            return [
                'success' => true,
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::error('MediaFileProcessingService: Exception during metadata removal', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'storage_path' => $storagePath,
                'disk' => $disk,
            ]);

            // 一時ファイルをクリーンアップ
            if ($tempOutputPath && file_exists($tempOutputPath)) {
                @unlink($tempOutputPath);
            }
            if ($tempFilePath && file_exists($tempFilePath) && !$isLocal) {
                @unlink($tempFilePath);
            }

            return [
                'success' => false,
                'error' => 'Failed to remove metadata: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * メディアファイルを処理（画像は再エンコード、動画・音声はメタデータ削除）
     * S3対応: ファイルパスではなくストレージパスとディスク名を受け取る
     *
     * @param string $storagePath ストレージ内のファイルパス（例: 'response_media/xxx.jpg'）
     * @param string $mediaType メディアタイプ（'image', 'video', 'audio'）
     * @param string $disk ディスク名（'public' など）
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function processMediaFile(string $storagePath, string $mediaType, string $disk = 'public'): array
    {
        if ($mediaType === 'image') {
            return $this->reencodeImage($storagePath, $mediaType, $disk);
        } elseif ($mediaType === 'video' || $mediaType === 'audio') {
            return $this->removeMetadata($storagePath, $mediaType, $disk);
        } else {
            Log::warning('MediaFileProcessingService: Unknown media type', [
                'media_type' => $mediaType,
            ]);
            return [
                'success' => false,
                'error' => 'Unknown media type: ' . $mediaType,
            ];
        }
    }
}
