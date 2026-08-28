<?php

namespace App\Support;

use App\Models\GameChallenge\GameChallenge;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdminSearch
{
    public static function keyword(): string
    {
        $search = request('search');

        if (is_array($search)) {
            return trim((string) ($search['value'] ?? ''));
        }

        return trim((string) $search);
    }

    public static function isMobile(string $keyword): bool
    {
        return (bool) preg_match('/^\d{10}$/', $keyword);
    }

    public static function isCode(string $keyword): bool
    {
        return (bool) preg_match('/^LH[A-Z0-9]+$/i', $keyword);
    }

    public static function userIdsForKeyword(string $keyword): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $query = User::query()->withoutGlobalScopes()->select('id');
        self::applyUserColumns($query, $keyword);

        return $query->limit(200)->pluck('id')->all();
    }

    public static function applyUserColumns(Builder $query, string $keyword): void
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return;
        }

        $like = addcslashes($keyword, '%_\\');

        if (self::isMobile($keyword)) {
            $query->where('mobile', $keyword);
            return;
        }

        if (self::isCode($keyword)) {
            $query->where('uid', $keyword);
            return;
        }

        if (ctype_digit($keyword) && strlen($keyword) <= 8) {
            $query->where(function ($inner) use ($keyword, $like) {
                $inner->where('id', (int) $keyword)
                    ->orWhere('mobile', 'like', $like.'%')
                    ->orWhere('uid', 'like', $like.'%');
            });
            return;
        }

        $query->where(function ($inner) use ($like) {
            $inner->where('uid', 'like', $like.'%')
                ->orWhere('mobile', 'like', $like.'%')
                ->orWhere('name', 'like', $like.'%')
                ->orWhere('email', 'like', $like.'%');
        });
    }

    public static function applyWalletSearch(Builder $query, string $keyword): void
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return;
        }

        $like = addcslashes($keyword, '%_\\');
        $userIds = self::userIdsForKeyword($keyword);

        if (self::isMobile($keyword) || (ctype_digit($keyword) && strlen($keyword) <= 8 && ! self::isCode($keyword))) {
            $query->whereIn('user_id', $userIds ?: [0]);
            return;
        }

        if (self::isCode($keyword)) {
            $gameIds = GameChallenge::query()
                ->where('uid', $keyword)
                ->limit(20)
                ->pluck('id')
                ->all();

            $query->where(function ($outer) use ($userIds, $gameIds) {
                $matched = false;
                if ($userIds !== []) {
                    $outer->orWhereIn('user_id', $userIds);
                    $matched = true;
                }
                if ($gameIds !== []) {
                    $outer->orWhereIn('game_challenge_id', $gameIds);
                    $matched = true;
                }
                if (! $matched) {
                    $outer->whereRaw('0 = 1');
                }
            });
            return;
        }

        $query->where(function ($outer) use ($userIds, $like) {
            if ($userIds !== []) {
                $outer->orWhereIn('user_id', $userIds);
            }
            $outer->orWhere('remark', 'like', $like.'%')
                ->orWhere('transaction_id', 'like', $like.'%');
        });
    }

    public static function applyGameChallengeSearch(Builder $query, string $keyword): void
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return;
        }

        $like = addcslashes($keyword, '%_\\');
        $userIds = self::userIdsForKeyword($keyword);

        $query->where(function ($outer) use ($keyword, $like, $userIds) {
            if (self::isCode($keyword)) {
                $outer->where('uid', $keyword)
                    ->orWhere('roomcode', $keyword);
            } else {
                $outer->where('uid', 'like', $like.'%')
                    ->orWhere('roomcode', 'like', $like.'%');
            }

            if ($userIds !== []) {
                $outer->orWhereIn('challenger_id', $userIds)
                    ->orWhereIn('opponent_id', $userIds);
            }
        });
    }

    public static function dayRange(?string $date): ?array
    {
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $day = carbon()->parse($date);

        return [$day->copy()->startOfDay(), $day->copy()->endOfDay()];
    }
}
