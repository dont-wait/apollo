<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'DRAFT';
    case Review = 'REVIEW';
    case Scheduled = 'SCHEDULED';
    case Published = 'PUBLISHED';
    case Archived = 'ARCHIVED';

    /**
     * @return array<string, string>
     */
    public static function selectableOptions(): array
    {
        return [
            self::Draft->value => 'Draft',
            self::Published->value => 'Published',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function filterOptions(): array
    {
        return [
            self::Draft->value => 'Draft',
            self::Review->value => 'Review',
            self::Scheduled->value => 'Scheduled',
            self::Published->value => 'Published',
            self::Archived->value => 'Archived',
        ];
    }

    /**
     * @return list<string>
     */
    public static function selectableValues(): array
    {
        return array_keys(self::selectableOptions());
    }
}
