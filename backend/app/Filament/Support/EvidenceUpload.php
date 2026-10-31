<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;

/**
 * Supporting documents are stored on the private disk and only served through short-lived signed links.
 */
class EvidenceUpload
{
    public static function make(string $directory): FileUpload
    {
        return FileUpload::make('evidence_paths')
            ->label('Supporting evidence')
            ->helperText('PDF, image, Word, or Excel files up to 10 MB each.')
            ->multiple()
            ->disk('local')
            ->directory("evidence/{$directory}")
            ->visibility('private')
            ->maxSize(10240)
            ->maxFiles(10)
            ->acceptedFileTypes([
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->downloadable()
            ->openable()
            ->previewable(false)
            ->columnSpanFull();
    }
}
