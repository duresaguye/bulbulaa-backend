<?php

namespace App\Filament\Support;

use App\Filament\RichContentBlocks\YouTubeVideoBlock;
use App\Support\PostHtml;
use Filament\Forms\Components\RichEditor;

/**
 * The editor for long website text: headings, lists, tables, photos in the text, collapsible questions and
 * answers, and YouTube videos. What it produces is rendered and cleaned by PostHtml before the website sees it.
 */
class ArticleEditor
{
    public static function make(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'highlight', 'link'],
                ['h2', 'h3'],
                ['bulletList', 'orderedList', 'blockquote', 'horizontalRule'],
                ['table', 'attachFiles', 'details', 'customBlocks'],
                ['undo', 'redo'],
            ])
            ->fileAttachmentsDisk(PostHtml::ATTACHMENTS_DISK)
            ->fileAttachmentsDirectory(PostHtml::ATTACHMENTS_DIRECTORY)
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->fileAttachmentsMaxSize(5120)
            ->preventFileAttachmentPathTampering()
            ->customBlocks([YouTubeVideoBlock::class])
            ->minimalCustomBlockControls()
            ->stickyToolbar();
    }
}
