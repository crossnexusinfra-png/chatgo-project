<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class MediaFileProcessingService
{
    /**
     * 画像ファイルを再エンコード（Intervention Image使用）
     *
     * @param string $filePath ファイルのフルパス
     * @param string $mediaType メディアタイプ（'image'）
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function reencodeImage(string $filePath, string $mediaType = 'image'): array
    {
        try {
            if (!file_exists($filePath)) {
                Log::error('MediaFileProcessingService: File not found', [
                    'file_path' => $filePath,
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
                'file_path' => $filePath,
            ]);

            // 元のファイルを読み込んで再エンコード
            $image = \Intervention\Image\Laravel\Facades\Image::read($filePath);
            
            // 元の拡張子を取得
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            // 拡張子に応じて適切な形式で再エンコードして保存
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $image->toJpeg(90)->save($filePath); // JPEG品質90%
                    break;
                case 'png':
                    $image->toPng()->save($filePath);
                    break;
                case 'webp':
                    $image->toWebp(90)->save($filePath); // WebP品質90%
                    break;
                default:
                    // デフォルトはJPEG
                    $image->toJpeg(90)->save($filePath);
                    break;
            }

            Log::info('MediaFileProcessingService: Image re-encoded successfully', [
                'file_path' => $filePath,
                'new_size' => filesize($filePath),
            ]);

            return [
                'success' => true,
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::error('MediaFileProcessingService: Exception during image re-encoding', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_path' => $filePath,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to re-encode image: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 動画・音声ファイルからメタデータを削除（ffmpeg使用）
     *
     * @param string $filePath ファイルのフルパス
     * @param string $mediaType メディアタイプ（'video' or 'audio'）
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function removeMetadata(string $filePath, string $mediaType): array
    {
        try {
            if (!file_exists($filePath)) {
                Log::error('MediaFileProcessingService: File not found', [
                    'file_path' => $filePath,
                ]);
                return [
                    'success' => false,
                    'error' => 'File not found.',
                ];
            }

            Log::info('MediaFileProcessingService: Starting metadata removal', [
                'file_path' => $filePath,
                'media_type' => $mediaType,
            ]);

            // 一時ファイル名を生成
            $tempFilePath = $filePath . '.tmp.' . pathinfo($filePath, PATHINFO_EXTENSION);
            
            // ffmpegコマンドを実行してメタデータを削除
            $process = new Process([
                'ffmpeg',
                '-i', $filePath,
                '-map_metadata', '-1', // すべてのメタデータを削除
                '-c', 'copy', // コーデックをコピー（再エンコードしない）
                '-y', // 上書き確認なし
                $tempFilePath,
            ]);

            $process->setTimeout(300); // 5分のタイムアウト
            $process->run();

            if (!$process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                Log::warning('MediaFileProcessingService: ffmpeg failed', [
                    'error' => $errorOutput,
                    'exit_code' => $process->getExitCode(),
                    'file_path' => $filePath,
                ]);

                // 一時ファイルが作成されていたら削除
                if (file_exists($tempFilePath)) {
                    unlink($tempFilePath);
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

            // 元のファイルを削除して、一時ファイルをリネーム
            if (file_exists($tempFilePath)) {
                unlink($filePath);
                rename($tempFilePath, $filePath);
            } else {
                Log::warning('MediaFileProcessingService: Temporary file not created', [
                    'file_path' => $filePath,
                    'temp_file_path' => $tempFilePath,
                ]);
                return [
                    'success' => false,
                    'error' => 'Temporary file was not created.',
                ];
            }

            Log::info('MediaFileProcessingService: Metadata removed successfully', [
                'file_path' => $filePath,
                'new_size' => filesize($filePath),
            ]);

            return [
                'success' => true,
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::error('MediaFileProcessingService: Exception during metadata removal', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_path' => $filePath,
            ]);

            // 一時ファイルが残っていたら削除
            $tempFilePath = $filePath . '.tmp.' . pathinfo($filePath, PATHINFO_EXTENSION);
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }

            return [
                'success' => false,
                'error' => 'Failed to remove metadata: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * メディアファイルを処理（画像は再エンコード、動画・音声はメタデータ削除）
     *
     * @param string $filePath ファイルのフルパス
     * @param string $mediaType メディアタイプ（'image', 'video', 'audio'）
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function processMediaFile(string $filePath, string $mediaType): array
    {
        if ($mediaType === 'image') {
            return $this->reencodeImage($filePath, $mediaType);
        } elseif ($mediaType === 'video' || $mediaType === 'audio') {
            return $this->removeMetadata($filePath, $mediaType);
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
