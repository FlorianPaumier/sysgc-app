<?php

declare(strict_types=1);

namespace App\Enum;

use function in_array;

/**
 * 25 Quebec CCQ construction trades.
 */
enum CcqTrade: string
{
    case Carpenter = 'carpenter';
    case Electrician = 'electrician';
    case Plumber = 'plumber';
    case Plasterer = 'plasterer';
    case Painter = 'painter';
    case Roofer = 'roofer';
    case Bricklayer = 'bricklayer';
    case Tiler = 'tiler';
    case Insulator = 'insulator';
    case Ironworker = 'ironworker';
    case Pipefitter = 'pipefitter';
    case RefrigerationMechanic = 'refrigeration_mechanic';
    case ElevatorMechanic = 'elevator_mechanic';
    case SprinklerFitter = 'sprinkler_fitter';
    case Glazier = 'glazier';
    case HeavyEquipmentOperator = 'heavy_equipment_operator';
    case CraneOperator = 'crane_operator';
    case ConcreteFinisher = 'concrete_finisher';
    case Laborer = 'laborer';
    case Surveyor = 'surveyor';
    case Welder = 'welder';
    case Steamfitter = 'steamfitter';
    case SheetMetalWorker = 'sheet_metal_worker';
    case Boilermaker = 'boilermaker';
    case Millwright = 'millwright';

    public function label(): string
    {
        return match ($this) {
            self::Carpenter => 'Charpentier-menuisier',
            self::Electrician => 'Électricien',
            self::Plumber => 'Plombier',
            self::Plasterer => 'Plâtrier',
            self::Painter => 'Peintre',
            self::Roofer => 'Couvreur',
            self::Bricklayer => 'Briqueteur-maçon',
            self::Tiler => 'Carreleur',
            self::Insulator => 'Calorifugeur',
            self::Ironworker => 'Monteur d\'acier',
            self::Pipefitter => 'Tuyauteur',
            self::RefrigerationMechanic => 'Mécanicien en réfrigération',
            self::ElevatorMechanic => 'Mécanicien d\'ascenseur',
            self::SprinklerFitter => 'Poseur de gicleurs',
            self::Glazier => 'Vitrier',
            self::HeavyEquipmentOperator => 'Opérateur d\'équipement lourd',
            self::CraneOperator => 'Grutier',
            self::ConcreteFinisher => 'Finisseur de béton',
            self::Laborer => 'Manœuvre',
            self::Surveyor => 'Arpenteur',
            self::Welder => 'Soudeur',
            self::Steamfitter => 'Monteur de vapeur',
            self::SheetMetalWorker => 'Tôlier',
            self::Boilermaker => 'Chaudronnier',
            self::Millwright => 'Mécanicien de chantier',
        };
    }

    /**
     * Whether this trade is restricted in the given region.
     * Used by rule 7 (trade_region_mismatch) and rule 9 (plumber_region_restriction).
     */
    public function isRestrictedInRegion(CcqRegion $region): bool
    {
        // Elevator mechanics only work in major urban centers
        if ($this === self::ElevatorMechanic) {
            return ! in_array($region, [CcqRegion::Montreal, CcqRegion::QuebecCity, CcqRegion::Laval], true);
        }

        // Crane operators restricted in very remote regions
        if ($this === self::CraneOperator) {
            return in_array($region, [CcqRegion::CoteNord, CcqRegion::Gaspesie, CcqRegion::Abitibi], true);
        }

        // Plumbers in remote regions need additional certification (rule 9)
        if ($this === self::Plumber) {
            return in_array($region, [CcqRegion::CoteNord, CcqRegion::Gaspesie, CcqRegion::Abitibi], true);
        }

        return false;
    }

    /**
     * Whether overtime flag is recommended when exceeding 40h/week.
     * Used by rule 8 (electrician_overtime).
     */
    public function requiresOvertimeFlagAbove40h(): bool
    {
        return $this === self::Electrician;
    }

    /**
     * Whether this trade has seasonal restrictions.
     * Used by rule 18 (seasonal_restriction_roofing).
     */
    public function isRestrictedInMonth(int $month): bool
    {
        if ($this === self::Roofer) {
            return $month === 1; // January only per spec
        }

        return false;
    }
}
