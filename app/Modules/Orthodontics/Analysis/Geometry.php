<?php

namespace App\Modules\Orthodontics\Analysis;

/**
 * Plain vector geometry used by the analysis calculators.
 *
 * 2D points are [x, y] in image pixels with y pointing DOWN (screen
 * coordinates). 3D points are [x, y, z] in model units. Everything is
 * deterministic; there is no estimation or randomness anywhere.
 */
final class Geometry
{
    public static function sub(array $a, array $b): array
    {
        $out = [];
        foreach ($a as $i => $v) {
            $out[$i] = $v - $b[$i];
        }

        return $out;
    }

    public static function add(array $a, array $b): array
    {
        $out = [];
        foreach ($a as $i => $v) {
            $out[$i] = $v + $b[$i];
        }

        return $out;
    }

    public static function scale(array $a, float $k): array
    {
        return array_map(fn ($v) => $v * $k, $a);
    }

    public static function dot(array $a, array $b): float
    {
        $sum = 0.0;
        foreach ($a as $i => $v) {
            $sum += $v * $b[$i];
        }

        return $sum;
    }

    public static function norm(array $a): float
    {
        return sqrt(self::dot($a, $a));
    }

    public static function distance(array $a, array $b): float
    {
        return self::norm(self::sub($a, $b));
    }

    public static function midpoint(array $a, array $b): array
    {
        return self::scale(self::add($a, $b), 0.5);
    }

    public static function cross3(array $a, array $b): array
    {
        return [
            $a[1] * $b[2] - $a[2] * $b[1],
            $a[2] * $b[0] - $a[0] * $b[2],
            $a[0] * $b[1] - $a[1] * $b[0],
        ];
    }

    /** z component of the 2D cross product a x b. */
    public static function cross2(array $a, array $b): float
    {
        return $a[0] * $b[1] - $a[1] * $b[0];
    }

    /**
     * Unsigned angle between two vectors, in degrees (0..180).
     */
    public static function angleBetween(array $u, array $v): ?float
    {
        $nu = self::norm($u);
        $nv = self::norm($v);
        if ($nu < 1e-12 || $nv < 1e-12) {
            return null;
        }
        $cos = max(-1.0, min(1.0, self::dot($u, $v) / ($nu * $nv)));

        return rad2deg(acos($cos));
    }

    /**
     * Angle at vertex B of the path A-B-C, in degrees (0..180).
     */
    public static function angleAt(array $a, array $b, array $c): ?float
    {
        return self::angleBetween(self::sub($a, $b), self::sub($c, $b));
    }

    /**
     * Acute angle between two undirected lines (0..90).
     */
    public static function lineAngle(array $p1, array $p2, array $q1, array $q2): ?float
    {
        $angle = self::angleBetween(self::sub($p2, $p1), self::sub($q2, $q1));

        return $angle === null ? null : ($angle > 90 ? 180 - $angle : $angle);
    }

    /**
     * Signed perpendicular distance from P to the 2D line through A and B.
     * Positive when P is on the side the unit normal `towards` points to.
     * `$towards` is any vector giving the positive direction (e.g. anterior).
     */
    public static function signedDistanceToLine2(array $p, array $a, array $b, array $towards): ?float
    {
        $d = self::sub($b, $a);
        $len = self::norm($d);
        if ($len < 1e-12) {
            return null;
        }
        $n = [-$d[1] / $len, $d[0] / $len];
        if (self::dot($n, $towards) < 0) {
            $n = [-$n[0], -$n[1]];
        }

        return self::dot(self::sub($p, $a), $n);
    }

    /**
     * Orthogonal projection of P on the line through A and B.
     */
    public static function projectOnLine(array $p, array $a, array $b): ?array
    {
        $d = self::sub($b, $a);
        $len2 = self::dot($d, $d);
        if ($len2 < 1e-12) {
            return null;
        }
        $t = self::dot(self::sub($p, $a), $d) / $len2;

        return self::add($a, self::scale($d, $t));
    }

    /**
     * Length of an ordered polyline.
     */
    public static function polylineLength(array $points): float
    {
        $length = 0.0;
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $length += self::distance($points[$i - 1], $points[$i]);
        }

        return $length;
    }

    /**
     * Unsigned distance from P to the plane through A, B and C (3D).
     */
    public static function distanceToPlane(array $p, array $a, array $b, array $c): ?float
    {
        $n = self::cross3(self::sub($b, $a), self::sub($c, $a));
        $len = self::norm($n);
        if ($len < 1e-12) {
            return null;
        }

        return abs(self::dot(self::sub($p, $a), $n)) / $len;
    }

    /**
     * Distance from P to the 3D line through A and B.
     */
    public static function distanceToLine3(array $p, array $a, array $b): ?float
    {
        $d = self::sub($b, $a);
        $len = self::norm($d);
        if ($len < 1e-12) {
            return null;
        }

        return self::norm(self::cross3(self::sub($p, $a), $d)) / $len;
    }

    public static function round(?float $value, int $decimals = 2): ?float
    {
        return $value === null ? null : round($value, $decimals);
    }
}
