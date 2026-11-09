<?php

namespace App\Enums;

/**
 * Vehicle fuel / power source. Deliberately separate from the vehicle category.
 */
enum FuelType: string
{
    case Petrol = 'petrol';
    case Diesel = 'diesel';
    case Electric = 'electric';
    case Hybrid = 'hybrid';
    case Other = 'other';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Petrol => 'Petrol',
            self::Diesel => 'Diesel',
            self::Electric => 'Electric',
            self::Hybrid => 'Hybrid',
            self::Other => 'Other',
            self::NotApplicable => 'Not applicable',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Values allowed for motorised (true) or non-motorised (false) vehicle categories.
     *
     * @return list<string>
     */
    public static function allowedFor(bool $motorized): array
    {
        return $motorized
            ? array_values(array_diff(self::values(), [self::NotApplicable->value]))
            : [self::NotApplicable->value];
    }

    /**
     * Default used when staff record a new vehicle at the gate without a fuel type.
     */
    public static function defaultFor(bool $motorized): self
    {
        return $motorized ? self::Other : self::NotApplicable;
    }
}
