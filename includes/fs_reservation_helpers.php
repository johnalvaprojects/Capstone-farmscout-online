<?php
/**
 * Reservation grouping (multi-product) + public reference helpers.
 */

if (!function_exists('fs_reservations_parent_column_exists')) {
    function fs_reservations_parent_column_exists(PDO $conn): bool {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $s = $conn->query("SHOW COLUMNS FROM reservations LIKE 'parent_reservation_id'");
            $cache = ($s && $s->rowCount() > 0);
        } catch (Throwable $e) {
            $cache = false;
        }
        return $cache;
    }
}

if (!function_exists('fs_reservations_public_ref_columns_exist')) {
    function fs_reservations_public_ref_columns_exist(PDO $conn): bool {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $a = $conn->query("SHOW COLUMNS FROM reservations LIKE 'public_ref'");
            $b = $conn->query("SHOW COLUMNS FROM reservations LIKE 'chat_public_ref'");
            $cache = ($a && $a->rowCount() > 0 && $b && $b->rowCount() > 0);
        } catch (Throwable $e) {
            $cache = false;
        }
        return $cache;
    }
}

if (!function_exists('fs_reservation_root_id')) {
    /**
     * Thread / payment / display root id for a reservation row.
     */
    function fs_reservation_root_id(PDO $conn, int $reservationId): int {
        if ($reservationId <= 0) {
            return $reservationId;
        }
        if (!fs_reservations_parent_column_exists($conn)) {
            return $reservationId;
        }
        $st = $conn->prepare('SELECT id, COALESCE(parent_reservation_id, 0) AS pid FROM reservations WHERE id = ? LIMIT 1');
        $st->execute([$reservationId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $reservationId;
        }
        $pid = (int)($row['pid'] ?? 0);
        return $pid > 0 ? $pid : (int)$row['id'];
    }
}

if (!function_exists('fs_next_reservation_public_refs')) {
    /**
     * Allocate sequential RSV-YYYY-NNNN and CHAT-YYYY-NNNN for a new root row.
     *
     * @return array{0:string,1:string} [public_ref, chat_public_ref]
     */
    function fs_next_reservation_public_refs(PDO $conn): array {
        $year = date('Y');
        $maxSeq = static function (string $prefix) use ($conn, $year): int {
            $like = $prefix . '-' . $year . '-%';
            $col = $prefix === 'CHAT' ? 'chat_public_ref' : 'public_ref';
            $st = $conn->prepare("SELECT `{$col}` FROM reservations WHERE `{$col}` IS NOT NULL AND `{$col}` LIKE ?");
            $st->execute([$like]);
            $max = 0;
            while ($val = $st->fetchColumn()) {
                $parts = explode('-', (string)$val);
                $n = (int) end($parts);
                if ($n > $max) {
                    $max = $n;
                }
            }
            return $max;
        };
        $next = max($maxSeq('RSV'), $maxSeq('CHAT')) + 1;
        $seq = str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        return ['RSV-' . $year . '-' . $seq, 'CHAT-' . $year . '-' . $seq];
    }
}

if (!function_exists('fs_reservation_display_public_ref')) {
    function fs_reservation_display_public_ref(?string $publicRef, int $numericId): string {
        $publicRef = trim((string) $publicRef);
        return $publicRef !== '' ? $publicRef : ('#' . $numericId);
    }
}

if (!function_exists('fs_reservation_display_chat_ref')) {
    function fs_reservation_display_chat_ref(?string $chatRef, int $rootNumericId): string {
        $chatRef = trim((string) $chatRef);
        return $chatRef !== '' ? $chatRef : ('#' . $rootNumericId);
    }
}
