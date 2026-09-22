<?php

declare(strict_types=1);

namespace App\Workforce\Domain\Enum;

/**
 * 15 Quebec CCQ regions.
 */
enum CcqRegion: string
{
    case Montreal = 'montreal';
    case QuebecCity = 'quebec_city';
    case Outaouais = 'outaouais';
    case Estrie = 'estrie';
    case Mauricie = 'mauricie';
    case Saguenay = 'saguenay';
    case CoteNord = 'cote_nord';
    case Gaspesie = 'gaspesie';
    case Abitibi = 'abitibi';
    case Laval = 'laval';
    case Monteregie = 'monteregie';
    case Lanaudiere = 'lanaudiere';
    case Laurentides = 'laurentides';
    case CentreDuQuebec = 'centre_du_quebec';
    case BasSaintLaurent = 'bas_saint_laurent';

    public function label(): string
    {
        return match ($this) {
            self::Montreal => 'Montréal',
            self::QuebecCity => 'Québec',
            self::Outaouais => 'Outaouais',
            self::Estrie => 'Estrie',
            self::Mauricie => 'Mauricie',
            self::Saguenay => 'Saguenay–Lac-Saint-Jean',
            self::CoteNord => 'Côte-Nord',
            self::Gaspesie => 'Gaspésie–Îles-de-la-Madeleine',
            self::Abitibi => 'Abitibi-Témiscamingue',
            self::Laval => 'Laval',
            self::Monteregie => 'Montérégie',
            self::Lanaudiere => 'Lanaudière',
            self::Laurentides => 'Laurentides',
            self::CentreDuQuebec => 'Centre-du-Québec',
            self::BasSaintLaurent => 'Bas-Saint-Laurent',
        };
    }

    /**
     * Whether this region requires a per-diem flag for workers.
     * Used by rule 20 (cote_nord_perdiem).
     */
    public function requiresPerDiem(): bool
    {
        return $this === self::CoteNord;
    }
}
