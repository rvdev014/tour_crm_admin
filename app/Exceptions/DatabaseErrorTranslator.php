<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;

/**
 * Turns a raw PostgreSQL QueryException (unique/foreign-key/not-null violations,
 * value-too-long, deadlocks, ...) into a message an operator can actually act on.
 *
 * Never put SQL, bindings, or the raw driver message in front of a user — that's
 * exactly what leaked in the "hotels_name_unique" Ignition page this class exists
 * to prevent. Always log the original exception separately for support/debugging.
 */
class DatabaseErrorTranslator
{
    /**
     * Known constraint name => the form field it maps to (relative to the form's
     * state path, e.g. "name" becomes "data.name" on a resource Create/Edit page)
     * and a message written for the person filling in the form. `field` is null
     * when the constraint can't be tied to one visible field (composite keys,
     * read-only generated values).
     */
    private const CONSTRAINTS = [
        'hotels_name_unique' => [
            'field' => 'name',
            'message' => 'A hotel with this name already exists. Check the hotel list before creating a new one.',
        ],
        'suppliers_inn_unique' => [
            'field' => 'inn',
            'message' => 'A supplier with this INN already exists.',
        ],
        'tours_group_number_unique' => [
            'field' => null,
            'message' => 'This group number was just taken by another tour. Please save again.',
        ],
        'route_prices_route_id_transport_class_id_unique' => [
            'field' => null,
            'message' => 'This transport class already has a price on this route.',
        ],
    ];

    /** Referenced/referencing table name => plain-English label for messages. */
    private const TABLE_LABELS = [
        'tour_day_expenses' => 'tours',
        'hotel_requests' => 'hotel booking requests',
        'hotel_reviews' => 'reviews',
        'transfer_requests' => 'transfer requests',
        'buy_requests' => 'purchase requests',
        'tour_requests' => 'tour requests',
        'web_tour_requests' => 'web tour requests',
        'transfers' => 'transfers',
    ];

    /**
     * @return array{title: string, message: string, field: ?string}
     */
    public static function translate(QueryException $e): array
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $driverMessage = $e->getMessage();

        $constraint = self::firstMatch('/constraint "([a-zA-Z0-9_]+)"/', $driverMessage);

        if ($constraint && isset(self::CONSTRAINTS[$constraint])) {
            return [
                'title' => 'Could not save',
                'message' => self::CONSTRAINTS[$constraint]['message'],
                'field' => self::CONSTRAINTS[$constraint]['field'],
            ];
        }

        return match ($sqlState) {
            '23505' => [
                'title' => 'Already exists',
                'message' => 'A record with this value already exists.',
                'field' => null,
            ],
            '23503' => self::foreignKeyMessage($driverMessage),
            '23502' => self::notNullMessage($driverMessage),
            '22001' => [
                'title' => 'Value too long',
                'message' => 'One of the values you entered is too long for its field.',
                'field' => null,
            ],
            '22P02' => [
                'title' => 'Invalid value',
                'message' => 'One of the values you entered has an invalid format.',
                'field' => null,
            ],
            '40001', '40P01' => [
                'title' => 'Please try again',
                'message' => 'Another change happened at the same time. Please try again.',
                'field' => null,
            ],
            default => [
                'title' => 'Something went wrong',
                'message' => 'We could not complete this action. Please try again or contact support.',
                'field' => null,
            ],
        };
    }

    /**
     * @return array{title: string, message: string, field: ?string}
     */
    private static function notNullMessage(string $driverMessage): array
    {
        $column = self::firstMatch('/column "([a-zA-Z0-9_]+)"/', $driverMessage);

        return [
            'title' => 'Missing information',
            'message' => 'A required field is empty'.($column ? " ({$column})" : '').'. Please check the form.',
            'field' => null,
        ];
    }

    /**
     * @return array{title: string, message: string, field: ?string}
     */
    private static function foreignKeyMessage(string $driverMessage): array
    {
        // "update or delete on table ... violates foreign key constraint ... on table X"
        // — a delete/update was blocked because table X still references this row.
        if (str_contains($driverMessage, 'update or delete on table')) {
            $referencingTable = self::lastMatch('/\son table "([a-zA-Z0-9_]+)"/', $driverMessage);
            $label = $referencingTable
                ? (self::TABLE_LABELS[$referencingTable] ?? str_replace('_', ' ', $referencingTable))
                : 'other records';

            return [
                'title' => 'Cannot delete',
                'message' => "This record is still used in {$label} and can't be deleted.",
                'field' => null,
            ];
        }

        // "insert or update ... violates foreign key constraint ..." with
        // "... is not present in table X" in the DETAIL line — a selected parent
        // record (e.g. a hotel picked in a dropdown) no longer exists.
        $parentTable = self::firstMatch('/not present in table "([a-zA-Z0-9_]+)"/', $driverMessage);
        $label = $parentTable ? (self::TABLE_LABELS[$parentTable] ?? str_replace('_', ' ', $parentTable)) : null;

        return [
            'title' => 'Invalid reference',
            'message' => $label
                ? "The selected {$label} record no longer exists. Please refresh and try again."
                : 'One of the selected records no longer exists. Please refresh and try again.',
            'field' => null,
        ];
    }

    private static function firstMatch(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $matches) ? $matches[1] : null;
    }

    private static function lastMatch(string $pattern, string $subject): ?string
    {
        return preg_match_all($pattern, $subject, $matches) ? end($matches[1]) : null;
    }
}
