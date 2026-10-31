<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Draws the user's initials locally so the avatar never depends on an outside service
 * (Filament's default calls ui-avatars.com, which fails on slow or filtered connections).
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->map(fn (string $part): string => mb_substr(preg_replace('/^[^\p{L}\p{N}]+/u', '', $part) ?? '', 0, 1))
            ->filter()
            ->take(2)
            ->join('');

        $initials = e(mb_strtoupper($initials ?: '?'));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">'
            .'<rect width="40" height="40" fill="#214f33"/>'
            .'<text x="20" y="20" dy=".35em" text-anchor="middle" fill="#ffffff" '
            .'font-family="IBM Plex Sans, Noto Sans Ethiopic, sans-serif" font-size="15" font-weight="600">'
            .$initials.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
